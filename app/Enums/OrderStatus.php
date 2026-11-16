<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Ordered = 'ordered';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Ordered => 'Dipesan',
            self::Ready => 'Siap diambil',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Orders still open: waiting for the goods, or waiting for the customer.
     *
     * @return list<self>
     */
    public static function unfinished(): array
    {
        return [self::Ordered, self::Ready];
    }

    public function isUnfinished(): bool
    {
        return in_array($this, self::unfinished(), true);
    }
}
