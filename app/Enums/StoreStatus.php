<?php

namespace App\Enums;

enum StoreStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Inactive = 'inactive';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Provisioning => 'Disiapkan',
            self::Active => 'Aktif',
            self::Inactive => 'Nonaktif',
            self::Failed => 'Gagal',
        };
    }
}
