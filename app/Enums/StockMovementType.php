<?php

namespace App\Enums;

enum StockMovementType: string
{
    case OpeningBalance = 'opening_balance';
    case Sale = 'sale';
    case SaleVoid = 'sale_void';
    case SaleReturn = 'sale_return';
    case OpnameAdjustment = 'opname_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => 'Stok awal',
            self::Sale => 'Penjualan',
            self::SaleVoid => 'Void nota',
            self::SaleReturn => 'Retur',
            self::OpnameAdjustment => 'Opname',
        };
    }
}
