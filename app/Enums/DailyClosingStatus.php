<?php

namespace App\Enums;

enum DailyClosingStatus: string
{
    case Closed = 'closed';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::Closed => 'Ditutup',
            self::Reopened => 'Dibuka kembali',
        };
    }
}
