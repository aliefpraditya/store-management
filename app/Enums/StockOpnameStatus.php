<?php

namespace App\Enums;

enum StockOpnameStatus: string
{
    case Draft = 'draft';
    case Counting = 'counting';
    case Review = 'review';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Counting => 'Menghitung',
            self::Review => 'Review',
            self::Posted => 'Diposting',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * An opname that still holds its products: no other opname may count them in the same warehouse.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Draft, self::Counting, self::Review], true);
    }

    /**
     * A cancelled draft is erased for good: nothing was counted, so there is nothing to keep. Any later status is
     * soft deleted instead, hidden from the list but kept as a record.
     */
    public function isErasedWhenCancelled(): bool
    {
        return $this === self::Draft;
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_map(fn (self $status): string => $status->value, array_filter(self::cases(), fn (self $status): bool => $status->isActive()));
    }
}
