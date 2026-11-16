<?php

namespace App\Enums;

/**
 * The "normal / damaged goods (DG)" choice on the item list.
 */
enum ProductConditionFilter: string
{
    case Normal = 'normal';
    case DamagedGoods = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::DamagedGoods => 'DG (barang rusak)',
        };
    }

    public function isDamagedGoods(): bool
    {
        return $this === self::DamagedGoods;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $condition): array => ['value' => $condition->value, 'label' => $condition->label()], self::cases());
    }
}
