<?php

namespace App\Enums;

enum CashShiftStatus: string
{
    case Open = 'open';

    /** Counted and handed in, but the cash does not match: an admin has to review before the shift closes. */
    case PendingReview = 'pending_review';

    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Berjalan',
            self::PendingReview => 'Menunggu review',
            self::Closed => 'Ditutup',
        };
    }
}
