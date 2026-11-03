<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
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
 * Sales belong to the business day of their shift; a return belongs to the day it was made. The cost of an item is the
 * last purchase price when it was sold (R1 has no cost layers yet), so profit is an estimate. Voided receipts are left
 * out. A COD sale counts when it is made, whether or not the goods have been delivered.
 *
 * @phpstan-type ReportTotals array{gross_total: string, returns_total: string, net_total: string, receipt_count: int, gross_profit: string}
 * @phpstan-type ReportDay array{date: string, gross_total: string, returns_total: string, net_total: string, receipt_count: int, gross_profit: string}
 * @phpstan-type ReportProduct array{product_id: int, name: string, unit: string, quantity: string, revenue: string, cost: string, profit: string, margin_percent: string}
 * @phpstan-type SalesReport array{from: string, to: string, totals: ReportTotals, days: list<ReportDay>, methods: list<array{method: string, label: string, amount: string}>, cashiers: list<array{cashier_name: string, receipt_count: int, total: string}>, products: list<ReportProduct>}
 */
class SalesReportService
{
    /** The most days of one report, so a mistyped date cannot ask for years of rows. */
    public const MAX_DAYS = 366;

    private const PRODUCT_LIMIT = 30;

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
        ];
    }

    /**
     * One row per day of the period, days without any sale included.
     *
     * @return list<ReportDay>
     */
    private function days(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $salesByDay = $this->soldItemsQuery($from, $to)
            ->groupBy('sales.business_date')
            ->selectRaw('sales.business_date as day, count(distinct sales.id) as receipts, sum(sale_items.subtotal) as revenue, sum(sale_items.cost_total) as cost')
            ->get()
            ->keyBy(fn ($row): string => (string) $row->day);

        $receiptTotalsByDay = $this->salesQuery($from, $to)
            ->groupBy('business_date')
            ->selectRaw('business_date as day, sum(total) as total')
            ->pluck('total', 'day');

        $returnsByDay = $this->returnedItemsQuery($from, $to)
            ->groupByRaw('date(sale_returns.returned_at)')
            ->selectRaw('date(sale_returns.returned_at) as day, sum(sale_return_items.refund_subtotal) as revenue, sum(sale_return_items.cost_total) as cost')
            ->get()
            ->keyBy(fn ($row): string => (string) $row->day);

        $creditsByDay = SaleReturn::query()
            ->where('status', SaleReturnStatus::Completed)
            ->whereDate('returned_at', '>=', $from->toDateString())
            ->whereDate('returned_at', '<=', $to->toDateString())
            ->groupByRaw('date(returned_at)')
            ->selectRaw('date(returned_at) as day, sum(credit_amount) as credit')
            ->pluck('credit', 'day');

        $days = [];

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $key = $day->toDateString();
            $sold = $salesByDay->get($key);
            $returned = $returnsByDay->get($key);

            $gross = BigDecimal::of((string) ($receiptTotalsByDay[$key] ?? 0));
            $returns = BigDecimal::of((string) ($creditsByDay[$key] ?? 0));
            $profit = BigDecimal::of((string) ($sold->revenue ?? 0))->minus((string) ($sold->cost ?? 0))
                ->minus(BigDecimal::of((string) ($returned->revenue ?? 0))->minus((string) ($returned->cost ?? 0)));

            $days[] = [
                'date' => $key,
                'gross_total' => $this->money($gross),
                'returns_total' => $this->money($returns),
                'net_total' => $this->money($gross->minus($returns)),
                'receipt_count' => (int) ($sold->receipts ?? 0),
                'gross_profit' => $this->money($profit),
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
            'gross_profit' => $this->money($sum('gross_profit')),
        ];
    }

    /**
     * What came in per method for the receipts of the period (cash after the change given back), and what is still to
     * be paid on delivery.
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

        return collect(PaymentMethod::forSale())->map(function (PaymentMethod $method) use ($receivedByMethod, $changeGiven): array {
            $amount = BigDecimal::of((string) ($receivedByMethod[$method->value] ?? 0));

            return ['method' => $method->value, 'label' => $method->label(), 'amount' => $this->money($method === PaymentMethod::Cash ? $amount->minus($changeGiven) : $amount)];
        })->all();
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
            ->selectRaw('sale_items.product_id as product_id, sum(sale_items.subtotal) as revenue, sum(sale_items.cost_total) as cost, sum(sale_items.base_quantity) as quantity')
            ->get()
            ->keyBy('product_id');

        $returned = $this->returnedItemsQuery($from, $to)
            ->groupBy('sale_return_items.product_id')
            ->selectRaw('sale_return_items.product_id as product_id, sum(sale_return_items.refund_subtotal) as revenue, sum(sale_return_items.cost_total) as cost, sum(sale_return_items.base_quantity) as quantity')
            ->get()
            ->keyBy('product_id');

        $products = Product::query()->withTrashed()->whereIn('id', $sold->keys()->merge($returned->keys())->unique())->get(['id', 'name', 'base_unit_code'])->keyBy('id');

        return $sold->keys()->merge($returned->keys())->unique()->map(function (int $productId) use ($sold, $returned, $products): array {
            $revenue = BigDecimal::of((string) ($sold[$productId]->revenue ?? 0))->minus((string) ($returned[$productId]->revenue ?? 0));
            $cost = BigDecimal::of((string) ($sold[$productId]->cost ?? 0))->minus((string) ($returned[$productId]->cost ?? 0));
            $quantity = BigDecimal::of((string) ($sold[$productId]->quantity ?? 0))->minus((string) ($returned[$productId]->quantity ?? 0));
            $profit = $revenue->minus($cost);

            return [
                'product_id' => $productId,
                'name' => $products[$productId]->name ?? '(barang dihapus)',
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
     * @return Builder<SaleReturnItem>
     */
    private function returnedItemsQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->where('sale_returns.status', SaleReturnStatus::Completed)
            ->whereDate('sale_returns.returned_at', '>=', $from->toDateString())
            ->whereDate('sale_returns.returned_at', '<=', $to->toDateString());
    }

    private function money(BigDecimal|string|int|float|null $amount): string
    {
        return (string) BigDecimal::of((string) ($amount ?? 0))->toScale(2);
    }
}
