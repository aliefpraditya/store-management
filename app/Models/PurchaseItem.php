<?php

namespace App\Models;

use Database\Factories\PurchaseItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. One product on a purchase, in the unit it was bought in. The unit code and its
 * `conversion_factor` (base units in one of it) are kept as they were bought, and `base_quantity` and `base_unit_cost` are the
 * same line in the product's base unit, which is what stock and cost are kept in. `received_base_quantity` is how much of it
 * has arrived so far.
 */
#[Fillable(['purchase_id', 'product_id', 'unit_code', 'conversion_factor', 'quantity', 'base_quantity', 'unit_cost', 'base_unit_cost', 'subtotal', 'received_base_quantity', 'notes'])]
class PurchaseItem extends Model implements AuditableContract
{
    /** @use HasFactory<PurchaseItemFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:3',
            'quantity' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'base_unit_cost' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'received_base_quantity' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
