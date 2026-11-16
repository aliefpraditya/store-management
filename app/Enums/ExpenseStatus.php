<?php

namespace App\Enums;

enum ExpenseStatus: string
{
    case Recorded = 'recorded';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Recorded => 'Tercatat',
            self::Voided => 'Dibatalkan',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status): array => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
