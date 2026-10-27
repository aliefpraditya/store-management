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

    /**
     * Still needs someone to act on it (ship it, or confirm the delivery).
     */
    public function isUnfinished(): bool
    {
        return $this === self::Draft || $this === self::Shipped;
    }

    /**
     * @return list<self>
     */
    public static function unfinished(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isUnfinished()));
    }
}
