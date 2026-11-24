<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleFulfillment;
use App\Enums\SaleStatus;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The detailed sales report as flat sheets for a spreadsheet: one row per receipt, one per goods line, one per returned
 * line and one per voided receipt, so the sheets can be filtered and summed in the spreadsheet without a line being counted
 * twice. It holds only what a transaction list needs (subtotals, no cost or margin) and knows no file format: a writer
 * turns the rows into a file. Amounts and quantities are numbers, days are `Y-m-d`. Must run while the store's tenancy is
 * initialized.
 *
 * The receipts and the goods lines are read straight from the database and handed on one row at a time, never as models
 * and never all at once, so a year of receipts (tens of thousands, with some ninety thousand lines) costs a few megabytes
 * and about a second of reading where the models of a month already weigh over a hundred. For the same reason a sheet is a
 * generator: it yields its heading row first, then its rows, and returns the row of totals that closes it (`null` when it
 * has no row), which is only known once the last row has been read.
 *
 * Each receipt row splits what was paid per method, cash after the change given back, so the method columns add up to the
 * receipt's total the same way the methods of the report do. The list can be narrowed to the receipts paid partly or wholly
 * by one method; returns and voids are not tied to a method, so a narrowed list has none.
 *
 * @phpstan-type SheetRow list<string|float|null>
 * @phpstan-type Sheet Generator<int, SheetRow, mixed, SheetRow|null>
 */
class SalesDetailSheetRows
{
    public function __construct(private readonly SalesDetailReportService $detailReport) {}

    /**
     * @return array<string, Sheet> Sheet name => its rows.
     */
    public function sheets(CarbonImmutable $from, CarbonImmutable $to, ?PaymentMethod $method = null): array
    {
        return [
            'Nota' => $this->receiptSheet($from, $to, $method),
            'Barang' => $this->goodsSheet($from, $to, $method),
            'Retur' => $this->returnSheet($method === null ? $this->detailReport->returns($from, $to) : []),
            'Void' => $this->voidSheet($method === null ? $this->detailReport->voids($from, $to) : []),
        ];
    }

    /**
     * A sheet from its heading row and its rows: the heading first, then every row, and, as what the generator returns,
     * the row that closes the sheet. The closing row has the label in the first column and, under each of the summed
     * columns, the sum of its rows. Only amounts are summed: quantities of different units and unit prices add up to nothing.
     *
     * @param  SheetRow  $headings
     * @param  iterable<SheetRow>  $rows
     * @param  list<string>  $summedHeadings
     * @param  callable(int): string  $label  Given how many rows there are.
     * @return Sheet
     */
    private function sheet(array $headings, iterable $rows, array $summedHeadings, callable $label): Generator
    {
        yield $headings;

        $summedColumns = array_keys(array_intersect($headings, $summedHeadings));
        $sums = array_fill_keys($summedColumns, 0.0);
        $rowCount = 0;

        foreach ($rows as $row) {
            yield $row;

            $rowCount++;

            foreach ($summedColumns as $column) {
                $sums[$column] = round($sums[$column] + $row[$column], 2);
            }
        }

        if ($rowCount === 0) {
            return null;
        }

        $closing = array_fill(0, count($headings), null);
        $closing[0] = $label($rowCount);

        foreach ($sums as $column => $sum) {
            $closing[$column] = $sum;
        }

        return $closing;
    }

    /**
     * @return Sheet
     */
    private function receiptSheet(CarbonImmutable $from, CarbonImmutable $to, ?PaymentMethod $method): Generator
    {
        $methodLabels = array_map(fn (PaymentMethod $paymentMethod): string => $paymentMethod->label(), PaymentMethod::cases());
        $headings = ['Tanggal', 'Jam', 'No. nota', 'Pelanggan', 'Pengambilan', 'Subtotal', 'Diskon', 'Total', ...$methodLabels, 'Referensi pembayaran'];

        return $this->sheet($headings, $this->receiptRows($from, $to, $method), ['Subtotal', 'Diskon', 'Total', ...$methodLabels], fn (int $count): string => "JUMLAH ({$count} nota)");
    }

    /**
     * @return Generator<int, SheetRow>
     */
    private function receiptRows(CarbonImmutable $from, CarbonImmutable $to, ?PaymentMethod $method): Generator
    {
        $references = $this->referencesBySale($from, $to, $method);

        foreach ($this->receiptQuery($from, $to, $method)->cursor() as $receipt) {
            yield [
                $receipt->business_date,
                substr($receipt->sold_at, 11, 5),
                $receipt->number,
                $receipt->customer_name,
                SaleFulfillment::from($receipt->fulfillment)->label(),
                (float) $receipt->subtotal,
                (float) $receipt->discount_total,
                (float) $receipt->total,
                ...array_map(fn (PaymentMethod $paymentMethod): float => $this->paidBy($receipt, $paymentMethod), PaymentMethod::cases()),
                $references[$receipt->id] ?? null,
            ];
        }
    }

    /**
     * One row per completed receipt of the period, in the order the sheets list them, with what was paid by each method
     * already summed (`paid_<method>`).
     */
    private function receiptQuery(CarbonImmutable $from, CarbonImmutable $to, ?PaymentMethod $method): Builder
    {
        $paidColumns = array_map(fn (PaymentMethod $paymentMethod): string => "sum(case when sale_payments.method = ? then sale_payments.amount else 0 end) as paid_{$paymentMethod->value}", PaymentMethod::cases());
        $paidBindings = array_map(fn (PaymentMethod $paymentMethod): string => $paymentMethod->value, PaymentMethod::cases());

        return $this->completedSales($from, $to, $method)
            ->leftJoin('sale_payments', 'sale_payments.sale_id', '=', 'sales.id')
            ->groupBy('sales.id')
            ->orderBy('sales.business_date')
            ->orderBy('sales.sold_at')
            ->orderBy('sales.id')
            ->selectRaw('sales.id, sales.business_date, sales.sold_at, sales.number, sales.customer_name, sales.fulfillment, sales.subtotal, sales.discount_total, sales.total, sales.change_amount, '.implode(', ', $paidColumns), $paidBindings);
    }

    /**
     * What was paid by one method; cash is what the customer handed over less the change given back.
     */
    private function paidBy(object $receipt, PaymentMethod $method): float
    {
        $paid = (float) $receipt->{"paid_{$method->value}"};

        return $method === PaymentMethod::Cash ? $paid - (float) $receipt->change_amount : $paid;
    }

    /**
     * The payment references of the receipts of the period, by receipt, e.g. "Transfer: TRX-77, QRIS: 9981". Only receipts
     * with one are there, which keeps this small next to the receipts; a reference is free text, so it is read as it is
     * rather than packed into the receipt's own query.
     *
     * @return array<int, string>
     */
    private function referencesBySale(CarbonImmutable $from, CarbonImmutable $to, ?PaymentMethod $method): array
    {
        $references = [];

        $payments = $this->completedSales($from, $to, $method)
            ->join('sale_payments', 'sale_payments.sale_id', '=', 'sales.id')
            ->whereNotNull('sale_payments.reference')
            ->orderBy('sale_payments.id')
            ->select('sales.id as sale_id', 'sale_payments.method', 'sale_payments.reference')
            ->cursor();

        foreach ($payments as $payment) {
            $references[$payment->sale_id][] = PaymentMethod::from($payment->method)->label().': '.$payment->reference;
        }

        return array_map(fn (array $parts): string => implode(', ', $parts), $references);
    }

    /**
     * @return Sheet
     */
    private function goodsSheet(CarbonImmutable $from, CarbonImmutable $to, ?PaymentMethod $method): Generator
    {
        return $this->sheet(['Tanggal', 'No. nota', 'Barang', 'Jumlah', 'Satuan', 'Harga satuan', 'Subtotal'], $this->goodsRows($from, $to, $method), ['Subtotal'], fn (): string => 'JUMLAH');
    }

    /**
     * @return Generator<int, SheetRow>
     */
    private function goodsRows(CarbonImmutable $from, CarbonImmutable $to, ?PaymentMethod $method): Generator
    {
        $lines = $this->completedSales($from, $to, $method)
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->orderBy('sales.business_date')
            ->orderBy('sales.sold_at')
            ->orderBy('sales.id')
            ->orderBy('sale_items.id')
            ->select('sales.business_date', 'sales.number', 'sale_items.product_name', 'sale_items.quantity', 'sale_items.unit_name', 'sale_items.unit_price', 'sale_items.subtotal')
            ->cursor();

        foreach ($lines as $line) {
            yield [$line->business_date, $line->number, $line->product_name, (float) $line->quantity, $line->unit_name, (float) $line->unit_price, (float) $line->subtotal];
        }
    }

    /**
     * The completed receipts of the period, narrowed to those with a payment by the method when one is given.
     */
    private function completedSales(CarbonImmutable $from, CarbonImmutable $to, ?PaymentMethod $method): Builder
    {
        return DB::table('sales')
            ->where('sales.status', SaleStatus::Completed->value)
            ->whereBetween('sales.business_date', [$from->toDateString(), $to->toDateString()])
            ->when($method, fn (Builder $sales, PaymentMethod $method) => $sales->whereExists(fn (Builder $payments) => $payments
                ->selectRaw('1')
                ->from('sale_payments as method_payments')
                ->whereColumn('method_payments.sale_id', 'sales.id')
                ->where('method_payments.method', $method->value)));
    }

    /**
     * @param  list<array<string, mixed>>  $returns
     * @return Sheet
     */
    private function returnSheet(array $returns): Generator
    {
        $rows = (function () use ($returns): Generator {
            foreach ($returns as $return) {
                foreach ($return['items'] as $item) {
                    yield [$return['date'], $return['number'], $return['receipt_number'], $return['resolution'], $return['refund_method'], $return['reason'], $item['name'], (float) $item['quantity'], $item['unit'], (float) $item['refund_subtotal']];
                }
            }
        })();

        return $this->sheet(['Tanggal', 'No. retur', 'Nota asal', 'Penyelesaian', 'Metode pengembalian', 'Alasan', 'Barang', 'Jumlah', 'Satuan', 'Nilai retur'], $rows, ['Nilai retur'], fn (): string => 'JUMLAH');
    }

    /**
     * @param  list<array<string, mixed>>  $voids
     * @return Sheet
     */
    private function voidSheet(array $voids): Generator
    {
        $rows = (function () use ($voids): Generator {
            foreach ($voids as $void) {
                yield [$void['void_date'], $void['number'], $void['business_date'], $void['reason'], (float) $void['total']];
            }
        })();

        return $this->sheet(['Tanggal void', 'No. nota', 'Tanggal nota', 'Alasan', 'Total'], $rows, ['Total'], fn (): string => 'JUMLAH');
    }
}
