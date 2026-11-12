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
     * @return list<array{value: string, label: string, takes_value: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $method): array => ['value' => $method->value, 'label' => $method->label(), 'takes_value' => $method->takesValue()], self::cases());
    }
}
