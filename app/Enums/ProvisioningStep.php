<?php

namespace App\Enums;

enum ProvisioningStep: string
{
    case Connection = 'connection';
    case Migration = 'migration';
    case Seed = 'seed';

    public function label(): string
    {
        return match ($this) {
            self::Connection => 'Uji koneksi database',
            self::Migration => 'Migrasi tabel',
            self::Seed => 'Gudang awal',
        };
    }
}
