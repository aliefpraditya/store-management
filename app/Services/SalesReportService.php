<?php

namespace App\Services;

use App\Enums\DeliveryNoteStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleReturnStatus;
use App\Enums\SalesGrouping;
use App\Enums\SaleStatus;
use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameStatus;
use App\Enums\WarehouseType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Stock;
use App\Models\StockOpnameItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * The sales report of a period: what was sold, net of what came back as returns, how it was paid, who sold it, and the
 * estimated gross profit per product. Money is returned as decimal strings. Must run while the store's tenancy is
 * initialized.
 *
 * Every figure belongs to a business day, the same one the day closing books it on: a sale and a return to the business
 * day of the shift they were made in (a shift that runs past midnight keeps its opening day), and a void to the business
 * day of the shift of the one who voided it, or to the day it was done when that person has no shift. A receipt sold on
 * one day and voided on a later one is counted in the sales of the first day and in the voids of the second, never twice
 * and never in neither). The cost of an item is
 * the last purchase price when it was sold (R1 has no cost layers yet), so profit is an estimate: an item without a
 * purchase price has no cost, which is told. Returned goods wait in the returns warehouse until they are inspected, so
 * a return takes back the whole profit of what came back. What is found unsellable afterwards and sent to dead stock is
 * a loss of its cost, taken off the profit on the day the opname is posted (`dead_stock_loss`, also added to the cost of
 * the product in the product rows). Goods sent to a DG product are not counted then: they are sold later under their own
 * product at its price, and a price under the cost shows as a lower profit when it is sold, so counting it here would
 * count it twice. Voided receipts are left out of the sales and the methods. A COD sale counts when it is made, whether or not the
 * goods have been delivered.
 *
 * The figures of the methods are of the sales, before returns: returns are not tied to the method the sale was paid by.
 * The report has no figures per cashier: what sells says more about the goods than about who rang them up.
 *
 * @phpstan-type ReportTotals array{gross_total: string, returns_total: string, net_total: string, receipt_count: int, dead_stock_loss: string, gross_profit: string, voids_count: int, voids_total: string, margin_percent: string}
 * @phpstan-type ReportDay array{date: string, gross_total: string, returns_total: string, net_total: string, receipt_count: int, dead_stock_loss: string, gross_profit: string, voids_count: int, voids_total: string}
 * @phpstan-type ReportProduct array{product_id: int, name: string, unit: string, quantity: string, revenue: string, cost: string, profit: string, margin_percent: string}
 * @phpstan-type SalesReport array{from: string, to: string, totals: ReportTotals, days: list<ReportDay>, methods: list<array{method: string, label: string, amount: string}>, products: list<ReportProduct>, product_count: int, product_limit: int, products_without_cost: int}
 * @phpstan-type ReportPeriod array{from: CarbonImmutable, to: CarbonImmutable, is_cut_back: bool}
 */
class SalesReportService
{
    /** The most days of one report, so a mistyped date cannot ask for years of rows. */
    public const MAX_DAYS = 366;

    private const PRODUCT_LIMIT = 30;

    /** How long the lists of returns are, since they are for what stands out. */
    private const RETURN_LIST_LIMIT = 5;

    /** How many rows a table of the screen may show per page, the first being the one it starts with. */
    public const PER_PAGE_OPTIONS = [7, 15, 30];

    /** The page sizes of the table per category or brand, which is a shorter list. */
    public const GROUP_PER_PAGE_OPTIONS = [5, 10];

    /** What the table per category or brand can be ordered by, the first being the order it starts in. */
    public const GROUP_SORTS = ['revenue', 'profit', 'margin', 'name'];

    /**
     * The period asked for, made one the report can give. A period needs both its days: with either missing the report
     * is of the whole of this month (its first to its last day, so the chart shows the month filling up). A start after
     * the end is swapped, and a period longer than {@see self::MAX_DAYS} (a year) is cut back from its start
     * (`is_cut_back` says so, for the page to tell).
     *
     * @return ReportPeriod
     */
    public function period(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        if ($from === null || $to === null) {
            return ['from' => CarbonImmutable::today()->startOfMonth(), 'to' => CarbonImmutable::today()->endOfMonth()->startOfDay(), 'is_cut_back' => false];
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $earliest = $to->subDays(self::MAX_DAYS - 1);

        return ['from' => $from->lessThan($earliest) ? $earliest : $from, 'to' => $to, 'is_cut_back' => $from->lessThan($earliest)];
    }

    /**
     * The whole report in one piece, for the print sheet: the top {@see self::PRODUCT_LIMIT} products only, so a sheet
     * stays a few pages. The screen asks for the same figures in parts ({@see self::summary()},
     * {@see self::comparison()}, {@see self::payments()}, {@see self::productsPage()}, {@see self::groupsPage()}) so
     * each can load on its own.
     *
     * @return SalesReport
     */
    public function report(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $productRows = $this->productRows($from, $to);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            ...$this->summary($from, $to),
            ...$this->payments($from, $to),
            'products' => $productRows->take(self::PRODUCT_LIMIT)->values()->all(),
            'product_count' => $productRows->count(),
            'product_limit' => self::PRODUCT_LIMIT,
        ];
    }

    /**
     * The totals, the figures per day, what a typical receipt is (see {@see self::receiptMedians()}), and how many
     * sold products have no purchase price (their profit equals their sales, which the page warns about next to the
     * profit).
     *
     * @return array{totals: ReportTotals, days: list<ReportDay>, receipts: array{median_total: string, median_lines: string, average_total: string}, products_without_cost: int}
     */
    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = $this->days($from, $to);
        $totals = $this->totals($days);

        return [
            'totals' => $totals,
            'days' => $days,
            'receipts' => [...$this->receiptMedians($from, $to), 'average_total' => $this->averageReceiptTotal($totals)],
            'products_without_cost' => $this->productsWithoutCostCount($from, $to),
        ];
    }

    /**
     * The mean receipt, shown beside the median: the sales divided by the receipts, so the gap between the two says how
     * much a few big receipts weigh in the sales.
     *
     * @param  ReportTotals  $totals
     */
    private function averageReceiptTotal(array $totals): string
    {
        if ($totals['receipt_count'] === 0) {
            return $this->money(0);
        }

        return $this->money(BigDecimal::of($totals['gross_total'])->dividedBy($totals['receipt_count'], 2, RoundingMode::HalfUp));
    }

    /**
     * What a typical receipt of the period is: the median of its total and of how many different products it carries.
     * The median and not the mean, because a few big receipts (a contractor buying by the dozen) would pull a mean far
     * from what most customers buy. With an even number of receipts it is the mean of the two in the middle.
     *
     * @return array{median_total: string, median_lines: string}
     */
    private function receiptMedians(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = $this->salesQuery($from, $to)->toBase()->selectRaw('total as median_input');
        $lines = $this->soldItemsQuery($from, $to)->toBase()->groupBy('sale_items.sale_id')->selectRaw('count(*) as median_input');

        return [
            'median_total' => $this->money($this->median($totals)),
            'median_lines' => (string) BigDecimal::of((string) ($this->median($lines) ?? 0))->toScale(1, RoundingMode::HalfUp),
        ];
    }

    /**
     * The median of the `median_input` column of a query, worked out by the database: MySQL has no median, so the rows
     * are ranked and the one in the middle (the two, when the count is even) is averaged.
     *
     * @param  QueryBuilder  $values  Selects one column, aliased `median_input`, a row per value.
     */
    private function median(QueryBuilder $values): ?string
    {
        $connection = $values->getConnection();

        $ranked = $connection->query()
            ->fromSub($values, 'median_values')
            ->selectRaw('median_input, row_number() over (order by median_input) as row_rank, count(*) over () as row_count');

        $median = $connection->query()
            ->fromSub($ranked, 'median_ranked')
            ->whereRaw('row_rank in (floor((row_count + 1) / 2), floor((row_count + 2) / 2))')
            ->avg('median_input');

        return $median === null ? null : (string) $median;
    }

    /**
     * @return array{methods: list<array{method: string, label: string, amount: string}>}
     */
    public function payments(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return ['methods' => $this->methods($from, $to)];
    }

    /**
     * The period before this one, as long as the days of this one that have come (a month that is half over is set
     * against the half-month before it, not a whole one), and how the net sales, the receipts and the profit moved
     * against it, in percent of what they were (`null` when they were nothing, so there is nothing to move from).
     *
     * @return array{from: string, to: string, totals: ReportTotals, change_percent: array{net_total: string|null, receipt_count: string|null, gross_profit: string|null}}
     */
    public function comparison(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $previousFrom = $from->subDays($this->elapsedDays($from, $to));
        $previousTo = $from->subDay();
        $current = $this->totals($this->days($from, $to));
        $previous = $this->totals($this->days($previousFrom, $previousTo));

        return [
            'from' => $previousFrom->toDateString(),
            'to' => $previousTo->toDateString(),
            'totals' => $previous,
            'change_percent' => [
                'net_total' => $this->changePercent($current['net_total'], $previous['net_total']),
                'receipt_count' => $this->changePercent($current['receipt_count'], $previous['receipt_count']),
                'gross_profit' => $this->changePercent($current['gross_profit'], $previous['gross_profit']),
            ],
        ];
    }

    /**
     * One page of the profit per product, best first. A page past the last one gives the last.
     *
     * @return array{data: list<ReportProduct>, current_page: int, last_page: int, per_page: int, from: int|null, to: int|null, total: int}
     */
    public function productsPage(CarbonImmutable $from, CarbonImmutable $to, int $page, int $perPage): array
    {
        return $this->paginate($this->productRows($from, $to), $page, $perPage);
    }

    /**
     * One page of the sales per category or per brand, from the same figures as the products (after returns, with the
     * dead stock loss), so the groups add up to the products. Products without a category or brand are one group of
     * their own. `$sort` is `name`, `revenue`, `profit` or `margin`, `$direction` `asc` or `desc`.
     *
     * @return array{data: list<array{id: int|null, name: string, product_count: int, revenue: string, cost: string, profit: string, margin_percent: string, share_percent: string}>, current_page: int, last_page: int, per_page: int, from: int|null, to: int|null, total: int, grouping: string, sort: string, direction: string}
     */
    public function groupsPage(CarbonImmutable $from, CarbonImmutable $to, SalesGrouping $grouping, string $sort, string $direction, int $page, int $perPage): array
    {
        $productRows = $this->productRows($from, $to);
        $groupOfProduct = Product::query()->withTrashed()->whereIn('id', $productRows->pluck('product_id'))->pluck($grouping->productColumn(), 'id');
        $names = match ($grouping) {
            SalesGrouping::Category => Category::query()->pluck('name', 'id'),
            SalesGrouping::Brand => Brand::query()->pluck('name', 'id'),
        };
        $totalRevenue = $productRows->reduce(fn (BigDecimal $total, array $row): BigDecimal => $total->plus($row['revenue']), BigDecimal::zero());

        $groups = $productRows
            ->groupBy(fn (array $row): int|string => $groupOfProduct[$row['product_id']] ?? '')
            ->map(function (Collection $rows, int|string $groupId) use ($names, $grouping, $totalRevenue): array {
                $revenue = $rows->reduce(fn (BigDecimal $total, array $row): BigDecimal => $total->plus($row['revenue']), BigDecimal::zero());
                $cost = $rows->reduce(fn (BigDecimal $total, array $row): BigDecimal => $total->plus($row['cost']), BigDecimal::zero());
                $profit = $revenue->minus($cost);

                return [
                    'id' => $groupId === '' ? null : (int) $groupId,
                    'name' => $groupId === '' ? $grouping->emptyLabel() : ($names[$groupId] ?? $grouping->emptyLabel()),
                    'product_count' => $rows->count(),
                    'revenue' => $this->money($revenue),
                    'cost' => $this->money($cost),
                    'profit' => $this->money($profit),
                    'margin_percent' => $revenue->isPositive() ? (string) $profit->multipliedBy(100)->dividedBy($revenue, 1, RoundingMode::HalfUp) : '0.0',
                    'share_percent' => $totalRevenue->isPositive() ? (string) $revenue->multipliedBy(100)->dividedBy($totalRevenue, 1, RoundingMode::HalfUp) : '0.0',
                ];
            });

        $sortValue = fn (array $group): float|string => $sort === 'name' ? mb_strtolower($group['name']) : (float) $group[$sort === 'margin' ? 'margin_percent' : $sort];

        // Groups that tie keep a stable order by name, so a page does not shuffle between requests.
        $sorted = $groups->sort(function (array $first, array $second) use ($sortValue, $direction): int {
            $order = $sortValue($first) <=> $sortValue($second);

            return ($direction === 'desc' ? -$order : $order) ?: mb_strtolower($first['name']) <=> mb_strtolower($second['name']);
        })->values();

        return [...$this->paginate($sorted, $page, $perPage), 'grouping' => $grouping->value, 'sort' => $sort, 'direction' => $direction];
    }

    /**
     * What comes back in returns, for the page to show what stands out: what became of the goods ({@see
     * self::returnOutcomes()}) and the products returned most often. It stops at the goods and leaves out who handled the
     * returns, and the reasons given, which are typed freely and too wide to say anything: what comes back says more
     * about what was sold than about who sold it. On the same business days as the rest of the report, the products
     * list being the top few, since it is there to catch the eye.
     *
     * @return array{returned: array{products_count: int, cost_total: string}, outcomes: list<array{outcome: string, label: string, products_count: int, cost_total: string, share_percent: string}>, waiting: array{products_count: int, cost_total: string}, products: list<array{product_id: int, name: string, unit: string, returns_count: int, quantity: string, total: string, rate_percent: string|null}>}
     */
    public function returnDetails(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            ...$this->returnOutcomes($from, $to),
            'products' => $this->mostReturnedProducts($from, $to),
        ];
    }

    /**
     * What became of the goods that came back. `returned` is what came back in the period. `outcomes` is what the
     * inspections of the returns warehouse posted in the period decided for every unit they counted (put back on the
     * shelf at full price, sent on as damaged goods to be sold cheaper, or written off as dead stock), so it can be goods
     * that came back before the period: an inspection is not tied to the returns it checks. `waiting` is what is in the
     * returns warehouse now, not yet inspected, whatever the period. Everything is valued at cost, since the units of
     * different products cannot be added up; the share is of the cost of what was decided.
     *
     * @return array{returned: array{products_count: int, cost_total: string}, outcomes: list<array{outcome: string, label: string, products_count: int, cost_total: string, share_percent: string}>, waiting: array{products_count: int, cost_total: string}}
     */
    private function returnOutcomes(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $returned = $this->returnedItemsQuery($from, $to)
            ->selectRaw('count(distinct sale_return_items.product_id) as products, coalesce(sum(sale_return_items.cost_total), 0) as cost')
            ->first();

        $decided = StockOpnameItem::query()
            ->join('stock_opnames', 'stock_opnames.id', '=', 'stock_opname_items.stock_opname_id')
            ->where('stock_opnames.status', StockOpnameStatus::Posted)
            ->where('stock_opnames.reason_code', StockOpnameReason::ReturnsCheck)
            ->whereBetween('stock_opnames.posted_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw(collect(['full_price' => 'full_price_quantity', 'damaged_goods' => 'damaged_goods_quantity', 'dead_stock' => 'dead_stock_quantity'])
                ->map(fn (string $column, string $key): string => "count(distinct case when stock_opname_items.{$column} > 0 then stock_opname_items.product_id end) as {$key}_products, coalesce(sum(stock_opname_items.{$column} * stock_opname_items.cost_price_at_posting), 0) as {$key}_cost")
                ->implode(', '))
            ->first();

        $outcomes = collect([
            ['outcome' => 'resellable', 'label' => 'Bisa dijual lagi', 'key' => 'full_price'],
            ['outcome' => 'damaged_goods', 'label' => 'Barang rusak (DG)', 'key' => 'damaged_goods'],
            ['outcome' => 'dead_stock', 'label' => 'Dead stock', 'key' => 'dead_stock'],
        ]);
        $decidedCost = $outcomes->reduce(fn (BigDecimal $total, array $outcome): BigDecimal => $total->plus((string) $decided->{"{$outcome['key']}_cost"}), BigDecimal::zero());

        $waiting = Stock::query()
            ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->where('warehouses.type', WarehouseType::Returns)
            ->where('stocks.quantity', '>', 0)
            ->selectRaw('count(distinct stocks.product_id) as products, coalesce(sum(stocks.quantity * products.cost_price), 0) as cost')
            ->first();

        return [
            'returned' => ['products_count' => (int) $returned->products, 'cost_total' => $this->money($returned->cost)],
            'outcomes' => $outcomes->map(function (array $outcome) use ($decided, $decidedCost): array {
                $cost = BigDecimal::of((string) $decided->{"{$outcome['key']}_cost"});

                return [
                    'outcome' => $outcome['outcome'],
                    'label' => $outcome['label'],
                    'products_count' => (int) $decided->{"{$outcome['key']}_products"},
                    'cost_total' => $this->money($cost),
                    'share_percent' => $decidedCost->isPositive() ? (string) $cost->multipliedBy(100)->dividedBy($decidedCost, 1, RoundingMode::HalfUp) : '0.0',
                ];
            })->all(),
            'waiting' => ['products_count' => (int) $waiting->products, 'cost_total' => $this->money($waiting->cost)],
        ];
    }

    /**
     * The products that came back in most returns, with what share of what was sold of them in the period that is
     * (`null` when none of it was sold in the period: it came back from an earlier sale).
     *
     * @return list<array{product_id: int, name: string, unit: string, returns_count: int, quantity: string, total: string, rate_percent: string|null}>
     */
    private function mostReturnedProducts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $returned = $this->returnedItemsQuery($from, $to)
            ->groupBy('sale_return_items.product_id')
            ->selectRaw('sale_return_items.product_id as product_id, count(distinct sale_return_items.sale_return_id) as returns, sum(sale_return_items.base_quantity) as quantity, sum(sale_return_items.refund_subtotal) as total')
            ->orderByDesc('returns')
            ->orderByDesc('quantity')
            ->orderBy('product_id')
            ->limit(self::RETURN_LIST_LIMIT)
            ->get();

        $productIds = $returned->pluck('product_id');
        $sold = $this->soldItemsQuery($from, $to)
            ->whereIn('sale_items.product_id', $productIds)
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id as product_id, sum(sale_items.base_quantity) as quantity')
            ->pluck('quantity', 'product_id');
        $products = Product::query()->withTrashed()->whereIn('id', $productIds)->get(['id', 'name', 'base_unit_code'])->keyBy('id');

        return $returned->map(function ($row) use ($sold, $products): array {
            $soldQuantity = BigDecimal::of((string) ($sold[$row->product_id] ?? 0));

            return [
                'product_id' => (int) $row->product_id,
                'name' => $products[$row->product_id]->name ?? '(barang dihapus)',
                'unit' => $products[$row->product_id]->base_unit_code ?? '',
                'returns_count' => (int) $row->returns,
                'quantity' => (string) BigDecimal::of((string) $row->quantity)->toScale(3),
                'total' => $this->money($row->total),
                'rate_percent' => $soldQuantity->isPositive() ? (string) BigDecimal::of((string) $row->quantity)->multipliedBy(100)->dividedBy($soldQuantity, 1, RoundingMode::HalfUp) : null,
            ];
        })->all();
    }

    /**
     * @template TRow
     *
     * @param  Collection<int, TRow>  $rows
     * @return array{data: list<TRow>, current_page: int, last_page: int, per_page: int, from: int|null, to: int|null, total: int}
     */
    private function paginate(Collection $rows, int $page, int $perPage): array
    {
        $lastPage = max((int) ceil($rows->count() / $perPage), 1);
        $currentPage = min(max($page, 1), $lastPage);
        $pageRows = $rows->forPage($currentPage, $perPage)->values();

        return [
            'data' => $pageRows->all(),
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'from' => $pageRows->isEmpty() ? null : ($currentPage - 1) * $perPage + 1,
            'to' => $pageRows->isEmpty() ? null : ($currentPage - 1) * $perPage + $pageRows->count(),
            'total' => $rows->count(),
        ];
    }

    /**
     * How many days of the period have come: all of them, or up to today for a period that runs past it.
     */
    private function elapsedDays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $today = CarbonImmutable::today();
        $lastDay = $to->greaterThan($today) && $from->lessThanOrEqualTo($today) ? $today : $to;

        return (int) round($from->diffInDays($lastDay)) + 1;
    }

    private function changePercent(string|int $current, string|int $previous): ?string
    {
        $previousAmount = BigDecimal::of((string) $previous);

        if ($previousAmount->isZero()) {
            return null;
        }

        return (string) BigDecimal::of((string) $current)->minus($previousAmount)->multipliedBy(100)->dividedBy($previousAmount->abs(), 1, RoundingMode::HalfUp);
    }

    /**
     * Products sold in the period whose items all carry no cost, so their profit is their whole sales.
     */
    private function productsWithoutCostCount(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->soldItemsQuery($from, $to)
            ->groupBy('sale_items.product_id')
            ->havingRaw('sum(sale_items.cost_total) = 0 and sum(sale_items.subtotal) > 0')
            ->selectRaw('sale_items.product_id')
            ->get()
            ->count();
    }

    /**
     * One row per day of the period, days without any sale included.
     *
     * @return list<ReportDay>
     */
    private function days(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $receiptsByDay = $this->salesQuery($from, $to)
            ->groupBy('business_date')
            ->selectRaw('business_date as day, count(*) as receipts, sum(total) as total')
            ->get()
            ->keyBy(fn ($row): string => (string) $row->day);

        $soldItemsByDay = $this->soldItemsQuery($from, $to)
            ->groupBy('sales.business_date')
            ->selectRaw('sales.business_date as day, sum(sale_items.subtotal) as revenue, sum(sale_items.cost_total) as cost')
            ->get()
            ->keyBy(fn ($row): string => (string) $row->day);

        $returnedItemsByDay = $this->returnedItemsQuery($from, $to)
            ->groupBy('cash_shifts.business_date')
            ->selectRaw('cash_shifts.business_date as day, sum(sale_return_items.refund_subtotal) as revenue, sum(sale_return_items.cost_total) as cost')
            ->get()
            ->keyBy(fn ($row): string => (string) $row->day);

        $creditsByDay = $this->completedReturnsQuery($from, $to)
            ->groupBy('cash_shifts.business_date')
            ->selectRaw('cash_shifts.business_date as day, sum(credit_amount) as credit')
            ->pluck('credit', 'day');

        $voidsByDay = $this->voidedSalesQuery($from, $to)
            ->groupBy('void_business_date')
            ->selectRaw('void_business_date as day, count(*) as voids, sum(total) as total')
            ->get()
            ->keyBy(fn ($row): string => (string) $row->day);

        $deadStockLossByDay = $this->deadStockLossQuery($from, $to)
            ->groupByRaw('date(stock_opnames.posted_at)')
            ->selectRaw('date(stock_opnames.posted_at) as day, sum(stock_opname_items.dead_stock_quantity * stock_opname_items.cost_price_at_posting) as loss')
            ->pluck('loss', 'day');

        $days = [];

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $key = $day->toDateString();
            $receipts = $receiptsByDay->get($key);
            $sold = $soldItemsByDay->get($key);
            $returned = $returnedItemsByDay->get($key);
            $voided = $voidsByDay->get($key);

            $gross = BigDecimal::of((string) ($receipts->total ?? 0));
            $returns = BigDecimal::of((string) ($creditsByDay[$key] ?? 0));
            $deadStockLoss = BigDecimal::of((string) ($deadStockLossByDay[$key] ?? 0));
            $profit = BigDecimal::of((string) ($sold->revenue ?? 0))->minus((string) ($sold->cost ?? 0))
                ->minus(BigDecimal::of((string) ($returned->revenue ?? 0))->minus((string) ($returned->cost ?? 0)))
                ->minus($deadStockLoss);

            $days[] = [
                'date' => $key,
                'gross_total' => $this->money($gross),
                'returns_total' => $this->money($returns),
                'net_total' => $this->money($gross->minus($returns)),
                'receipt_count' => (int) ($receipts->receipts ?? 0),
                'dead_stock_loss' => $this->money($deadStockLoss),
                'gross_profit' => $this->money($profit),
                'voids_count' => (int) ($voided->voids ?? 0),
                'voids_total' => $this->money($voided->total ?? 0),
            ];
        }

        return $days;
    }

    /**
     * @param  list<ReportDay>  $days
     * @return ReportTotals
     */
    private function totals(array $days): array
    {
        $sum = fn (string $field): BigDecimal => collect($days)->reduce(fn (BigDecimal $total, array $day): BigDecimal => $total->plus($day[$field]), BigDecimal::zero());

        $gross = $sum('gross_total');
        $net = $sum('net_total');
        $profit = $sum('gross_profit');
        $receiptCount = (int) collect($days)->sum('receipt_count');

        return [
            'gross_total' => $this->money($gross),
            'returns_total' => $this->money($sum('returns_total')),
            'net_total' => $this->money($net),
            'receipt_count' => $receiptCount,
            'dead_stock_loss' => $this->money($sum('dead_stock_loss')),
            'gross_profit' => $this->money($profit),
            'voids_count' => (int) collect($days)->sum('voids_count'),
            'voids_total' => $this->money($sum('voids_total')),
            'margin_percent' => $net->isPositive() ? (string) $profit->multipliedBy(100)->dividedBy($net, 1, RoundingMode::HalfUp) : '0.0',
        ];
    }

    /**
     * How the receipts of the period were paid, before returns: what came in per method (cash after the change given
     * back), COD apart as what is still to be paid on delivery (split by whether the goods have been delivered, on any
     * note that carries them: the same meaning as the shift summary, see {@see CodSaleService}), and,
     * when there are any, what was settled by an order deposit taken before or by the credit of a returned receipt.
     * Together they add up to the sales of the period.
     *
     * @return list<array{method: string, label: string, amount: string}>
     */
    private function methods(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $receivedByMethod = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.business_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method as method, sum(sale_payments.amount) as amount')
            ->pluck('amount', 'method');

        $changeGiven = BigDecimal::of((string) $this->salesQuery($from, $to)->sum('change_amount'));
        $receivedBy = fn (PaymentMethod $method): BigDecimal => BigDecimal::of((string) ($receivedByMethod[$method->value] ?? 0));

        $rows = collect(PaymentMethod::forCashier())->map(fn (PaymentMethod $method): array => [
            'method' => $method->value,
            'label' => $method->label(),
            'amount' => $this->money($method === PaymentMethod::Cash ? $receivedBy($method)->minus($changeGiven) : $receivedBy($method)),
        ]);

        $deliveredCod = BigDecimal::of((string) $this->codPaymentsQuery($from, $to)
            ->whereIn('sales.id', Sale::query()
                ->whereHas('deliveryNotes', fn (Builder $notes) => $notes->whereIn('delivery_notes.status', DeliveryNoteStatus::withDeliveredGoods()))
                ->select('sales.id'))
            ->sum('sale_payments.amount'));

        $rows->push(['method' => 'cod_delivered', 'label' => 'COD (sudah diantar)', 'amount' => $this->money($deliveredCod)]);
        $rows->push(['method' => 'cod_undelivered', 'label' => 'COD (belum diantar)', 'amount' => $this->money($receivedBy(PaymentMethod::Cod)->minus($deliveredCod))]);

        foreach ([PaymentMethod::OrderDeposit, PaymentMethod::ReturnCredit] as $settledBefore) {
            if ($receivedBy($settledBefore)->isPositive()) {
                $rows->push(['method' => $settledBefore->value, 'label' => $settledBefore->label(), 'amount' => $this->money($receivedBy($settledBefore))]);
            }
        }

        return $rows->all();
    }

    /**
     * The estimated profit per product, best first: what it sold for minus its cost, after what came back.
     *
     * @return Collection<int, ReportProduct>
     */
    private function productRows(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $sold = $this->soldItemsQuery($from, $to)
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id as product_id, max(sale_items.product_name) as sold_as, sum(sale_items.subtotal) as revenue, sum(sale_items.cost_total) as cost, sum(sale_items.base_quantity) as quantity')
            ->get()
            ->keyBy('product_id');

        $returned = $this->returnedItemsQuery($from, $to)
            ->groupBy('sale_return_items.product_id')
            ->selectRaw('sale_return_items.product_id as product_id, sum(sale_return_items.refund_subtotal) as revenue, sum(sale_return_items.cost_total) as cost, sum(sale_return_items.base_quantity) as quantity')
            ->get()
            ->keyBy('product_id');

        $deadStockLosses = $this->deadStockLossQuery($from, $to)
            ->groupBy('stock_opname_items.product_id')
            ->selectRaw('stock_opname_items.product_id as product_id, sum(stock_opname_items.dead_stock_quantity * stock_opname_items.cost_price_at_posting) as loss')
            ->pluck('loss', 'product_id');

        $productIds = $sold->keys()->merge($returned->keys())->merge($deadStockLosses->keys())->unique();
        $products = Product::query()->withTrashed()->whereIn('id', $productIds)->get(['id', 'name', 'base_unit_code'])->keyBy('id');

        return $productIds->map(function (int $productId) use ($sold, $returned, $deadStockLosses, $products): array {
            $revenue = BigDecimal::of((string) ($sold[$productId]->revenue ?? 0))->minus((string) ($returned[$productId]->revenue ?? 0));
            $cost = BigDecimal::of((string) ($sold[$productId]->cost ?? 0))->minus((string) ($returned[$productId]->cost ?? 0))->plus((string) ($deadStockLosses[$productId] ?? 0));
            $quantity = BigDecimal::of((string) ($sold[$productId]->quantity ?? 0))->minus((string) ($returned[$productId]->quantity ?? 0));
            $profit = $revenue->minus($cost);

            return [
                'product_id' => $productId,
                'name' => $products[$productId]->name ?? $sold[$productId]->sold_as ?? '(barang dihapus)',
                'unit' => $products[$productId]->base_unit_code ?? '',
                'quantity' => (string) $quantity->toScale(3),
                'revenue' => $this->money($revenue),
                'cost' => $this->money($cost),
                'profit' => $this->money($profit),
                'margin_percent' => $revenue->isZero() ? '0.0' : (string) $profit->multipliedBy(100)->dividedBy($revenue, 1, RoundingMode::HalfUp),
            ];
        })->sortByDesc(fn (array $row): float => (float) $row['profit'])->values();
    }

    /**
     * @return Builder<Sale>
     */
    private function salesQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Sale::query()->where('status', SaleStatus::Completed)->whereBetween('business_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * Receipts voided in the period, by the business day of the void.
     *
     * @return Builder<Sale>
     */
    private function voidedSalesQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Sale::query()->where('status', SaleStatus::Voided)->whereBetween('void_business_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * The COD part of the completed receipts of the period, with `sales` joined so a caller can narrow by receipt.
     *
     * @return Builder<SalePayment>
     */
    private function codPaymentsQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.business_date', [$from->toDateString(), $to->toDateString()])
            ->where('sale_payments.method', PaymentMethod::Cod);
    }

    /**
     * @return Builder<SaleItem>
     */
    private function soldItemsQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.business_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * The completed returns of the period, with `cash_shifts` joined: a return belongs to the business day of the shift
     * it was made in, as the day closing counts it.
     *
     * @return Builder<SaleReturn>
     */
    private function completedReturnsQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return SaleReturn::query()
            ->join('cash_shifts', 'cash_shifts.id', '=', 'sale_returns.cash_shift_id')
            ->where('sale_returns.status', SaleReturnStatus::Completed)
            ->whereBetween('cash_shifts.business_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * The units an inspection or a count sent to dead stock, from the opnames posted in the period, with `stock_opnames`
     * joined. A routine count only sends units there with a damaged-goods decision on a variance, which is why such an
     * item must have one; an inspection of returns decides for every unit it counted.
     *
     * @return Builder<StockOpnameItem>
     */
    private function deadStockLossQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return StockOpnameItem::query()
            ->join('stock_opnames', 'stock_opnames.id', '=', 'stock_opname_items.stock_opname_id')
            ->where('stock_opnames.status', StockOpnameStatus::Posted)
            ->where('stock_opname_items.dead_stock_quantity', '>', 0)
            ->where(fn (Builder $items) => $items
                ->where('stock_opnames.reason_code', StockOpnameReason::ReturnsCheck)
                ->orWhere('stock_opname_items.variance', '!=', 0))
            ->whereBetween('stock_opnames.posted_at', [$from->startOfDay(), $to->endOfDay()]);
    }

    /**
     * @return Builder<SaleReturnItem>
     */
    private function returnedItemsQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('cash_shifts', 'cash_shifts.id', '=', 'sale_returns.cash_shift_id')
            ->where('sale_returns.status', SaleReturnStatus::Completed)
            ->whereBetween('cash_shifts.business_date', [$from->toDateString(), $to->toDateString()]);
    }

    private function money(BigDecimal|string|int|float|null $amount): string
    {
        return (string) BigDecimal::of((string) ($amount ?? 0))->toScale(2);
    }
}
