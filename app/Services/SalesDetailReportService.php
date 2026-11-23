<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The sales report of a period down to the receipt: every receipt with its goods and how it was paid, day by day, then
 * the voided receipts and the returns of the period, and what was sold per product. It is for the printed sheet and the
 * spreadsheet, where an accountant ticks receipts off against the totals, so its totals and its per-method figures come from
 * {@see SalesReportService} and cannot drift from the summary sheet. Must run while the store's tenancy is initialized.
 *
 * A receipt belongs to its business day, a void to the business day it was done on and a return to the business day of the
 * shift it was made in, the same as in the summary report. Who rang a receipt up is left out, as in the rest of the report.
 *
 * The list can be narrowed to the receipts paid partly or wholly by one method. Returns and voids are not tied to a method,
 * so a narrowed list has none, and its totals are those of the receipts it lists (a day has no returns and no net figure
 * then): the figures of the whole period would not match what is listed. A receipt paid by two methods is listed whole.
 *
 * @phpstan-type DetailPayment array{method: string, label: string, amount: string, reference: string|null}
 * @phpstan-type DetailItem array{name: string, quantity: string, unit: string, unit_price: string, subtotal: string}
 * @phpstan-type DetailReceipt array{number: string, time: string, customer_name: string|null, fulfillment: string, subtotal: string, discount_total: string, total: string, change_amount: string, is_voided: bool, void_reason: string|null, items: list<DetailItem>, payments: list<DetailPayment>}
 * @phpstan-type DetailDay array{date: string, receipt_count: int, gross_total: string, returns_total: string|null, net_total: string|null, methods: list<array{method: string, label: string, amount: string}>, receipts: list<DetailReceipt>}
 */
class SalesDetailReportService
{
    public function __construct(private readonly SalesReportService $salesReport) {}

    /**
     * @return array{from: string, to: string, filter: string|null, totals: array<string, string|int>, methods: list<array{method: string, label: string, amount: string}>, days: list<DetailDay>, goods: list<array{name: string, unit: string, quantity: string, subtotal: string}>, voids: list<array{number: string, business_date: string, void_date: string, reason: string|null, total: string}>, returns: list<array{number: string, receipt_number: string, date: string, resolution: string, reason: string|null, refund_method: string|null, credit_amount: string, items: list<array{name: string, quantity: string, unit: string, refund_subtotal: string}>}>}
     */
    public function report(CarbonImmutable $from, CarbonImmutable $to, ?PaymentMethod $method = null): array
    {
        $receipts = $this->receipts($from, $to, $method);
        $summary = $method === null ? $this->salesReport->summary($from, $to) : null;

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'filter' => $method?->label(),
            ...($summary === null ? $this->narrowedPeriod($receipts) : $this->wholePeriod($from, $to, $summary)),
            'days' => $this->days($receipts, $summary),
            'goods' => $this->goods($receipts),
            'voids' => $method === null ? $this->voids($from, $to) : [],
            'returns' => $method === null ? $this->returns($from, $to) : [],
        ];
    }

    /**
     * Every receipt sold on one day, the ones voided since too (marked as such), for the day closing report. What came in per
     * method is counted the way the closing counts it: the money did come in on a receipt that was voided afterwards, and
     * goes back out as a refund, so the methods add up to the closing's figures. COD is the exception, being money still
     * owed rather than money in, so a voided receipt's COD is left out. `gross_total` and `receipt_count` are of the
     * receipts that stand, as the closing's sales are.
     *
     * @return array{receipt_count: int, voided_count: int, gross_total: string, methods: list<array{method: string, label: string, amount: string}>, receipts: list<DetailReceipt>, returns: list<array<string, mixed>>}
     */
    public function forDay(CarbonImmutable $day): array
    {
        $receipts = $this->receipts($day, $day, null, includeVoided: true);
        $standing = $receipts->filter(fn (Sale $sale): bool => $sale->status === SaleStatus::Completed);

        return [
            'receipt_count' => $standing->count(),
            'voided_count' => $receipts->count() - $standing->count(),
            'gross_total' => $this->sumOf($standing, 'total'),
            'methods' => $this->methodsOf($receipts),
            'receipts' => $receipts->map(fn (Sale $sale): array => $this->receiptRow($sale))->values()->all(),
            'returns' => $this->returns($day, $day),
        ];
    }

    /**
     * @param  array{totals: array<string, string|int>, days: list<array<string, mixed>>}  $summary
     * @return array{totals: array<string, string|int>, methods: list<array{method: string, label: string, amount: string}>}
     */
    private function wholePeriod(CarbonImmutable $from, CarbonImmutable $to, array $summary): array
    {
        return ['totals' => $summary['totals'], ...$this->salesReport->payments($from, $to)];
    }

    /**
     * @param  Collection<int, Sale>  $receipts
     * @return array{totals: array<string, string|int>, methods: list<array{method: string, label: string, amount: string}>}
     */
    private function narrowedPeriod(Collection $receipts): array
    {
        return [
            'totals' => ['receipt_count' => $receipts->count(), 'gross_total' => $this->sumOf($receipts, 'total')],
            'methods' => $this->methodsOf($receipts),
        ];
    }

    /**
     * @param  Collection<int, Sale>  $receipts
     * @param  array{days: list<array<string, mixed>>}|null  $summary  The summary of the whole period; none when the list is narrowed.
     * @return list<DetailDay>
     */
    private function days(Collection $receipts, ?array $summary): array
    {
        $isNarrowed = $summary === null;
        $summaryDays = $isNarrowed ? collect() : collect($summary['days'])->keyBy('date');

        return $receipts
            ->groupBy(fn (Sale $sale): string => $sale->business_date->toDateString())
            ->map(fn (Collection $sales, string $date): array => [
                'date' => $date,
                'receipt_count' => $sales->count(),
                'gross_total' => $isNarrowed ? $this->sumOf($sales, 'total') : $summaryDays[$date]['gross_total'],
                'returns_total' => $isNarrowed ? null : $summaryDays[$date]['returns_total'],
                'net_total' => $isNarrowed ? null : $summaryDays[$date]['net_total'],
                'methods' => $this->methodsOf($sales),
                'receipts' => $sales->map(fn (Sale $sale): array => $this->receiptRow($sale))->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Sale>
     */
    private function receipts(CarbonImmutable $from, CarbonImmutable $to, ?PaymentMethod $method, bool $includeVoided = false): Collection
    {
        return Sale::query()
            ->with(['items', 'payments'])
            ->whereIn('status', $includeVoided ? [SaleStatus::Completed, SaleStatus::Voided] : [SaleStatus::Completed])
            ->whereBetween('business_date', [$from->toDateString(), $to->toDateString()])
            ->when($method !== null, fn (Builder $sales) => $sales->whereHas('payments', fn (Builder $payments) => $payments->where('method', $method)))
            ->orderBy('business_date')
            ->orderBy('sold_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Sale>  $receipts
     */
    private function sumOf(Collection $receipts, string $column): string
    {
        return $this->money($receipts->reduce(fn (BigDecimal $sum, Sale $sale): BigDecimal => $sum->plus((string) $sale->{$column}), BigDecimal::zero()));
    }

    /**
     * @return DetailReceipt
     */
    private function receiptRow(Sale $sale): array
    {
        return [
            'number' => $sale->number,
            'time' => $sale->sold_at->format('H:i'),
            'customer_name' => $sale->customer_name,
            'fulfillment' => $sale->fulfillment->label(),
            'subtotal' => $this->money($sale->subtotal),
            'discount_total' => $this->money($sale->discount_total),
            'total' => $this->money($sale->total),
            'change_amount' => $this->money($sale->change_amount),
            'is_voided' => $sale->status === SaleStatus::Voided,
            'void_reason' => $sale->void_reason,
            'items' => $sale->items->map(fn (SaleItem $item): array => [
                'name' => $item->product_name,
                'quantity' => (string) $item->quantity,
                'unit' => $item->unit_name,
                'unit_price' => $this->money($item->unit_price),
                'subtotal' => $this->money($item->subtotal),
            ])->all(),
            'payments' => $sale->payments->map(fn (SalePayment $payment): array => [
                'method' => $payment->method->value,
                'label' => $payment->method->label(),
                'amount' => $this->money($payment->amount),
                'reference' => $payment->reference,
            ])->all(),
        ];
    }

    /**
     * What came in per method on a set of receipts, cash after the change given back, so the methods of a day add up to
     * its sales the same as the methods of the whole period do. A method with nothing is left out. A voided receipt's COD is
     * not counted: it was never money in (see {@see self::forDay()}).
     *
     * @param  Collection<int, Sale>  $sales
     * @return list<array{method: string, label: string, amount: string}>
     */
    private function methodsOf(Collection $sales): array
    {
        $change = $sales->reduce(fn (BigDecimal $sum, Sale $sale): BigDecimal => $sum->plus((string) $sale->change_amount), BigDecimal::zero());

        return $sales
            ->flatMap(fn (Sale $sale): Collection => $sale->payments->reject(fn (SalePayment $payment): bool => $sale->status === SaleStatus::Voided && $payment->method === PaymentMethod::Cod))
            ->groupBy(fn (SalePayment $payment): string => $payment->method->value)
            ->map(function (Collection $payments, string $method) use ($change): array {
                $received = $payments->reduce(fn (BigDecimal $sum, SalePayment $payment): BigDecimal => $sum->plus((string) $payment->amount), BigDecimal::zero());
                $paymentMethod = PaymentMethod::from($method);

                return [
                    'method' => $method,
                    'label' => $paymentMethod->label(),
                    'amount' => $this->money($paymentMethod === PaymentMethod::Cash ? $received->minus($change) : $received),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * What was sold per product on the listed receipts, before returns, by name: the name and unit are those copied onto
     * the receipt, so a product renamed since still adds up with itself within the period it was sold under one name.
     *
     * @param  Collection<int, Sale>  $receipts
     * @return list<array{name: string, unit: string, quantity: string, subtotal: string}>
     */
    private function goods(Collection $receipts): array
    {
        return $receipts->flatMap->items
            ->groupBy(fn (SaleItem $item): string => "{$item->product_name}|{$item->unit_name}")
            ->map(fn (Collection $items): array => [
                'name' => $items[0]->product_name,
                'unit' => $items[0]->unit_name,
                'quantity' => (string) $items->reduce(fn (BigDecimal $sum, SaleItem $item): BigDecimal => $sum->plus((string) $item->quantity), BigDecimal::zero())->toScale(3),
                'subtotal' => $this->money($items->reduce(fn (BigDecimal $sum, SaleItem $item): BigDecimal => $sum->plus((string) $item->subtotal), BigDecimal::zero())),
            ])
            ->sortBy(fn (array $row): string => mb_strtolower($row['name']))
            ->values()
            ->all();
    }

    /**
     * @return list<array{number: string, business_date: string, void_date: string, reason: string|null, total: string}>
     */
    private function voids(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Sale::query()
            ->where('status', SaleStatus::Voided)
            ->whereBetween('void_business_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('void_business_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Sale $sale): array => [
                'number' => $sale->number,
                'business_date' => $sale->business_date->toDateString(),
                'void_date' => $sale->void_business_date->toDateString(),
                'reason' => $sale->void_reason,
                'total' => $this->money($sale->total),
            ])
            ->all();
    }

    /**
     * @return list<array{number: string, receipt_number: string, date: string, resolution: string, reason: string|null, refund_method: string|null, credit_amount: string, items: list<array{name: string, quantity: string, unit: string, refund_subtotal: string}>}>
     */
    private function returns(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return SaleReturn::query()
            ->select('sale_returns.*')
            ->selectRaw('cash_shifts.business_date as business_date')
            ->join('cash_shifts', 'cash_shifts.id', '=', 'sale_returns.cash_shift_id')
            ->with(['sale:id,number', 'items.saleItem:id,product_name,unit_name'])
            ->where('sale_returns.status', SaleReturnStatus::Completed)
            ->whereBetween('cash_shifts.business_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('cash_shifts.business_date')
            ->orderBy('sale_returns.id')
            ->get()
            ->map(fn (SaleReturn $return): array => [
                'number' => $return->number,
                'receipt_number' => $return->sale->number,
                'date' => (string) $return->business_date,
                'resolution' => $return->resolution->label(),
                'reason' => $return->reason,
                'refund_method' => $return->refund_method?->label(),
                'credit_amount' => $this->money($return->credit_amount),
                'items' => $return->items->map(fn (SaleReturnItem $item): array => [
                    'name' => $item->saleItem->product_name,
                    'quantity' => (string) $item->quantity,
                    'unit' => $item->saleItem->unit_name,
                    'refund_subtotal' => $this->money($item->refund_subtotal),
                ])->all(),
            ])
            ->all();
    }

    private function money(BigDecimal|string|int|float|null $amount): string
    {
        return (string) BigDecimal::of((string) ($amount ?? 0))->toScale(2);
    }
}
