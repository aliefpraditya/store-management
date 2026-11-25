<?php

namespace App\Enums;

/**
 * How a store paid a supplier for a purchase. Separate from `PaymentMethod`, which is how customers pay at the till.
 */
enum PurchasePaymentMethod: string
{
    case Transfer = 'transfer';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer',
            self::Cash => 'Tunai',
            self::Other => 'Lainnya',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $method): array => ['value' => $method->value, 'label' => $method->label()], self::cases());
    }
}
