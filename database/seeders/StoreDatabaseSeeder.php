<?php

namespace Database\Seeders;

use App\Enums\WarehouseType;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Data every store database starts with. Safe to run again: existing rows are left alone.
 */
class StoreDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::query()->firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Warehouse', 'type' => WarehouseType::Sellable, 'is_default' => true, 'is_active' => true],
        );

        Warehouse::query()->firstOrCreate(
            ['code' => 'DEAD'],
            ['name' => 'Dead Stock', 'type' => WarehouseType::DeadStock, 'is_default' => false, 'is_active' => true],
        );
    }
}
