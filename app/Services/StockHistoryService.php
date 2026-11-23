<?php

namespace App\Services;

use App\Enums\StockReferenceType;
use App\Enums\WarehouseType;
use App\Models\StockMovement;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Reads the stock ledger. Every row gets a `document_number` attribute: the number of the opname, receipt or
 * return that caused the movement.
 *
 * @phpstan-type HistoryFilters array{product_id: int|null, warehouse_id: int|null, from: string|null, to: string|null}
 */
class StockHistoryService
{
    /**
     * @param  HistoryFilters  $filters
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $movements = StockMovement::query()
            ->with(['product.baseUnit', 'product.productUnits', 'warehouse'])
            ->when($filters['product_id'], fn (Builder $query, int $productId) => $query->where('product_id', $productId))
            ->when($filters['warehouse_id'], fn (Builder $query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('moved_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('moved_at', '<=', $to.' 23:59:59'))
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $documentNumbers = $this->documentNumbers($movements->getCollection());

        $movements->getCollection()->each(fn (StockMovement $movement) => $movement->setAttribute(
            'document_number',
            $documentNumbers[$movement->reference_type.':'.$movement->reference_id] ?? null,
        ));

        return $movements;
    }

    /**
     * What the product's stock was at the end of a day, per kind of warehouse: the sum of everything the ledger recorded
     * for it up to then, so a product that did not move that day (or that year) still has its answer. A product with
     * nothing recorded yet has no answer (`has_records` is false and the stock is 0 only on paper): the ledger starts
     * when the store began to use the system, so "0" there would be a claim about a time it knows nothing of.
     * `first_recorded_on` is the day of the product's first record, whenever it was, for telling the reader from when
     * the history goes.
     *
     * @return array{sellable: string, dead_stock: string, returns: string, has_records: bool, first_recorded_on: string|null}
     */
    public function stockOn(int $productId, CarbonImmutable $day): array
    {
        $totals = StockMovement::query()->toBase()
            ->join('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')
            ->where('stock_movements.product_id', $productId)
            ->where('stock_movements.moved_at', '<=', $day->endOfDay())
            ->groupBy('warehouses.type')
            ->selectRaw('warehouses.type as type, sum(stock_movements.quantity) as quantity')
            ->pluck('quantity', 'type');

        $firstMovedAt = StockMovement::query()->where('product_id', $productId)->min('moved_at');
        $quantityIn = fn (WarehouseType $type): string => (string) BigDecimal::of((string) ($totals[$type->value] ?? 0))->toScale(3);

        return [
            'sellable' => $quantityIn(WarehouseType::Sellable),
            'dead_stock' => $quantityIn(WarehouseType::DeadStock),
            'returns' => $quantityIn(WarehouseType::Returns),
            'has_records' => $totals->isNotEmpty(),
            'first_recorded_on' => $firstMovedAt === null ? null : CarbonImmutable::parse($firstMovedAt)->toDateString(),
        ];
    }

    /**
     * @param  Collection<int, StockMovement>  $movements
     * @return array<string, string> document number keyed by "reference_type:reference_id"
     */
    private function documentNumbers(Collection $movements): array
    {
        $documentNumbers = [];

        foreach ($movements->whereNotNull('reference_type')->groupBy('reference_type') as $referenceType => $group) {
            $type = StockReferenceType::tryFrom($referenceType);

            if ($type === null) {
                continue;
            }

            foreach ($type->modelClass()::query()->whereKey($group->pluck('reference_id'))->pluck('number', 'id') as $id => $number) {
                $documentNumbers["{$referenceType}:{$id}"] = $number;
            }
        }

        return $documentNumbers;
    }
}
