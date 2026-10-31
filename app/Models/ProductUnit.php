<?php

namespace App\Models;

use Database\Factories\ProductUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database: one unit a product is sold in. `conversion_factor` is how many base
 * units one of this unit holds; the base unit itself is 1. The barcode identifies this unit, not the product.
 */
#[Fillable(['product_id', 'unit_code', 'conversion_factor', 'is_base', 'barcode', 'sort_order', 'is_sellable', 'cost_price', 'selling_price', 'is_default_sale'])]
class ProductUnit extends Model implements AuditableContract
{
    /** @use HasFactory<ProductUnitFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:3',
            'is_base' => 'boolean',
            'is_sellable' => 'boolean',
            'is_default_sale' => 'boolean',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_code');
    }
}
