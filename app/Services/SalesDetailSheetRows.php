<?php

namespace App\Services;

use App\Enums\PaymentMethod;

/**
 * Lays the detailed sales report out as flat sheets for a spreadsheet: one row per receipt, one per goods line, one per
 * returned line and one per voided receipt, so the sheets can be filtered and summed in the spreadsheet without a line being
 * counted twice. It holds only what a transaction list needs (subtotals, no cost or margin) and knows no file format: a writer
 * turns the rows into a file. Amounts and quantities are numbers, days are `Y-m-d`.
 *
 * Each receipt row splits what was paid per method, cash after the change given back, so the method columns add up to the
 * receipt's total the same way the methods of the report do.
 *
 * @phpstan-import-type DetailReceipt from SalesDetailReportService
 */
class SalesDetailSheetRows
{
    /**
     * @param  array{days: list<array{date: string, receipts: list<DetailReceipt>}>, voids: list<array<string, mixed>>, returns: list<array<string, mixed>>}  $report  What {@see SalesDetailReportService::report()} gives.
     * @return array<string, array{rows: list<list<string|float|null>>, total: list<string|float|null>|null}> Sheet name => its rows, the heading row first, and the row of totals that closes it (none when the sheet has no transaction).
     */
    public function sheets(array $report): array
    {
        $paymentLabels = array_map(fn (PaymentMethod $method): string => $method->label(), PaymentMethod::cases());
        $receiptRows = $this->receiptRows($report['days']);

        return [
            'Nota' => $this->withTotal($receiptRows, ['Subtotal', 'Diskon', 'Total', ...$paymentLabels], 'JUMLAH ('.(count($receiptRows) - 1).' nota)'),
            'Barang' => $this->withTotal($this->goodsRows($report['days']), ['Subtotal'], 'JUMLAH'),
            'Retur' => $this->withTotal($this->returnRows($report['returns']), ['Nilai retur'], 'JUMLAH'),
            'Void' => $this->withTotal($this->voidRows($report['voids']), ['Total'], 'JUMLAH'),
        ];
    }

    /**
     * The sheet with the row of totals that closes it: the label in the first column and, under each of the named columns,
     * the sum of its rows. Only amounts are summed: quantities of different units and unit prices add up to nothing.
     *
     * @param  list<list<string|float|null>>  $rows  The heading row first.
     * @param  list<string>  $summedHeadings
     * @return array{rows: list<list<string|float|null>>, total: list<string|float|null>|null}
     */
    private function withTotal(array $rows, array $summedHeadings, string $label): array
    {
        $dataRows = array_slice($rows, 1);

        if ($dataRows === []) {
            return ['rows' => $rows, 'total' => null];
        }

        $total = array_map(
            fn (string $heading, int $column): string|float|null => in_array($heading, $summedHeadings, true) ? round(array_sum(array_column($dataRows, $column)), 2) : null,
            $rows[0],
            array_keys($rows[0]),
        );
        $total[0] = $label;

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @param  list<array{date: string, receipts: list<DetailReceipt>}>  $days
     * @return list<list<string|float|null>>
     */
    private function receiptRows(array $days): array
    {
        $rows = [['Tanggal', 'Jam', 'No. nota', 'Pelanggan', 'Pengambilan', 'Subtotal', 'Diskon', 'Total', ...array_map(fn (PaymentMethod $method): string => $method->label(), PaymentMethod::cases()), 'Referensi pembayaran']];

        foreach ($days as $day) {
            foreach ($day['receipts'] as $receipt) {
                $rows[] = [
                    $day['date'],
                    $receipt['time'],
                    $receipt['number'],
                    $receipt['customer_name'],
                    $receipt['fulfillment'],
                    (float) $receipt['subtotal'],
                    (float) $receipt['discount_total'],
                    (float) $receipt['total'],
                    ...array_map(fn (PaymentMethod $method): float => $this->paidBy($receipt, $method), PaymentMethod::cases()),
                    $this->references($receipt),
                ];
            }
        }

        return $rows;
    }

    /**
     * What was paid by one method; cash is what the customer handed over less the change given back.
     *
     * @param  DetailReceipt  $receipt
     */
    private function paidBy(array $receipt, PaymentMethod $method): float
    {
        $paid = array_sum(array_map(
            fn (array $payment): float => (float) $payment['amount'],
            array_filter($receipt['payments'], fn (array $payment): bool => $payment['method'] === $method->value),
        ));

        return $method === PaymentMethod::Cash ? $paid - (float) $receipt['change_amount'] : $paid;
    }

    /**
     * @param  DetailReceipt  $receipt
     */
    private function references(array $receipt): ?string
    {
        $references = array_filter(array_map(
            fn (array $payment): ?string => $payment['reference'] === null ? null : "{$payment['label']}: {$payment['reference']}",
            $receipt['payments'],
        ));

        return $references === [] ? null : implode(', ', $references);
    }

    /**
     * @param  list<array{date: string, receipts: list<DetailReceipt>}>  $days
     * @return list<list<string|float|null>>
     */
    private function goodsRows(array $days): array
    {
        $rows = [['Tanggal', 'No. nota', 'Barang', 'Jumlah', 'Satuan', 'Harga satuan', 'Subtotal']];

        foreach ($days as $day) {
            foreach ($day['receipts'] as $receipt) {
                foreach ($receipt['items'] as $item) {
                    $rows[] = [$day['date'], $receipt['number'], $item['name'], (float) $item['quantity'], $item['unit'], (float) $item['unit_price'], (float) $item['subtotal']];
                }
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $returns
     * @return list<list<string|float|null>>
     */
    private function returnRows(array $returns): array
    {
        $rows = [['Tanggal', 'No. retur', 'Nota asal', 'Penyelesaian', 'Metode pengembalian', 'Alasan', 'Barang', 'Jumlah', 'Satuan', 'Nilai retur']];

        foreach ($returns as $return) {
            foreach ($return['items'] as $item) {
                $rows[] = [$return['date'], $return['number'], $return['receipt_number'], $return['resolution'], $return['refund_method'], $return['reason'], $item['name'], (float) $item['quantity'], $item['unit'], (float) $item['refund_subtotal']];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $voids
     * @return list<list<string|float|null>>
     */
    private function voidRows(array $voids): array
    {
        $rows = [['Tanggal void', 'No. nota', 'Tanggal nota', 'Alasan', 'Total']];

        foreach ($voids as $void) {
            $rows[] = [$void['void_date'], $void['number'], $void['business_date'], $void['reason'], (float) $void['total']];
        }

        return $rows;
    }
}
