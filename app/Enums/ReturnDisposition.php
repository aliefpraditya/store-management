<?php

namespace App\Enums;

/**
 * Where returned goods go: back on the shelf to be sold again, or to the dead-stock warehouse.
 */
enum ReturnDisposition: string
{
    case Resellable = 'resellable';
    case DeadStock = 'dead_stock';

    public function label(): string
    {
        return match ($this) {
            self::Resellable => 'Dijual lagi',
            self::DeadStock => 'Dead stock',
        };
    }

    public function warehouseType(): WarehouseType
    {
        return match ($this) {
            self::Resellable => WarehouseType::Sellable,
            self::DeadStock => WarehouseType::DeadStock,
        };
    }
}
