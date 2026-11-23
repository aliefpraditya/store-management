<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Concerns\PaginatesReportRows;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The sellable stock that does not sell: active products that hold stock but had no completed sale for a number of days,
 * with the capital (stock × last purchase price) they tie up. A product that has never been sold is listed too, since
 * stock that nobody has bought yet is what this report is after; the summary says how far back the sales go, so the
 * reader can tell a short history from a real lack of sales. Only stock in the sellable warehouses counts: dead stock
 * and returns waiting are other reports' business, and an inactive product is no longer for sale, so it cannot be "not
 * selling". Voided receipts are no sales, and neither are stock opnames, so counting the stock never makes a product
 * "move". Returns are not taken off a sale, so a product sold and returned the same week still counts as sold. Money is
 * returned as decimal strings. Must run while the store's tenancy is initialized.
 *
 * @phpstan-import-type ReportPage from PaginatesReportRows
 */
class IdleStockService
{
    use PaginatesReportRows;

    /** How long without a sale makes stock idle, in days; the page offers these. */
    public const DAYS_OPTIONS = [30, 60, 90, 180];

    public const DEFAULT_DAYS = 60;

    /**
     * What the idle stock comes to: how many products and what they are worth, the share of all the sellable stock's
     * value that is, how many of them were never sold, how many have no purchase price (so the value is short by them),
     * and how far back the store's sales go: the business day of its first completed sale and the days from then to today
     * (both `null` while nothing was sold), for telling whether the chosen number of days is covered by the history.
     *
     * @return array{products_count: int, stock_value: string, share_percent: string, never_sold_count: int, products_without_cost: int, first_sale_on: ?string, history_days: ?int}
     */
    public function summary(int $days): array
    {
        $totalValue = BigDecimal::of((string) $this->stockedProducts()->toBase()->selectRaw('coalesce(sum(sellable_stock * cost_price), 0) as stock_value')->value('stock_value'));

        $idle = $this->idleProducts($days)->toBase()->selectRaw(<<<'SQL'
            count(*) as products_count,
            coalesce(sum(sellable_stock * cost_price), 0) as stock_value,
            count(case when last_sold_on is null then 1 end) as never_sold_count,
            count(case when cost_price = 0 then 1 end) as products_without_cost
        SQL)->first();

        $idleValue = BigDecimal::of((string) $idle->stock_value);
        $firstSaleOn = Sale::query()->where('status', SaleStatus::Completed)->min('business_date');

        return [
            'products_count' => (int) $idle->products_count,
            'stock_value' => (string) $idleValue->toScale(2, RoundingMode::HalfUp),
            'share_percent' => $totalValue->isPositive() ? (string) $idleValue->multipliedBy(100)->dividedBy($totalValue, 1, RoundingMode::HalfUp) : '0.0',
            'never_sold_count' => (int) $idle->never_sold_count,
            'products_without_cost' => (int) $idle->products_without_cost,
            'first_sale_on' => $firstSaleOn === null ? null : CarbonImmutable::parse($firstSaleOn)->toDateString(),
            'history_days' => $firstSaleOn === null ? null : CarbonImmutable::parse($firstSaleOn)->startOfDay()->diffInDays(CarbonImmutable::today()) + 1,
        ];
    }

    /**
     * One page of the idle products, the ones that tie up the most capital first, narrowed to a category when asked. A
     * page past the last one gives the last. Every product carries `sellable_stock`, `last_sold_on` (`null` when it never
     * sold) and the sealed pack counts for showing its stock, with its category, brand and units loaded.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function page(int $days, ?int $categoryId, int $page, int $perPage): LengthAwarePaginator
    {
        $products = $this->idleProducts($days)
            ->when($categoryId, fn (Builder $query, int $categoryId) => $query->where('products.category_id', $categoryId))
            ->with(['category', 'brand', 'productUnits'])
            ->orderByRaw('sellable_stock * cost_price desc')
            ->orderBy('products.name')
            ->orderBy('products.id');

        return $this->clampedPage($products, $page, $perPage);
    }

    /**
     * @return Builder<Product>
     */
    private function idleProducts(int $days): Builder
    {
        $periodStart = CarbonImmutable::today()->subDays($days - 1);

        return $this->stockedProducts()->where(fn (Builder $products) => $products
            ->whereNull('last_sold_on')
            ->orWhere('last_sold_on', '<', $periodStart->toDateString()));
    }

    /**
     * The active products with sellable stock, as a table named `products` so the filters and totals can read the stock
     * columns and `last_sold_on` (the business day of the last completed sale, whenever it was) like any other column.
     *
     * @return Builder<Product>
     */
    private function stockedProducts(): Builder
    {
        $withLastSale = Product::query()
            ->withStockTotals()
            ->selectRaw('(select max(sales.business_date) from sale_items join sales on sales.id = sale_items.sale_id where sale_items.product_id = products.id and sales.status = ?) as last_sold_on', [SaleStatus::Completed->value])
            ->where('products.is_active', true);

        return Product::query()->fromSub($withLastSale, 'products')->where('sellable_stock', '>', 0);
    }
}
