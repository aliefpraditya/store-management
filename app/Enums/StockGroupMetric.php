<?php

namespace App\Enums;

/**
 * What the stock summary ranks the categories or brands by: what their sellable stock is worth, or how much of it there is.
 * A quantity only means something within one unit, since kilograms, sacks and pieces cannot be added up, so it is always
 * asked for in a single base unit.
 */
enum StockGroupMetric: string
{
    case Value = 'value';
    case Quantity = 'quantity';

    /** The column of the grouped rows that holds this measure, which the groups are put in order by. */
    public function column(): string
    {
        return match ($this) {
            self::Value => 'stock_value',
            self::Quantity => 'in_stock_quantity',
        };
    }
}
