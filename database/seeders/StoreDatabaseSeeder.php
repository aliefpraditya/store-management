<?php

namespace Database\Seeders;

use App\Enums\WarehouseType;
use App\Models\ExpenseCategory;
use App\Models\OpnameReason;
use App\Models\Register;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Data every store database starts with: the three warehouses (main, dead stock, returns), the first cash register (K1, selling from the main
 * warehouse), the usual expense categories and the reasons of an opname variance. Safe to run again: existing rows are
 * left alone.
 */
class StoreDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ExpenseCategory::DEFAULT_NAMES as $categoryName) {
            ExpenseCategory::query()->firstOrCreate(['name' => $categoryName], ['is_active' => true]);
        }

        foreach (OpnameReason::DEFAULTS as $reasonName => $behavior) {
            OpnameReason::query()->firstOrCreate(['name' => $reasonName], ['behavior' => $behavior, 'is_system' => true, 'is_active' => true]);
        }

        $mainWarehouse = Warehouse::query()->firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Warehouse', 'type' => WarehouseType::Sellable, 'is_default' => true, 'is_active' => true],
        );

        Warehouse::query()->firstOrCreate(
            ['code' => 'DEAD'],
            ['name' => 'Dead Stock', 'type' => WarehouseType::DeadStock, 'is_default' => false, 'is_active' => true],
        );

        Warehouse::query()->firstOrCreate(
            ['code' => 'RETUR'],
            ['name' => 'Retur', 'type' => WarehouseType::Returns, 'returns_for_warehouse_id' => $mainWarehouse->id, 'is_default' => false, 'is_active' => true],
        );

        Register::query()->firstOrCreate(
            ['code' => 'K1'],
            ['name' => 'Kasir 1', 'warehouse_id' => $mainWarehouse->id, 'is_active' => true],
        );
    }
}
