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

    /**
     * The database was never set up successfully: its credentials may still be fixed and setup retried.
     */
    public function isAwaitingProvisioning(): bool
    {
        return $this === self::Provisioning || $this === self::Failed;
    }

    /**
     * Only a store that finished setup can be switched on and off; stores are never deleted.
     */
    public function canBeToggled(): bool
    {
        return $this === self::Active || $this === self::Inactive;
    }
}
