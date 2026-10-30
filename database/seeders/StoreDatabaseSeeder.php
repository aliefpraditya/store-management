<?php

namespace Database\Seeders;

use App\Enums\WarehouseType;
use App\Models\Register;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Data every store database starts with: the two warehouses and the first cash register (K1, selling from the main
 * warehouse, with no fixed opening cash until an admin sets one). Safe to run again: existing rows are left alone.
 */
class StoreDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $mainWarehouse = Warehouse::query()->firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Warehouse', 'type' => WarehouseType::Sellable, 'is_default' => true, 'is_active' => true],
        );

        Warehouse::query()->firstOrCreate(
            ['code' => 'DEAD'],
            ['name' => 'Dead Stock', 'type' => WarehouseType::DeadStock, 'is_default' => false, 'is_active' => true],
        );

        Register::query()->firstOrCreate(
            ['code' => 'K1'],
            ['name' => 'Kasir 1', 'warehouse_id' => $mainWarehouse->id, 'fixed_opening_cash' => 0, 'is_active' => true],
        );
    }
}
