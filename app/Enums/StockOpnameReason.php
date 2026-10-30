<?php

namespace App\Enums;

enum StockOpnameReason: string
{
    case Routine = 'routine';
    case OpeningBalance = 'opening_balance';
    case StockIncrease = 'stock_increase';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case InterStoreTransfer = 'inter_store_transfer';
    case Correction = 'correction';

    /**
     * With this reason the admin types what arrived, not the total on the shelf, and that quantity is added to the stock.
     */
    public function countsArrivals(): bool
    {
        return $this === self::StockIncrease;
    }

    public function label(): string
    {
        return match ($this) {
            self::Routine => 'Opname Rutin',
            self::OpeningBalance => 'Stok awal',
            self::StockIncrease => 'Penambahan stok',
            self::Damaged => 'Rusak',
            self::Lost => 'Hilang',
            self::InterStoreTransfer => 'Transfer antar toko',
            self::Correction => 'Koreksi',
        };
    }
}
