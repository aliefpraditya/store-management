<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Edc = 'edc';
    case ReturnCredit = 'return_credit';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Transfer => 'Transfer',
            self::Qris => 'QRIS',
            self::Edc => 'EDC',
            self::ReturnCredit => 'Kredit retur',
        };
    }

    /**
     * Methods a cashier picks at the payment screen; return credit only comes from an exchange.
     *
     * @return list<self>
     */
    public static function forCashier(): array
    {
        return [self::Cash, self::Transfer, self::Qris, self::Edc];
    }
}
