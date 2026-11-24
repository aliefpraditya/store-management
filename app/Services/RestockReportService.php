<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Product;
use App\Services\Concerns\PaginatesReportRows;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * What to buy: the active products whose sellable stock will not last, with how many days it lasts at the pace it has
 * been selling and how many to buy so it does. The pace is what completed receipts sold over the last `$window` days (a
 * voided receipt is no sale, and what came back by return is not taken off) divided by those days. The stock wanted is
 * the larger of the product's minimum and the pace over `$cover` days, and the suggestion is what is missing from the
 * sellable stock (a negative balance counts as nothing there), rounded up to a whole base unit. A product is listed when
 * something is missing: it is under its minimum, or it would run out within `$cover` days. Damaged goods (DG) are left
 * out because they are not bought, and so are inactive products. A product that is out of stock and did not sell in the
 * window is not to buy either; when it has a minimum it is listed apart ({@see self::idleOutOfStockPage()}), and
 * the figures of the list do not count it.
 *
 * `days_left` is how many days the sellable stock lasts at that pace: 0 when it is out, `null` when the product did not
 * sell in the window (it is listed for its minimum then). The cost of the suggestion is at the last purchase price, an
 * estimate, and a product without a purchase price counts for nothing there, which is told. Money is returned as decimal
 * strings. Must run while the store's tenancy is initialized.
 *
 * @phpstan-import-type ReportPage from PaginatesReportRows
 */
class RestockReportService
{
    use PaginatesReportRows;

    /** How many days of sales set the pace, the first being the one it starts with. */
    public const WINDOW_OPTIONS = [30, 7, 14, 60, 90];

    /** How many days the stock should last, the first being the one it starts with. */
    public const COVER_OPTIONS = [14, 7, 30];

    /**
     * What the table can be ordered by, named as the columns of its rows, and the ORDER BY it takes (`%s` is the
     * direction). The first is the order the table starts in. A product that does not sell has no days left, and it goes
     * after the ones that do whichever way the table runs.
     */
    private const SORT_SQL = [
        'days_left' => 'days_left is null, days_left %s',
        'name' => 'products.name %s',
        'sellable_stock' => 'sellable_stock %s',
        'sold_quantity' => 'sold_quantity %s',
        'suggested_quantity' => 'suggested_quantity %s',
        'suggested_cost' => 'suggested_quantity * cost_price %s',
    ];

    /** How many rows a page of the products that are out and not selling has. */
    public const IDLE_OUT_OF_STOCK_PER_PAGE = 10;

    /** A product with nothing to sell (a negative balance counts as nothing) that did not sell in the window. */
    private const IS_IDLE_OUT_OF_STOCK_SQL = 'sellable_stock <= 0 and sold_quantity = 0';

    /** The columns a table starts in ascending order for: the most urgent, and names from A to Z. */
    private const ASCENDING_FIRST_SORTS = ['days_left', 'name'];

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
     * Whether a column starts in ascending order, the others starting with the biggest figure.
     */
    public static function startsAscending(string $sort): bool
    {
        return in_array($sort, self::ASCENDING_FIRST_SORTS, true);
    }

    /**
     * The figures of the list: how many products to buy, what that is estimated to cost, how many are out of stock
     * though they sell, how many are under their minimum, and how many have no purchase price (so the cost is short by
     * them).
     *
     * @return array{products_count: int, purchase_cost: string, out_of_stock_selling_count: int, below_minimum_count: int, products_without_cost: int}
     */
    public function summary(int $window, int $cover): array
    {
        $figures = $this->productsToBuy($window, $cover)->toBase()->selectRaw(<<<'SQL'
            count(*) as products_count,
            coalesce(sum(suggested_quantity * cost_price), 0) as purchase_cost,
            count(case when sellable_stock <= 0 and sold_quantity > 0 then 1 end) as out_of_stock_selling_count,
            count(case when is_below_minimum = 1 then 1 end) as below_minimum_count,
            count(case when cost_price = 0 then 1 end) as products_without_cost
        SQL)->first();

        return [
            'products_count' => (int) $figures->products_count,
            'purchase_cost' => (string) BigDecimal::of((string) $figures->purchase_cost)->toScale(2, RoundingMode::HalfUp),
            'out_of_stock_selling_count' => (int) $figures->out_of_stock_selling_count,
            'below_minimum_count' => (int) $figures->below_minimum_count,
            'products_without_cost' => (int) $figures->products_without_cost,
        ];
    }

    /**
     * The products that will run out soonest: of those that need buying and sell, the ones with the fewest days of stock
     * left, the soonest first. A product that is already out is left out (it has nothing left to run out of; the restock
     * table has it), and so is one that does not sell, which has no days left. `days_left` is a decimal string.
     *
     * @return list<array{id: int, name: string, days_left: string}>
     */
    public function runningOutSoonest(int $window, int $cover, int $limit): array
    {
        return $this->productsToBuy($window, $cover)
            ->where('sellable_stock', '>', 0)
            ->whereNotNull('days_left')
            ->orderBy('days_left')
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->limit($limit)
            ->get(['products.id', 'products.name', 'days_left'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'days_left' => (string) BigDecimal::of((string) $product->getAttribute('days_left'))->toScale(1, RoundingMode::HalfUp),
            ])
            ->all();
    }

    /**
     * One page of the products to buy, in the order asked (`$sort` is one of {@see self::sorts()}, `$direction` `asc` or
     * `desc`; products that tie keep a stable order by name, so a page does not shuffle between requests), narrowed to a
     * category when asked. A page past the last one gives the last. Every product carries `sellable_stock`,
     * `sold_quantity` (in the window), `days_left` and `suggested_quantity`, with its category, brand and units loaded.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function page(int $window, int $cover, ?int $categoryId, string $sort, string $direction, int $page, int $perPage): LengthAwarePaginator
    {
        $products = $this->productsToBuy($window, $cover)
            ->when($categoryId, fn (Builder $query, int $categoryId) => $query->where('products.category_id', $categoryId))
            ->with(['category', 'brand', 'productUnits'])
            ->orderByRaw(sprintf(self::SORT_SQL[$sort], $direction === 'desc' ? 'desc' : 'asc'))
            ->orderBy('products.name')
            ->orderBy('products.id');

        return $this->clampedPage($products, $page, $perPage);
    }

    /**
     * One page of the products that are out of stock and did not sell in the window, which only a minimum asks to buy
     * (`suggested_quantity` is that minimum), by name. They are kept apart from {@see self::page()} so that the cost and the
     * counts of what to buy are not swollen by products that may no longer be wanted: the store can look at them and decide
     * whether the minimum still holds. Carries the same columns as {@see self::page()}.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function idleOutOfStockPage(int $window, int $cover, ?int $categoryId, int $page, int $perPage): LengthAwarePaginator
    {
        $products = $this->productsWithSuggestion($window, $cover)
            ->whereRaw(self::IS_IDLE_OUT_OF_STOCK_SQL)
            ->when($categoryId, fn (Builder $query, int $categoryId) => $query->where('products.category_id', $categoryId))
            ->with(['category', 'brand', 'productUnits'])
            ->orderBy('products.name')
            ->orderBy('products.id');

        return $this->clampedPage($products, $page, $perPage);
    }

    /**
     * The products to buy: the ones that sell or still have stock. A product that is out and did not sell is left to
     * {@see self::idleOutOfStockPage()}.
     *
     * @return Builder<Product>
     */
    private function productsToBuy(int $window, int $cover): Builder
    {
        return $this->productsWithSuggestion($window, $cover)->whereRaw('not ('.self::IS_IDLE_OUT_OF_STOCK_SQL.')');
    }

    /**
     * The products that need buying, selling or not, as a table named `products` so the filters and totals can read the
     * stock columns, `sold_quantity`, `days_left` and `suggested_quantity` like any other column. Built in layers because
     * a figure cannot be read in the query that works it out.
     *
     * @return Builder<Product>
     */
    private function productsWithSuggestion(int $window, int $cover): Builder
    {
        $periodStart = CarbonImmutable::today()->subDays($window - 1);

        $withSold = Product::query()
            ->withStockTotals()
            ->selectRaw('(select coalesce(sum(sale_items.base_quantity), 0) from sale_items join sales on sales.id = sale_items.sale_id where sale_items.product_id = products.id and sales.status = ? and sales.business_date >= ?) as sold_quantity', [SaleStatus::Completed->value, $periodStart->toDateString()])
            ->where('products.is_active', true)
            ->where('products.is_damaged_goods', false);

        $withSuggestion = Product::query()
            ->fromSub($withSold, 'products')
            ->select('products.*')
            ->selectRaw(<<<'SQL'
                ceil(greatest(min_stock, sold_quantity * ? / ?) - greatest(sellable_stock, 0)) as suggested_quantity,
                case when sellable_stock <= 0 then 0 when sold_quantity > 0 then round(sellable_stock * ? / sold_quantity, 1) end as days_left
            SQL, [$cover, $window, $window]);

        return Product::query()->fromSub($withSuggestion, 'products')->where('suggested_quantity', '>', 0);
    }
}
