<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\WarehouseType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Concerns\PaginatesReportRows;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * How the sellable stock of each product moved over a period, read from the stock ledger: what it was when the period
 * began, what sold, what came back by a voided receipt or a return into the shelf, what the stock counts changed, and
 * what it was when the period ended. Quantities are in the base unit of the product, signed as the ledger holds them (a
 * sale is negative), and `opening + sold + voided + returned + counted = closing` for every row.
 *
 * Only the sellable warehouses are read: dead stock and the returns warehouse are other stock. Goods that come back
 * into the returns warehouse reach the shelf through an inspection, which is a count and shows under `counted`, so
 * `returned` is only what a return put straight back on the shelf. Days are calendar days of the movement, as the stock
 * history has them, not business days. The units of different products cannot be added up, so only values are
 * totalled, at the last purchase price (an estimate, since R1 has no cost layers). Only products that moved in the
 * period are listed. Must run while the store's tenancy is initialized.
 *
 * @phpstan-import-type ReportPage from PaginatesReportRows
 */
class StockMovementReportService
{
    use PaginatesReportRows;

    /**
     * What the table can be ordered by, named as the columns of its rows, and the SQL that orders by it. The first is the
     * order the table starts in. Sales are negative in the ledger, so `sold_quantity` orders by how much was sold: the
     * biggest first means the most sold, not the least negative.
     */
    private const SORT_SQL = [
        'name' => 'products.name',
        'opening_quantity' => 'movements.opening_quantity',
        'sold_quantity' => '-movements.sold_quantity',
        'voided_quantity' => 'movements.voided_quantity',
        'returned_quantity' => 'movements.returned_quantity',
        'counted_quantity' => 'movements.counted_quantity',
        'closing_quantity' => 'movements.closing_quantity',
    ];

    /**
     * What the table can be ordered by.
     *
     * @return list<string>
     */
    public static function sorts(): array
    {
        return array_keys(self::SORT_SQL);
    }

    /**
     * The figures of the whole period: how many products moved, what went out through sales at cost (sales less what
     * came back by void or return) and what the counts changed at cost (a plus is stock found, a minus stock missing).
     *
     * @return array{products_count: int, sold_cost: string, counted_cost: string}
     */
    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $figures = $this->movedProducts($from, $to)->toBase()->selectRaw(<<<'SQL'
            count(*) as products_count,
            coalesce(sum(-(sold_quantity + voided_quantity + returned_quantity) * cost_price), 0) as sold_cost,
            coalesce(sum(counted_quantity * cost_price), 0) as counted_cost
        SQL)->first();

        return [
            'products_count' => (int) $figures->products_count,
            'sold_cost' => $this->money($figures->sold_cost),
            'counted_cost' => $this->money($figures->counted_cost),
        ];
    }

    /**
     * One page of the products that moved, in the order asked (`$sort` is one of {@see self::sorts()}, `$direction` `asc`
     * or `desc`; products that tie keep a stable order by name, so a page does not shuffle between requests), narrowed
     * by a search on the name, SKU or barcode and a category. A
     * page past the last one gives the last. Every product carries `opening_quantity`, `sold_quantity`,
     * `voided_quantity`, `returned_quantity`, `counted_quantity` and `closing_quantity`, with its category and brand
     * loaded.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function page(CarbonImmutable $from, CarbonImmutable $to, string $search, ?int $categoryId, string $sort, string $direction, int $page, int $perPage): LengthAwarePaginator
    {
        $products = $this->movedProducts($from, $to)
            ->select('products.*', 'movements.opening_quantity', 'movements.sold_quantity', 'movements.voided_quantity', 'movements.returned_quantity', 'movements.counted_quantity', 'movements.closing_quantity')
            ->search($search)
            ->when($categoryId, fn (Builder $query, int $categoryId) => $query->where('products.category_id', $categoryId))
            ->with(['category', 'brand'])
            ->orderByRaw(self::SORT_SQL[$sort].' '.($direction === 'desc' ? 'desc' : 'asc'))
            ->orderBy('products.name')
            ->orderBy('products.id');

        return $this->clampedPage($products, $page, $perPage);
    }

    /**
     * The products that had a movement in the sellable warehouses during the period, joined to their ledger figures as
     * `movements`. A product deleted since is still there: what moved is history.
     *
     * @return Builder<Product>
     */
    private function movedProducts(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $periodStart = $from->startOfDay();
        $sumOf = fn (string $types): string => "sum(case when stock_movements.moved_at >= ? and stock_movements.type in ({$types}) then stock_movements.quantity else 0 end)";

        $movements = StockMovement::query()->toBase()
            ->join('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')
            ->where('warehouses.type', WarehouseType::Sellable->value)
            ->where('stock_movements.moved_at', '<=', $to->endOfDay())
            ->groupBy('stock_movements.product_id')
            ->havingRaw('count(case when stock_movements.moved_at >= ? then 1 end) > 0', [$periodStart])
            ->selectRaw(
                'stock_movements.product_id as product_id, '
                .'sum(case when stock_movements.moved_at < ? then stock_movements.quantity else 0 end) as opening_quantity, '
                .$sumOf($this->typeList(StockMovementType::Sale)).' as sold_quantity, '
                .$sumOf($this->typeList(StockMovementType::SaleVoid)).' as voided_quantity, '
                .$sumOf($this->typeList(StockMovementType::SaleReturn)).' as returned_quantity, '
                .$sumOf($this->typeList(StockMovementType::OpeningBalance, StockMovementType::OpnameAdjustment)).' as counted_quantity, '
                .'sum(stock_movements.quantity) as closing_quantity',
                array_fill(0, 5, $periodStart),
            );

        return Product::query()->withTrashed()->joinSub($movements, 'movements', 'movements.product_id', '=', 'products.id');
    }

    /**
     * The movement types as a list for SQL's `in`: they are the enum's own fixed values, never typed by anyone.
     */
    private function typeList(StockMovementType ...$types): string
    {
        return implode(', ', array_map(fn (StockMovementType $type): string => "'{$type->value}'", $types));
    }

    private function money(BigDecimal|string|int|float|null $amount): string
    {
        return (string) BigDecimal::of((string) ($amount ?? 0))->toScale(2, RoundingMode::HalfUp);
    }
}
