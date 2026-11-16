<?php

namespace App\Services;

use App\Enums\DeliveryNoteStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameStatus;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockOpnameItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
 * The figures of the methods and the cashiers are of the sales, before returns: returns are not tied to the cashier who
 * sold, nor to the method the sale was paid by.
 *
 * @phpstan-type ReportTotals array{gross_total: string, returns_total: string, net_total: string, receipt_count: int, dead_stock_loss: string, gross_profit: string, voids_count: int, voids_total: string}
 * @phpstan-type ReportDay array{date: string, gross_total: string, returns_total: string, net_total: string, receipt_count: int, dead_stock_loss: string, gross_profit: string, voids_count: int, voids_total: string}
 * @phpstan-type ReportProduct array{product_id: int, name: string, unit: string, quantity: string, revenue: string, cost: string, profit: string, margin_percent: string, is_cost_missing: bool}
 * @phpstan-type SalesReport array{from: string, to: string, totals: ReportTotals, days: list<ReportDay>, methods: list<array{method: string, label: string, amount: string}>, cashiers: list<array{cashier_name: string, receipt_count: int, total: string}>, products: list<ReportProduct>, product_count: int, product_limit: int, products_without_cost: int}
 * @phpstan-type ReportPeriod array{from: CarbonImmutable, to: CarbonImmutable, is_cut_back: bool}
 */
class SalesReportService
{
    /** The most days of one report, so a mistyped date cannot ask for years of rows. */
    public const MAX_DAYS = 366;

    /** A report without dates covers the last week, today included. */
    private const DEFAULT_DAYS = 7;

    private const PRODUCT_LIMIT = 30;

    /**
     * The period asked for, made one the report can give: a missing end is today, a missing start is a week before the
     * end, a start after the end is swapped, and a period longer than {@see self::MAX_DAYS} is cut back from its start
     * (`is_cut_back` says so, for the page to tell).
     *
     * @return ReportPeriod
     */
    public function period(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $to ??= CarbonImmutable::today();
        $from ??= $to->subDays(self::DEFAULT_DAYS - 1);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $earliest = $to->subDays(self::MAX_DAYS - 1);

        return ['from' => $from->lessThan($earliest) ? $earliest : $from, 'to' => $to, 'is_cut_back' => $from->lessThan($earliest)];
    }

    /**
     * @return SalesReport
     */
    public function report(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $productRows = $this->productRows($from, $to);
        $days = $this->days($from, $to);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => $this->totals($days),
            'days' => $days,
            'methods' => $this->methods($from, $to),
            'cashiers' => $this->cashiers($from, $to),
            'products' => $productRows->take(self::PRODUCT_LIMIT)->values()->all(),
            'product_count' => $productRows->count(),
            'product_limit' => self::PRODUCT_LIMIT,
            'products_without_cost' => $productRows->where('is_cost_missing', true)->count(),
        ];
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

        return [
            'gross_total' => $this->money($sum('gross_total')),
            'returns_total' => $this->money($sum('returns_total')),
            'net_total' => $this->money($sum('net_total')),
            'receipt_count' => (int) collect($days)->sum('receipt_count'),
            'dead_stock_loss' => $this->money($sum('dead_stock_loss')),
            'gross_profit' => $this->money($sum('gross_profit')),
            'voids_count' => (int) collect($days)->sum('voids_count'),
            'voids_total' => $this->money($sum('voids_total')),
        ];
    }

    /**
     * How the receipts of the period were paid, before returns: what came in per method (cash after the change given
     * back), COD apart as what is still to be paid on delivery (split by whether the goods have been delivered), and,
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
            ->whereIn('sales.id', DeliveryNote::query()->whereIn('status', DeliveryNoteStatus::withDeliveredGoods())->select('sale_id'))
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
     * @return list<array{cashier_name: string, receipt_count: int, total: string}>
     */
    private function cashiers(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->salesQuery($from, $to)
            ->groupBy('cashier_id', 'cashier_name')
            ->selectRaw('cashier_name, count(*) as receipts, sum(total) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => ['cashier_name' => $row->cashier_name, 'receipt_count' => (int) $row->receipts, 'total' => $this->money($row->total)])
            ->all();
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
                'is_cost_missing' => isset($sold[$productId]) && BigDecimal::of((string) $sold[$productId]->cost)->isZero() && BigDecimal::of((string) $sold[$productId]->revenue)->isPositive(),
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
