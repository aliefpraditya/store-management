<?php

namespace App\Services;

use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads an opname for the review screen: its items with the live system quantity and variance, and the totals.
 * The variance is worked out in SQL here ({@see self::varianceSql()}) with the same rule as
 * {@see StockOpname::varianceOf()}, which posting uses; change both together.
 *
 * @phpstan-type OpnameSummary array{items_count: int, counted_count: int, variance_count: int, variance_value: string}
 */
class StockOpnameReport
{
    public const ITEM_FILTER_UNCOUNTED = 'uncounted';

    public const ITEM_FILTER_VARIANCE = 'variance';

    /** Live stock until the opname is posted, then the quantity it was posted against. */
    private const SYSTEM_QUANTITY_SQL = 'coalesce(stock_opname_items.system_quantity_at_posting, stocks.quantity, 0)';

    /**
     * The items of an opname with `system_quantity` (live stock until posted, then the stored value),
     * `product_cost_price`, and the product's name and SKU for searching.
     *
     * @return Builder<StockOpnameItem>
     */
    public function itemsQuery(StockOpname $opname): Builder
    {
        return StockOpnameItem::query()
            ->where('stock_opname_items.stock_opname_id', $opname->id)
            ->join('products', 'products.id', '=', 'stock_opname_items.product_id')
            ->leftJoin('stocks', fn ($join) => $join
                ->on('stocks.product_id', '=', 'stock_opname_items.product_id')
                ->where('stocks.warehouse_id', '=', $opname->warehouse_id));
    }

    /**
     * @return LengthAwarePaginator<int, StockOpnameItem>
     */
    public function paginateItems(StockOpname $opname, ?string $search, ?string $filter, int $perPage): LengthAwarePaginator
    {
        $variance = $this->varianceSql($opname);

        $term = trim((string) $search);

        return $this->itemsQuery($opname)
            ->with('product.baseUnit', 'product.productUnits')
            ->select('stock_opname_items.*', 'products.cost_price as product_cost_price')
            ->selectRaw(self::SYSTEM_QUANTITY_SQL.' as system_quantity')
            ->selectRaw("{$variance} as variance_quantity")
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('products.name', 'like', "%{$term}%")
                ->orWhere('products.sku', 'like', "%{$term}%")))
            ->when($filter === self::ITEM_FILTER_UNCOUNTED, fn (Builder $query) => $query->whereNull('stock_opname_items.counted_quantity'))
            ->when($filter === self::ITEM_FILTER_VARIANCE, fn (Builder $query) => $query
                ->whereNotNull('stock_opname_items.counted_quantity')
                ->whereRaw("{$variance} != 0"))
            ->orderBy('products.name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The value of the variance is an estimate: variance × the product's current cost price (no FIFO in R1).
     *
     * @return OpnameSummary
     */
    public function summarize(StockOpname $opname): array
    {
        $variance = $this->varianceSql($opname);

        $totals = $this->itemsQuery($opname)->toBase()->selectRaw(
            "count(*) as items_count,
            count(stock_opname_items.counted_quantity) as counted_count,
            coalesce(sum(stock_opname_items.counted_quantity is not null and {$variance} != 0), 0) as variance_count,
            coalesce(sum({$variance} * products.cost_price), 0) as variance_value",
        )->first();

        return [
            'items_count' => (int) $totals->items_count,
            'counted_count' => (int) $totals->counted_count,
            'variance_count' => (int) $totals->variance_count,
            'variance_value' => (string) BigDecimal::of((string) $totals->variance_value)->toScale(2, RoundingMode::HalfUp),
        ];
    }

    /**
     * SQL for the variance of an item (null while it is not counted); the same rule as {@see StockOpname::varianceOf()}.
     */
    private function varianceSql(StockOpname $opname): string
    {
        return $opname->reason_code?->countsArrivals()
            ? 'stock_opname_items.counted_quantity'
            : '(stock_opname_items.counted_quantity - '.self::SYSTEM_QUANTITY_SQL.')';
    }
}
