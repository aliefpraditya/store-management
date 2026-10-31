<?php

namespace App\Enums;

enum SaleReturnStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu persetujuan',
            self::Completed => 'Selesai',
            self::Rejected => 'Ditolak',
        };
    }
}
