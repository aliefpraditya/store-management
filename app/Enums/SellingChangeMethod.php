<?php

namespace App\Enums;

/**
 * How the selling price of many items is changed at once. The two margin methods price from the modal as it will be
 * after the same run, so a modal change and a margin rule can go together.
 */
enum SellingChangeMethod: string
{
    case Percent = 'percent';
    case Nominal = 'nominal';
    case Set = 'set';
    case Margin = 'margin';
    case KeepMargin = 'keep_margin';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Persen (naik atau turun)',
            self::Nominal => 'Nominal Rp (naik atau turun)',
            self::Set => 'Setarakan ke harga tertentu',
            self::Margin => 'Atur margin',
            self::KeepMargin => 'Pertahankan margin sekarang',
        };
    }

    /**
     * Whether the method needs a number typed next to it; keeping the margin does not.
     */
    public function takesValue(): bool
    {
        return $this !== self::KeepMargin;
    }

    /**
     * Whether the price comes from a rule (a percent, a margin) and so may be rounded up to the store's selling price
     * step. A Rupiah amount the admin typed (nominal, set) is saved as typed.
     */
    public function isDerivedFromRule(): bool
    {
        return match ($this) {
            self::Percent, self::Margin, self::KeepMargin => true,
            self::Nominal, self::Set => false,
        };
    }

    /**
     * @return list<array{value: string, label: string, takes_value: bool, is_derived_from_rule: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $method): array => [
            'value' => $method->value,
            'label' => $method->label(),
            'takes_value' => $method->takesValue(),
            'is_derived_from_rule' => $method->isDerivedFromRule(),
        ], self::cases());
    }
}
