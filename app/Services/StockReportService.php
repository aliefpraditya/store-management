<?php

namespace App\Services;

use App\Enums\SalesGrouping;
use App\Enums\SaleStatus;
use App\Enums\StockGroupMetric;
use App\Enums\StockStatusFilter;
use App\Models\Product;
use App\Services\Concerns\PaginatesReportRows;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The stock the store holds now, in the three kinds of warehouse (sellable, dead stock, and the returns warehouse where
 * returned goods wait to be inspected), valued at the last purchase price (R1 has no cost layers yet, so the value is an
 * estimate; a product without a purchase price counts for nothing, which is told). Money is returned as decimal strings.
 * Must run while the store's tenancy is initialized.
 *
 * The report covers the active products and the inactive ones that still hold stock, since goods that can no longer
 * be sold are still on the shelf. Only what is in stock is valued: a negative balance is a mistake to find (counted
 * apart as `negative_count`), not a debt that takes value off the rest. The units of different products cannot be added
 * up, so only values are totalled; a quantity is only ever added up within one base unit.
 *
 * @phpstan-import-type ReportPage from PaginatesReportRows
 */
class StockReportService
{
    use PaginatesReportRows;

    /** How many products the table may show per page, the first being the one it starts with. */
    public const PER_PAGE_OPTIONS = [15, 30, 50];

    /** How many categories or brands the summary charts by name, the rest being folded into one part. */
    public const SUMMARY_TOP_GROUPS = 5;

    /**
     * What the table of products can be ordered by, named as the columns of its rows, and the SQL that orders by it. The
     * first is the order the table starts in. A negative balance is worth nothing in the value, as in the totals.
     */
    private const POSITION_SORT_SQL = [
        'name' => 'products.name',
        'sellable_stock' => 'sellable_stock',
        'dead_stock' => 'dead_stock',
        'returns_stock' => 'returns_stock',
        'cost_price' => 'cost_price',
        'sellable_value' => 'greatest(sellable_stock, 0) * cost_price',
    ];

    private const HAS_STOCK_SQL = 'exists (select 1 from stocks where stocks.product_id = products.id and stocks.quantity != 0)';

    private const HAS_NEGATIVE_STOCK_SQL = '(exists (select 1 from stocks where stocks.product_id = products.id and stocks.quantity < 0))';

    /** What the sellable stock of a set of products is worth: the quantity of each that is there, at its purchase price. */
    private const SELLABLE_VALUE_SQL = 'coalesce(sum(greatest(sellable_stock, 0) * cost_price), 0)';

    /** How many of a set of products have anything to sell: a product that is out, or at a negative balance, is not counted. */
    private const IN_STOCK_COUNT_SQL = 'count(case when sellable_stock > 0 then 1 end)';

    /** How much sellable stock a set of products holds, in their base units: a negative balance counts for nothing. */
    private const IN_STOCK_QUANTITY_SQL = 'coalesce(sum(greatest(sellable_stock, 0)), 0)';

    /**
     * The headline figures: how many products are sold, how many are low, out or below zero, what the sellable stock is
     * worth, what sits in dead stock and in the returns warehouse, and how many products with stock have no purchase
     * price (so the value is short by them).
     *
     * The active products also come split into three that do not overlap, for a chart of the whole: `out_of_stock_count`
     * (nothing left), `low_stock_count` (under the minimum but not out; a minimum of 0 is never low) and `healthy_count`
     * (the rest). `below_minimum_count` is not the sum of the first two, since a product out of stock with no minimum is
     * out but not below it. A product with a negative balance sits in whichever of the three its sellable stock and minimum
     * say; it is counted apart as `negative_count`.
     *
     * `damaged_goods` is the part of the sellable stock that is damaged goods (DG products, sold at a lower price): they sit
     * in the sellable warehouse, so `sellable_value` includes them, and a chart of where the money is takes them out of it.
     *
     * @return array{products_count: int, below_minimum_count: int, out_of_stock_count: int, low_stock_count: int, healthy_count: int, negative_count: int, sellable_value: string, damaged_goods: array{products_count: int, value: string}, dead_stock: array{products_count: int, value: string}, returns_waiting: array{products_count: int, value: string}, products_without_cost: int}
     */
    public function summary(): array
    {
        $figures = $this->stockedProducts()->toBase()->selectRaw(<<<'SQL'
            count(case when is_active = 1 then 1 end) as products_count,
            count(case when is_active = 1 and is_below_minimum = 1 then 1 end) as below_minimum_count,
            count(case when is_active = 1 and sellable_stock = 0 then 1 end) as out_of_stock_count,
            count(case when is_active = 1 and sellable_stock != 0 and is_below_minimum = 1 then 1 end) as low_stock_count,
            count(case when has_negative_stock = 1 then 1 end) as negative_count,
            coalesce(sum(greatest(sellable_stock, 0) * cost_price), 0) as sellable_value,
            count(case when is_damaged_goods = 1 and sellable_stock > 0 then 1 end) as damaged_goods_count,
            coalesce(sum(case when is_damaged_goods = 1 then greatest(sellable_stock, 0) * cost_price end), 0) as damaged_goods_value,
            count(case when dead_stock > 0 then 1 end) as dead_stock_count,
            coalesce(sum(greatest(dead_stock, 0) * cost_price), 0) as dead_stock_value,
            count(case when returns_stock > 0 then 1 end) as returns_waiting_count,
            coalesce(sum(greatest(returns_stock, 0) * cost_price), 0) as returns_waiting_value,
            count(case when cost_price = 0 and (sellable_stock > 0 or dead_stock > 0 or returns_stock > 0) then 1 end) as products_without_cost
        SQL)->first();

        $productsCount = (int) $figures->products_count;
        $outOfStockCount = (int) $figures->out_of_stock_count;
        $lowStockCount = (int) $figures->low_stock_count;

        return [
            'products_count' => $productsCount,
            'below_minimum_count' => (int) $figures->below_minimum_count,
            'out_of_stock_count' => $outOfStockCount,
            'low_stock_count' => $lowStockCount,
            'healthy_count' => $productsCount - $outOfStockCount - $lowStockCount,
            'negative_count' => (int) $figures->negative_count,
            'sellable_value' => $this->money($figures->sellable_value),
            'damaged_goods' => ['products_count' => (int) $figures->damaged_goods_count, 'value' => $this->money($figures->damaged_goods_value)],
            'dead_stock' => ['products_count' => (int) $figures->dead_stock_count, 'value' => $this->money($figures->dead_stock_value)],
            'returns_waiting' => ['products_count' => (int) $figures->returns_waiting_count, 'value' => $this->money($figures->returns_waiting_value)],
            'products_without_cost' => (int) $figures->products_without_cost,
        ];
    }

    /**
     * The base units that sellable stock is held in, the one most products have stock in first: what a quantity can be asked
     * for, since quantities only add up within one unit.
     *
     * @return list<string> Unit codes.
     */
    public function stockUnits(): array
    {
        return $this->stockedProducts()->toBase()
            ->selectRaw('base_unit_code, '.self::IN_STOCK_COUNT_SQL.' as in_stock_count')
            ->groupBy('base_unit_code')
            ->having('in_stock_count', '>', 0)
            ->orderByDesc('in_stock_count')
            ->orderBy('base_unit_code')
            ->pluck('base_unit_code')
            ->all();
    }

    /**
     * One page of the sellable stock per category or per brand, the biggest first by the measure asked for: what it is
     * worth, or how much of it there is. A quantity is only counted in one base unit, `$unitCode` (the unit most products
     * are held in when it is not one of {@see self::stockUnits()}), since kilograms, sacks and pieces cannot be added up;
     * the groups of a quantity then hold only the products of that unit, and their value is that of those products. The
     * value is of every product when it is the measure, and then the quantities are `null`, since they would add up
     * different units. Each group carries both figures, and `share_percent` is its share of the measure it is ranked by. Products without a category or brand are one group of their own. A page past the
     * last one gives the last. `value_total` and `quantity_total` are the figures of every group, not only of the page, for
     * a chart that folds the groups off the page into one.
     *
     * @return array{data: list<array{id: int|null, name: string, in_stock_quantity: string|null, value: string, share_percent: string}>, grouping: string, metric: string, unit: string|null, units: list<string>, value_total: string, quantity_total: string|null}&ReportPage
     */
    public function groupsPage(SalesGrouping $grouping, StockGroupMetric $metric, ?string $unitCode, int $page, int $perPage): array
    {
        $column = $grouping->productColumn();
        $groupTable = match ($grouping) {
            SalesGrouping::Category => 'categories',
            SalesGrouping::Brand => 'brands',
        };
        $units = $this->stockUnits();
        $unit = $this->unitOrMostUsed($unitCode, $units);
        $isInOneUnit = $metric === StockGroupMetric::Quantity && $unit !== null;
        $products = fn () => $this->stockedProducts()->toBase()->when($isInOneUnit, fn ($query) => $query->where('products.base_unit_code', $unit));

        $totals = $products()->selectRaw(self::SELLABLE_VALUE_SQL.' as total_value, '.self::IN_STOCK_QUANTITY_SQL.' as total_quantity')->first();
        $totalValue = BigDecimal::of((string) $totals->total_value);
        $totalQuantity = BigDecimal::of((string) $totals->total_quantity);

        $groups = $products()
            ->leftJoin($groupTable, "{$groupTable}.id", '=', "products.{$column}")
            ->groupBy("products.{$column}", "{$groupTable}.name")
            ->selectRaw("products.{$column} as group_id, {$groupTable}.name as group_name, ".self::IN_STOCK_QUANTITY_SQL.' as in_stock_quantity, '.self::SELLABLE_VALUE_SQL.' as stock_value')
            ->orderByDesc($metric->column())
            ->orderBy("{$groupTable}.name");

        $paginator = $this->clampedPage($groups, $page, $perPage);
        $total = $metric === StockGroupMetric::Value ? $totalValue : $totalQuantity;

        return [
            'data' => $paginator->getCollection()->map(function (object $group) use ($grouping, $metric, $total, $isInOneUnit): array {
                $measure = BigDecimal::of((string) $group->{$metric->column()});

                return [
                    'id' => $group->group_id === null ? null : (int) $group->group_id,
                    'name' => $group->group_name ?? $grouping->emptyLabel(),
                    'in_stock_quantity' => $isInOneUnit ? $this->quantity($group->in_stock_quantity) : null,
                    'value' => $this->money($group->stock_value),
                    'share_percent' => $total->isPositive() ? (string) $measure->multipliedBy(100)->dividedBy($total, 1, RoundingMode::HalfUp) : '0.0',
                ];
            })->values()->all(),
            'grouping' => $grouping->value,
            'metric' => $metric->value,
            'unit' => $unit,
            'units' => $units,
            'value_total' => $this->money($totalValue),
            'quantity_total' => $isInOneUnit ? $this->quantity($totalQuantity) : null,
            ...$this->pageOf($paginator),
        ];
    }

    /**
     * The products sold the most in the last `$window` days, in `$unitCode` (the unit most products are held in when it is not
     * one of {@see self::stockUnits()}), the biggest quantity first. Only a quantity in one unit can be compared, so the
     * other units are left out. Completed receipts only, and returns are not taken off.
     *
     * @return array{data: list<array{id: int, name: string, sold_quantity: string}>, unit: string|null, units: list<string>}
     */
    public function bestSellers(int $window, ?string $unitCode, int $limit): array
    {
        $units = $this->stockUnits();
        $unit = $this->unitOrMostUsed($unitCode, $units);

        $sold = $unit === null ? collect() : Product::query()->toBase()
            ->join('sale_items', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', SaleStatus::Completed->value)
            ->where('sales.business_date', '>=', CarbonImmutable::today()->subDays($window - 1)->toDateString())
            ->where('products.base_unit_code', $unit)
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.id, products.name, sum(sale_items.base_quantity) as sold_quantity')
            ->orderByDesc('sold_quantity')
            ->orderBy('products.name')
            ->limit($limit)
            ->get();

        return [
            'data' => $sold->map(fn (object $product): array => ['id' => (int) $product->id, 'name' => $product->name, 'sold_quantity' => $this->quantity($product->sold_quantity)])->values()->all(),
            'unit' => $unit,
            'units' => $units,
        ];
    }

    /**
     * @param  list<string>  $units
     */
    private function unitOrMostUsed(?string $unitCode, array $units): ?string
    {
        return in_array($unitCode, $units, true) ? $unitCode : ($units[0] ?? null);
    }

    /**
     * What the table of products can be ordered by.
     *
     * @return list<string>
     */
    public static function positionSorts(): array
    {
        return array_keys(self::POSITION_SORT_SQL);
    }

    /**
     * One page of the products with their stock per kind of warehouse, in the order asked (`$sort` is one of
     * {@see self::positionSorts()}, `$direction` `asc` or `desc`; products that tie keep a stable order by name, so a page
     * does not shuffle between requests), narrowed by a search on the name or
     * SKU, a category, a brand and a stock condition. A page past the last one gives the last. Every product carries
     * `sellable_stock`, `dead_stock`, `returns_stock`, `is_below_minimum` and `has_negative_stock`
     * (see {@see Product::scopeWithStockTotals()}), with its category, brand and units loaded.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function positionsPage(string $search, ?int $categoryId, ?int $brandId, ?StockStatusFilter $status, string $sort, string $direction, int $page, int $perPage): LengthAwarePaginator
    {
        $products = $this->stockedProducts()
            ->search($search)
            ->when($categoryId, fn (Builder $query, int $categoryId) => $query->where('products.category_id', $categoryId))
            ->when($brandId, fn (Builder $query, int $brandId) => $query->where('products.brand_id', $brandId))
            ->when($status, fn (Builder $query, StockStatusFilter $status) => $status->narrow($query))
            ->with(['category', 'brand', 'productUnits'])
            ->orderByRaw(self::POSITION_SORT_SQL[$sort].' '.($direction === 'desc' ? 'desc' : 'asc'))
            ->orderBy('products.name')
            ->orderBy('products.id');

        return $this->clampedPage($products, $page, $perPage);
    }

    /**
     * The products the report is about, each with its stock per kind of warehouse, as a table named `products` so the
     * filters and totals can read the stock columns like any other column: the active ones, and the inactive ones that
     * still hold stock.
     *
     * @return Builder<Product>
     */
    private function stockedProducts(): Builder
    {
        $withStock = Product::query()
            ->withStockTotals()
            ->selectRaw(self::HAS_NEGATIVE_STOCK_SQL.' as has_negative_stock')
            ->where(fn (Builder $products) => $products->where('products.is_active', true)->orWhereRaw(self::HAS_STOCK_SQL));

        return Product::query()->fromSub($withStock, 'products');
    }

    private function quantity(BigDecimal|string|int|float|null $quantity): string
    {
        return (string) BigDecimal::of((string) ($quantity ?? 0))->toScale(3, RoundingMode::HalfUp);
    }

    private function money(BigDecimal|string|int|float|null $amount): string
    {
        return (string) BigDecimal::of((string) ($amount ?? 0))->toScale(2, RoundingMode::HalfUp);
    }
}
