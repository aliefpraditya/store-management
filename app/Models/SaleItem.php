<?php

namespace App\Models;

use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lives in the store database. One line of a receipt. The name, unit, conversion and price are copied at the
 * time of sale, so later edits to the product do not change old receipts. `base_quantity` is what left the stock.
 */
#[Fillable(['sale_id', 'product_id', 'product_unit_id', 'product_name', 'unit_name', 'quantity', 'conversion_snapshot', 'base_quantity', 'unit_price', 'cost_total', 'is_cost_estimated', 'is_manual_price', 'subtotal'])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'conversion_snapshot' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'cost_total' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'is_cost_estimated' => 'boolean',
            'is_manual_price' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
