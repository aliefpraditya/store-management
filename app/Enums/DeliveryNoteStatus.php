<?php

namespace App\Enums;

enum DeliveryNoteStatus: string
{
    case Draft = 'draft';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case PartiallyDelivered = 'partially_delivered';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Shipped => 'Dikirim',
            self::Delivered => 'Terkirim',
            self::PartiallyDelivered => 'Sebagian terkirim',
            self::Returned => 'Kembali',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function canBeShipped(): bool
    {
        return $this === self::Draft;
    }

    public function canBeCompleted(): bool
    {
        return $this === self::Shipped;
    }

    /**
     * Can still be cancelled: nothing has been confirmed yet.
     */
    public function canBeCancelled(): bool
    {
        return $this->isUnfinished();
    }

    /**
     * Still needs someone to act on it (ship it, or confirm the delivery).
     */
    public function isUnfinished(): bool
    {
        return $this === self::Draft || $this === self::Shipped;
    }

    /**
     * Some of the goods reached the customer, so a COD sale with such a note counts as delivered. A note that came
     * back whole (Kembali) delivered nothing.
     */
    public function hasDeliveredGoods(): bool
    {
        return $this === self::Delivered || $this === self::PartiallyDelivered;
    }

    /**
     * @return list<self>
     */
    public static function withDeliveredGoods(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->hasDeliveredGoods()));
    }

    /**
     * @return list<self>
     */
    public static function unfinished(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isUnfinished()));
    }
}
