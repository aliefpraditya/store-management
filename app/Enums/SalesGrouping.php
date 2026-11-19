<?php

namespace App\Enums;

/**
 * What the sales report groups the products by: the category or the brand they belong to.
 */
enum SalesGrouping: string
{
    case Category = 'category';
    case Brand = 'brand';

    /** The column of `products` that holds the group of a product. */
    public function productColumn(): string
    {
        return match ($this) {
            self::Category => 'category_id',
            self::Brand => 'brand_id',
        };
    }

    /** What the products without a group are listed under. */
    public function emptyLabel(): string
    {
        return match ($this) {
            self::Category => '(Tanpa kategori)',
            self::Brand => '(Tanpa merek)',
        };
    }
}
