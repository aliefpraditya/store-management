<?php

namespace App\Enums;

/**
 * Where returned goods were sent when the cashier still chose it, kept for the returns made then. Returns made now carry
 * none: the goods wait in the returns warehouse for an opname to decide.
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
