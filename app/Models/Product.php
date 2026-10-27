<?php

namespace App\Models;

use App\Enums\WarehouseType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. Quantities are in the base unit.
 */
class Product extends Model implements AuditableContract
{
    /** @use HasFactory<ProductFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Stock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * Active products whose stock in the sellable warehouses is under their minimum.
     * Products without a minimum are never "low".
     *
     * @param  Builder<Product>  $query
     */
    public function scopeBelowMinimumStock(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('min_stock', '>', 0)
            ->whereRaw(
                '(select coalesce(sum(stocks.quantity), 0) from stocks
                    join warehouses on warehouses.id = stocks.warehouse_id
                    where stocks.product_id = products.id and warehouses.type = ?) < products.min_stock',
                [WarehouseType::Sellable->value],
            );
    }
}
