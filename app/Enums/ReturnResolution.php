<?php

namespace App\Enums;

/**
 * How a return is settled with the customer: other goods instead (exchange, the default at the till) or the money back.
 */
enum ReturnResolution: string
{
    case Exchange = 'exchange';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Exchange => 'Tukar barang',
            self::Refund => 'Refund',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $resolution): array => ['value' => $resolution->value, 'label' => $resolution->label()], self::cases());
    }
}
