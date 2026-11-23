<?php

namespace App\Enums;

/**
 * How the goods of a receipt reach the customer: taken from the store right away, or delivered. Only a delivered receipt
 * has delivery notes, and only a delivered receipt can be paid on delivery (COD).
 */
enum SaleFulfillment: string
{
    case InStore = 'in_store';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::InStore => 'Ambil di toko',
            self::Delivery => 'Diantar',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $fulfillment): array => ['value' => $fulfillment->value, 'label' => $fulfillment->label()], self::cases());
    }
}
