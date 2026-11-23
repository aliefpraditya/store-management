<?php

namespace App\Services;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes sheets of rows (see {@see SalesDetailSheetRows}) as an xlsx file, one sheet each: bold heading row held in place
 * while scrolling, columns as wide as what they hold, and amounts shown with thousands separators and two decimals, since
 * a price is not rounded in this app. Rows are streamed to the file, so a long period does not have to fit in memory twice.
 */
class SalesDetailWorkbook
{
    /** Widest a column is made, so one long note does not push the rest off the screen. */
    private const MAX_COLUMN_WIDTH = 50;

    private const MIN_COLUMN_WIDTH = 10;

    private const NUMBER_FORMAT = '#,##0.00';

    /**
     * @param  array<string, array{rows: list<list<string|float|null>>, total: list<string|float|null>|null}>  $sheets  Sheet name => its rows (the heading row first) and the row of totals that closes it, if any.
     * @param  string  $path  Where the file is written, such as `php://output`.
     */
    public function write(array $sheets, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $headingStyle = (new Style)->withFontBold(true)->withBackgroundColor(Color::rgb(229, 229, 229));
        $numberStyle = (new Style)->withFormat(self::NUMBER_FORMAT);
        $totalStyle = (new Style)->withFontBold(true)->withBackgroundColor(Color::rgb(229, 229, 229));
        $totalNumberStyle = $totalStyle->withFormat(self::NUMBER_FORMAT);
        $isFirstSheet = true;

        foreach ($sheets as $name => ['rows' => $rows, 'total' => $total]) {
            $sheet = $isFirstSheet ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $isFirstSheet = false;

            $sheet->setName($name);
            $sheet->setSheetView((new SheetView)->withFreezeRow(2));

            foreach ($this->columnWidths($total === null ? $rows : [...$rows, $total]) as $column => $width) {
                $sheet->setColumnWidth($width, $column + 1);
            }

            $writer->addRow(Row::fromValuesWithStyle($rows[0], $headingStyle));

            foreach (array_slice($rows, 1) as $values) {
                $writer->addRow(new Row(array_map(
                    fn (string|float|null $value): Cell => Cell::fromValue($value, is_float($value) ? $numberStyle : null),
                    $values,
                )));
            }

            if ($total !== null) {
                $writer->addRow(new Row(array_map(
                    fn (string|float|null $value): Cell => Cell::fromValue($value, is_float($value) ? $totalNumberStyle : $totalStyle),
                    $total,
                )));
            }
        }

        $writer->close();
    }

    /**
     * @param  list<list<string|float|null>>  $rows
     * @return list<float>
     */
    private function columnWidths(array $rows): array
    {
        $widths = [];

        foreach ($rows as $values) {
            foreach ($values as $column => $value) {
                $length = is_float($value) ? 14 : mb_strlen((string) $value) + 2;
                $widths[$column] = min(max($widths[$column] ?? self::MIN_COLUMN_WIDTH, $length), self::MAX_COLUMN_WIDTH);
            }
        }

        return array_map(floatval(...), $widths);
    }
}
