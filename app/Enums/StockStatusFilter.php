<?php

namespace App\Enums;

use App\Models\Product;
use App\Services\StockReportService;
use Illuminate\Database\Eloquent\Builder;

/**
 * The stock conditions the stock report's table can be narrowed to.
 */
enum StockStatusFilter: string
{
    case BelowMinimum = 'below_minimum';
    case OutOfStock = 'out_of_stock';
    case Negative = 'negative';
    case DeadStock = 'dead_stock';
    case ReturnsWaiting = 'returns_waiting';

    public function label(): string
    {
        return match ($this) {
            self::BelowMinimum => 'Di bawah minimum',
            self::OutOfStock => 'Stok habis',
            self::Negative => 'Stok negatif',
            self::DeadStock => 'Ada dead stock',
            self::ReturnsWaiting => 'Retur menunggu diperiksa',
        };
    }

    /**
     * Narrows products that carry the stock columns of {@see StockReportService} (`sellable_stock`,
     * `dead_stock`, `returns_stock`, `is_below_minimum` and `has_negative_stock`). An inactive product is never
     * out of stock or low: it is no longer sold, so there is nothing to restock.
     *
     * @param  Builder<Product>  $products
     */
    public function narrow(Builder $products): void
    {
        match ($this) {
            self::BelowMinimum => $products->where('is_active', true)->where('is_below_minimum', true),
            self::OutOfStock => $products->where('is_active', true)->where('sellable_stock', 0),
            self::Negative => $products->where('has_negative_stock', true),
            self::DeadStock => $products->where('dead_stock', '>', 0),
            self::ReturnsWaiting => $products->where('returns_stock', '>', 0),
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
