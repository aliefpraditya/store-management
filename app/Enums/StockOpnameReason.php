<?php

namespace App\Enums;

use App\Models\OpnameReason;

/**
 * What an opname as a whole is for. Only the three marked selectable can be chosen for a new opname; the others come
 * from before the reason of a variance was chosen per item (the item's own {@see OpnameReason}), and stay
 * so the opnames made then keep their label.
 */
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
    public function isArrivalCount(): bool
    {
        return $this === self::StockIncrease;
    }

    /**
     * Whether a new opname may be made for this reason.
     */
    public function isSelectable(): bool
    {
        return in_array($this, [self::Routine, self::OpeningBalance, self::StockIncrease], true);
    }

    /**
     * A routine count finds variances the admin has to explain and review item by item before posting: why (the item's
     * reason), and for damaged goods where they go. Arrivals and opening stock are the count itself, and the legacy
     * reasons were made before this existed.
     */
    public function requiresVarianceReview(): bool
    {
        return $this === self::Routine;
    }

    /**
     * @return list<self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $reason): bool => $reason->isSelectable()));
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
