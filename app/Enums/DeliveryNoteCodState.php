<?php

namespace App\Enums;

/**
 * Where the money of a delivery note's COD receipt stands, as the delivery note list shows it.
 */
enum DeliveryNoteCodState: string
{
    case NotDelivered = 'not_delivered';
    case Unreceived = 'unreceived';
    case AwaitingApproval = 'awaiting_approval';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::NotDelivered => 'Belum diantar',
            self::Unreceived => 'Uang belum diterima',
            self::AwaitingApproval => 'Menunggu persetujuan',
            self::Received => 'Uang diterima',
        };
    }
}
