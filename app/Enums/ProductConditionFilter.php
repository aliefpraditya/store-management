<?php

namespace App\Enums;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * The "normal / damaged goods (DG) / unit not confirmed" choice on the item list.
 */
enum ProductConditionFilter: string
{
    case Normal = 'normal';
    case DamagedGoods = 'damaged';
    case UnconfirmedUnits = 'unconfirmed_units';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::DamagedGoods => 'DG (barang rusak)',
            self::UnconfirmedUnits => 'Satuan tidak valid',
        };
    }

    /**
     * @param  Builder<Product>  $products
     * @return Builder<Product>
     */
    public function applyTo(Builder $products): Builder
    {
        return match ($this) {
            self::Normal => $products->where('products.is_damaged_goods', false),
            self::DamagedGoods => $products->where('products.is_damaged_goods', true),
            self::UnconfirmedUnits => $products->whereHas('productUnits', fn (Builder $units) => $units->unconfirmed()),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $condition): array => ['value' => $condition->value, 'label' => $condition->label()], self::cases());
    }
}
