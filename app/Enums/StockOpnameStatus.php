<?php

namespace App\Enums;

enum StockOpnameStatus: string
{
    case Counting = 'counting';
    case Review = 'review';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
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
        return in_array($this, [self::Counting, self::Review], true);
    }

    /**
     * An opname whose products are being counted: the stock of those products is not to be sold while the store has
     * chosen to lock sales during opname. A posted or cancelled one is over.
     */
    public function isCounting(): bool
    {
        return $this === self::Counting || $this === self::Review;
    }

    /**
     * @return list<string>
     */
    public static function countingValues(): array
    {
        return array_values(array_map(fn (self $status): string => $status->value, array_filter(self::cases(), fn (self $status): bool => $status->isCounting())));
    }

    /**
     * An opname cancelled while still being counted is erased for good: nothing was posted and no review was done, so
     * there is nothing to keep. Once it is in review it is soft deleted instead, hidden from the list but kept as a
     * record.
     */
    public function isErasedWhenCancelled(): bool
    {
        return $this === self::Counting;
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_map(fn (self $status): string => $status->value, array_filter(self::cases(), fn (self $status): bool => $status->isActive()));
    }
}
