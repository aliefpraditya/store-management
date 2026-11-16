<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Edc = 'edc';
    case ReturnCredit = 'return_credit';
    case Cod = 'cod';
    case OrderDeposit = 'order_deposit';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Transfer => 'Transfer',
            self::Qris => 'QRIS',
            self::Edc => 'EDC',
            self::ReturnCredit => 'Kredit retur',
            self::Cod => 'COD',
            self::OrderDeposit => 'Uang muka pesanan',
        };
    }

    /**
     * Methods money can come in or go back by: what a refund, an exchange payment or the drawer summary works with.
     * Return credit only comes from an exchange, COD is not money received yet, and an order deposit was already
     * received when the order was made.
     *
     * @return list<self>
     */
    public static function forCashier(): array
    {
        return [self::Cash, self::Transfer, self::Qris, self::Edc];
    }

    /**
     * Methods a cashier can pick for a sale at the payment screen: the money methods and COD, which is paid when the
     * goods are delivered.
     *
     * @return list<self>
     */
    public static function forSale(): array
    {
        return [...self::forCashier(), self::Cod];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function saleOptions(): array
    {
        return array_map(fn (self $method): array => ['value' => $method->value, 'label' => $method->label()], self::forSale());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function cashierOptions(): array
    {
        return array_map(fn (self $method): array => ['value' => $method->value, 'label' => $method->label()], self::forCashier());
    }
}
