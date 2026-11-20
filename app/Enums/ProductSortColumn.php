<?php

namespace App\Enums;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Builder;

/**
 * The columns the item list can be ordered by. The stock columns order by the totals that
 * {@see Product::scopeWithStockTotals()} adds, so the list must be queried with them.
 */
enum ProductSortColumn: string
{
    case Sku = 'sku';
    case Name = 'name';
    case Category = 'category';
    case Brand = 'brand';
    case SellableStock = 'sellable_stock';
    case DeadStock = 'dead_stock';
    case CostPrice = 'cost_price';
    case SellingPrice = 'selling_price';
    case Status = 'status';

    /**
     * Text runs A to Z, figures and the active status start with the biggest.
     *
     * @return 'asc'|'desc'
     */
    public function defaultDirection(): string
    {
        return match ($this) {
            self::Sku, self::Name, self::Category, self::Brand => 'asc',
            self::SellableStock, self::DeadStock, self::CostPrice, self::SellingPrice, self::Status => 'desc',
        };
    }

    /**
     * @return 'asc'|'desc'
     */
    public function directionOrDefault(?string $requested): string
    {
        return in_array($requested, ['asc', 'desc'], true) ? $requested : $this->defaultDirection();
    }

    /**
     * Items that tie keep the name order, so a page never shuffles between two visits.
     *
     * @param  Builder<Product>  $query
     */
    public function applyTo(Builder $query, string $direction): void
    {
        match ($this) {
            self::Sku => $query->orderBy('products.sku', $direction),
            self::Name => $query->orderBy('products.name', $direction),
            self::Category => $query->orderBy(Category::query()->select('name')->whereColumn('categories.id', 'products.category_id'), $direction),
            self::Brand => $query->orderBy(Brand::query()->select('name')->whereColumn('brands.id', 'products.brand_id'), $direction),
            self::SellableStock => $query->orderBy('sellable_stock', $direction),
            self::DeadStock => $query->orderBy('dead_stock', $direction),
            self::CostPrice => $query->orderBy('products.cost_price', $direction),
            self::SellingPrice => $query->orderBy(ProductUnit::query()->select('selling_price')->whereColumn('product_units.product_id', 'products.id')->where('is_default_sale', true)->limit(1), $direction),
            self::Status => $query->orderBy('products.is_active', $direction),
        };

        $query->orderBy('products.name')->orderBy('products.id');
    }
}
