<?php

namespace App\Services;

use Generator;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes sheets of rows (see {@see SalesDetailSheetRows}) as an xlsx file, one sheet each: bold heading row held in place
 * while scrolling, a bold row of totals at the end, columns of a width that suits their heading, and amounts shown with
 * thousands separators and two decimals, since a price is not rounded in this app. Rows are written as they are read, so the
 * rows of a year are never held at once.
 *
 * The widths go by the heading, not by what the column holds, because the rows are not known when the width has to be
 * set (it must come before the first row).
 */
class SalesDetailWorkbook
{
    /** Widths by heading; a column with another heading (an amount) gets {@see self::AMOUNT_WIDTH}. */
    private const COLUMN_WIDTHS = [
        'Tanggal' => 12, 'Jam' => 8, 'No. nota' => 26, 'Pelanggan' => 22, 'Pengambilan' => 16, 'Barang' => 42, 'Satuan' => 10, 'Alasan' => 30,
        'Referensi pembayaran' => 36, 'No. retur' => 26, 'Nota asal' => 26, 'Penyelesaian' => 16, 'Metode pengembalian' => 20, 'Tanggal void' => 14, 'Tanggal nota' => 14,
    ];

    private const AMOUNT_WIDTH = 16;

    private const NUMBER_FORMAT = '#,##0.00';

    /**
     * @param  array<string, Generator<int, list<string|float|null>, mixed, list<string|float|null>|null>>  $sheets  Sheet name => its rows: the heading row first, then the rows, and, returned, the row of totals that closes it (`null` for none).
     * @param  string  $path  Where the file is written, such as `php://output`.
     */
    public function write(array $sheets, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $headingStyle = (new Style)->withFontBold(true)->withBackgroundColor(Color::rgb(229, 229, 229));
        $numberStyle = (new Style)->withFormat(self::NUMBER_FORMAT);
        $totalNumberStyle = $headingStyle->withFormat(self::NUMBER_FORMAT);
        $isFirstSheet = true;

        foreach ($sheets as $name => $rows) {
            $sheet = $isFirstSheet ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $isFirstSheet = false;

            $headings = $rows->current();

            $sheet->setName($name);
            $sheet->setSheetView((new SheetView)->withFreezeRow(2));

            foreach ($headings as $column => $heading) {
                $sheet->setColumnWidth((float) (self::COLUMN_WIDTHS[$heading] ?? self::AMOUNT_WIDTH), $column + 1);
            }

            $writer->addRow(Row::fromValuesWithStyle($headings, $headingStyle));
            $rows->next();

            while ($rows->valid()) {
                $writer->addRow(new Row(array_map(
                    fn (string|float|null $value): Cell => Cell::fromValue($value, is_float($value) ? $numberStyle : null),
                    $rows->current(),
                )));
                $rows->next();
            }

            $totals = $rows->getReturn();

            if ($totals !== null) {
                $writer->addRow(new Row(array_map(
                    fn (string|float|null $value): Cell => Cell::fromValue($value, is_float($value) ? $totalNumberStyle : $headingStyle),
                    $totals,
                )));
            }
        }

        $writer->close();
    }
}
