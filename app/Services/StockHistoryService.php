<?php

namespace App\Services;

use App\Enums\StockReferenceType;
use App\Models\StockMovement;
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
            ->with(['product.baseUnit', 'warehouse'])
            ->when($filters['product_id'], fn (Builder $query, int $productId) => $query->where('product_id', $productId))
            ->when($filters['warehouse_id'], fn (Builder $query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('moved_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('moved_at', '<=', $to.' 23:59:59'))
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $numbers = $this->documentNumbers($movements->getCollection());

        $movements->getCollection()->each(fn (StockMovement $movement) => $movement->setAttribute(
            'document_number',
            $numbers[$movement->reference_type.':'.$movement->reference_id] ?? null,
        ));

        return $movements;
    }

    /**
     * @param  Collection<int, StockMovement>  $movements
     * @return array<string, string> document number keyed by "reference_type:reference_id"
     */
    private function documentNumbers(Collection $movements): array
    {
        $numbers = [];

        foreach ($movements->whereNotNull('reference_type')->groupBy('reference_type') as $referenceType => $group) {
            $type = StockReferenceType::tryFrom($referenceType);

            if ($type === null) {
                continue;
            }

            foreach ($type->modelClass()::query()->whereKey($group->pluck('reference_id'))->pluck('number', 'id') as $id => $number) {
                $numbers["{$referenceType}:{$id}"] = $number;
            }
        }

        return $numbers;
    }
}
