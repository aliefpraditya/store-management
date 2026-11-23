<?php

namespace App\Services;

use App\Enums\SalesGrouping;
use App\Enums\StockStatusFilter;
use App\Models\Product;
use App\Services\Concerns\PaginatesReportRows;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
 * up, so only values are totalled.
 *
 * @phpstan-import-type ReportPage from PaginatesReportRows
 */
class StockReportService
{
    use PaginatesReportRows;

    /** How many products the table may show per page, the first being the one it starts with. */
    public const PER_PAGE_OPTIONS = [15, 30, 50];

    /** The page sizes of the table per category or brand, which is a shorter list. */
    public const GROUP_PER_PAGE_OPTIONS = [5, 10];

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

    /**
     * The headline figures: how many products are sold, how many are low, out or below zero, what the sellable stock is
     * worth, what sits in dead stock and in the returns warehouse, and how many products with stock have no purchase
     * price (so the value is short by them).
     *
     * @return array{products_count: int, below_minimum_count: int, out_of_stock_count: int, negative_count: int, sellable_value: string, dead_stock: array{products_count: int, value: string}, returns_waiting: array{products_count: int, value: string}, products_without_cost: int}
     */
    public function summary(): array
    {
        $figures = $this->stockedProducts()->toBase()->selectRaw(<<<'SQL'
            count(case when is_active = 1 then 1 end) as products_count,
            count(case when is_active = 1 and is_below_minimum = 1 then 1 end) as below_minimum_count,
            count(case when is_active = 1 and sellable_stock = 0 then 1 end) as out_of_stock_count,
            count(case when has_negative_stock = 1 then 1 end) as negative_count,
            coalesce(sum(greatest(sellable_stock, 0) * cost_price), 0) as sellable_value,
            count(case when dead_stock > 0 then 1 end) as dead_stock_count,
            coalesce(sum(greatest(dead_stock, 0) * cost_price), 0) as dead_stock_value,
            count(case when returns_stock > 0 then 1 end) as returns_waiting_count,
            coalesce(sum(greatest(returns_stock, 0) * cost_price), 0) as returns_waiting_value,
            count(case when cost_price = 0 and (sellable_stock > 0 or dead_stock > 0 or returns_stock > 0) then 1 end) as products_without_cost
        SQL)->first();

        return [
            'products_count' => (int) $figures->products_count,
            'below_minimum_count' => (int) $figures->below_minimum_count,
            'out_of_stock_count' => (int) $figures->out_of_stock_count,
            'negative_count' => (int) $figures->negative_count,
            'sellable_value' => $this->money($figures->sellable_value),
            'dead_stock' => ['products_count' => (int) $figures->dead_stock_count, 'value' => $this->money($figures->dead_stock_value)],
            'returns_waiting' => ['products_count' => (int) $figures->returns_waiting_count, 'value' => $this->money($figures->returns_waiting_value)],
            'products_without_cost' => (int) $figures->products_without_cost,
        ];
    }

    /**
     * One page of what the sellable stock is worth per category or per brand, the most valuable first. Products
     * without a category or brand are one group of their own. A page past the last one gives the last.
     *
     * @return array{data: list<array{id: int|null, name: string, products_count: int, value: string, share_percent: string}>, grouping: string}&ReportPage
     */
    public function groupsPage(SalesGrouping $grouping, int $page, int $perPage): array
    {
        $column = $grouping->productColumn();
        $groupTable = match ($grouping) {
            SalesGrouping::Category => 'categories',
            SalesGrouping::Brand => 'brands',
        };
        $totalValue = BigDecimal::of((string) $this->stockedProducts()->toBase()->selectRaw(self::SELLABLE_VALUE_SQL.' as total_value')->value('total_value'));

        $groups = $this->stockedProducts()->toBase()
            ->leftJoin($groupTable, "{$groupTable}.id", '=', "products.{$column}")
            ->groupBy("products.{$column}", "{$groupTable}.name")
            ->selectRaw("products.{$column} as group_id, {$groupTable}.name as group_name, count(*) as products_count, ".self::SELLABLE_VALUE_SQL.' as stock_value')
            ->orderByDesc('stock_value')
            ->orderBy("{$groupTable}.name");

        $paginator = $this->clampedPage($groups, $page, $perPage);

        return [
            'data' => $paginator->getCollection()->map(function (object $group) use ($grouping, $totalValue): array {
                $value = BigDecimal::of((string) $group->stock_value);

                return [
                    'id' => $group->group_id === null ? null : (int) $group->group_id,
                    'name' => $group->group_name ?? $grouping->emptyLabel(),
                    'products_count' => (int) $group->products_count,
                    'value' => $this->money($value),
                    'share_percent' => $totalValue->isPositive() ? (string) $value->multipliedBy(100)->dividedBy($totalValue, 1, RoundingMode::HalfUp) : '0.0',
                ];
            })->values()->all(),
            'grouping' => $grouping->value,
            ...$this->pageOf($paginator),
        ];
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

    private function money(BigDecimal|string|int|float|null $amount): string
    {
        return (string) BigDecimal::of((string) ($amount ?? 0))->toScale(2, RoundingMode::HalfUp);
    }
}
