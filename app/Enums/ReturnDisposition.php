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

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $disposition): array => ['value' => $disposition->value, 'label' => $disposition->label()], self::cases());
    }

    public function warehouseType(): WarehouseType
    {
        return match ($this) {
            self::Resellable => WarehouseType::Sellable,
            self::DeadStock => WarehouseType::DeadStock,
        };
    }
}
