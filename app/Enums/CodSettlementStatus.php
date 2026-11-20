<?php

namespace App\Enums;

enum CodSettlementStatus: string
{
    case Confirmed = 'confirmed';
    case AwaitingApproval = 'awaiting_approval';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Lunas',
            self::AwaitingApproval => 'Menunggu persetujuan',
        };
    }
}
