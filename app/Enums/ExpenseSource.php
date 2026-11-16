<?php

namespace App\Enums;

enum ExpenseSource: string
{
    case Drawer = 'drawer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Drawer => 'Laci kasir',
            self::Other => 'Lainnya',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $source): array => ['value' => $source->value, 'label' => $source->label()], self::cases());
    }
}
