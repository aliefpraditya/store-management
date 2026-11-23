<?php

namespace Tests\Unit;

use App\Services\SalesDetailSheetRows;
use PHPUnit\Framework\TestCase;

class SalesDetailSheetRowsTest extends TestCase
{
    /**
     * One receipt of 100.000 paid 60.000 by transfer and 50.000 in cash, with 10.000 given back as change.
     *
     * @return array<string, mixed>
     */
    private function report(): array
    {
        return [
            'days' => [[
                'date' => '2026-11-20',
                'receipts' => [[
                    'number' => 'MU-0001',
                    'time' => '09:15',
                    'customer_name' => 'Pak Budi',
                    'fulfillment' => 'Ambil di toko',
                    'subtotal' => '100000.00',
                    'discount_total' => '0.00',
                    'total' => '100000.00',
                    'change_amount' => '10000.00',
                    'items' => [
                        ['name' => 'Kuas', 'quantity' => '4.000', 'unit' => 'PCS', 'unit_price' => '10000.00', 'subtotal' => '40000.00'],
                        ['name' => 'Cat', 'quantity' => '1.000', 'unit' => 'KG', 'unit_price' => '60000.00', 'subtotal' => '60000.00'],
                    ],
                    'payments' => [
                        ['method' => 'transfer', 'label' => 'Transfer', 'amount' => '60000.00', 'reference' => 'TRX-77'],
                        ['method' => 'cash', 'label' => 'Tunai', 'amount' => '50000.00', 'reference' => null],
                    ],
                ]],
            ]],
            'voids' => [['number' => 'MU-0002', 'business_date' => '2026-11-19', 'void_date' => '2026-11-20', 'reason' => 'Salah input', 'total' => '25000.00']],
            'returns' => [['number' => 'RT-0001', 'receipt_number' => 'MU-0001', 'date' => '2026-11-21', 'resolution' => 'Refund', 'reason' => null, 'refund_method' => 'Tunai', 'credit_amount' => '10000.00', 'items' => [['name' => 'Kuas', 'quantity' => '1.000', 'unit' => 'PCS', 'refund_subtotal' => '10000.00']]]],
        ];
    }

    public function test_there_is_a_sheet_each_for_receipts_goods_returns_and_voids(): void
    {
        $this->assertSame(['Nota', 'Barang', 'Retur', 'Void'], array_keys((new SalesDetailSheetRows)->sheets($this->report())));
    }

    public function test_a_receipt_is_one_row_with_what_was_paid_split_by_method_and_cash_after_the_change(): void
    {
        $sheet = (new SalesDetailSheetRows)->sheets($this->report())['Nota']['rows'];
        $columns = array_flip($sheet[0]);
        $row = $sheet[1];

        $this->assertCount(2, $sheet);
        $this->assertSame(100000.0, $row[$columns['Total']]);
        $this->assertSame(40000.0, $row[$columns['Tunai']]);
        $this->assertSame(60000.0, $row[$columns['Transfer']]);
        $this->assertSame(0.0, $row[$columns['COD']]);
        $this->assertSame('Transfer: TRX-77', $row[$columns['Referensi pembayaran']]);
        $this->assertSame(100000.0, $row[$columns['Tunai']] + $row[$columns['Transfer']]);
    }

    public function test_every_goods_line_is_a_row_of_its_own_with_the_day_and_the_receipt(): void
    {
        $sheet = (new SalesDetailSheetRows)->sheets($this->report())['Barang']['rows'];

        $this->assertCount(3, $sheet);
        $this->assertSame(['2026-11-20', 'MU-0001', 'Kuas', 4.0, 'PCS', 10000.0, 40000.0], $sheet[1]);
        $this->assertSame(60000.0, $sheet[2][6]);
    }

    public function test_returns_and_voids_are_listed_with_their_amounts_as_numbers(): void
    {
        $sheets = (new SalesDetailSheetRows)->sheets($this->report());

        $this->assertSame(['2026-11-21', 'RT-0001', 'MU-0001', 'Refund', 'Tunai', null, 'Kuas', 1.0, 'PCS', 10000.0], $sheets['Retur']['rows'][1]);
        $this->assertSame(['2026-11-20', 'MU-0002', '2026-11-19', 'Salah input', 25000.0], $sheets['Void']['rows'][1]);
    }

    public function test_a_period_without_transactions_leaves_only_the_headings_and_no_total_row(): void
    {
        $sheets = (new SalesDetailSheetRows)->sheets(['days' => [], 'voids' => [], 'returns' => []]);

        foreach ($sheets as $sheet) {
            $this->assertCount(1, $sheet['rows']);
            $this->assertNull($sheet['total']);
        }
    }

    public function test_the_receipts_sheet_closes_with_the_sums_of_its_amount_columns_and_how_many_receipts(): void
    {
        $sheet = (new SalesDetailSheetRows)->sheets($this->report())['Nota'];
        $columns = array_flip($sheet['rows'][0]);

        $this->assertSame('JUMLAH (1 nota)', $sheet['total'][0]);
        $this->assertSame(100000.0, $sheet['total'][$columns['Total']]);
        $this->assertSame(40000.0, $sheet['total'][$columns['Tunai']]);
        $this->assertSame(60000.0, $sheet['total'][$columns['Transfer']]);
        $this->assertNull($sheet['total'][$columns['Pelanggan']]);
        $this->assertNull($sheet['total'][$columns['Referensi pembayaran']]);
    }

    public function test_the_goods_sheet_sums_only_the_subtotals_since_quantities_of_different_units_add_up_to_nothing(): void
    {
        $sheet = (new SalesDetailSheetRows)->sheets($this->report())['Barang'];

        $this->assertSame(['JUMLAH', null, null, null, null, null, 100000.0], $sheet['total']);
    }

    public function test_the_returns_and_voids_sheets_sum_their_amounts(): void
    {
        $sheets = (new SalesDetailSheetRows)->sheets($this->report());

        $this->assertSame(10000.0, $sheets['Retur']['total'][9]);
        $this->assertSame(25000.0, $sheets['Void']['total'][4]);
    }

    public function test_the_sums_of_cents_are_not_thrown_off_by_floating_point_error(): void
    {
        $report = $this->report();
        $report['days'][0]['receipts'][0]['items'] = [
            ['name' => 'A', 'quantity' => '1.000', 'unit' => 'PCS', 'unit_price' => '0.10', 'subtotal' => '0.10'],
            ['name' => 'B', 'quantity' => '1.000', 'unit' => 'PCS', 'unit_price' => '0.20', 'subtotal' => '0.20'],
        ];

        $this->assertSame(0.3, (new SalesDetailSheetRows)->sheets($report)['Barang']['total'][6]);
    }
}
