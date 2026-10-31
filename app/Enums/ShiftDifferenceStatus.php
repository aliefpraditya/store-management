<?php

namespace App\Enums;

enum ShiftDifferenceStatus: string
{
    case None = 'none';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tidak ada selisih',
            self::PendingApproval => 'Menunggu persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }
}
