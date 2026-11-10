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
 * units one of this unit holds; the base unit itself is 1.
 * A sealed pack (`is_sealed_pack`) is a unit that cannot be put back together once cut, such as a roll of wire: the
 * product's stock then also counts whole packs, see {@see Stock}.
 */
#[Fillable(['product_id', 'unit_code', 'conversion_factor', 'is_base', 'sort_order', 'is_sellable', 'cost_price', 'selling_price', 'is_default_sale', 'is_sealed_pack'])]
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
            'is_sealed_pack' => 'boolean',
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
