<?php

namespace App\Enums;

/**
 * How the purchase price (modal) of many items is changed at once.
 */
enum CostChangeMethod: string
{
    case Percent = 'percent';
    case Nominal = 'nominal';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Persen (naik atau turun)',
            self::Nominal => 'Nominal Rp (naik atau turun)',
        };
    }
}
