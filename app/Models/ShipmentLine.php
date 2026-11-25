<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. How much of one purchase line is in one container (`quantity` in the unit the line was bought
 * in, `base_quantity` in the base unit) and what the carrier charged for it (`freight_cost`, the packing list's price).
 */
#[Fillable(['purchase_item_id', 'quantity', 'base_quantity', 'freight_cost'])]
class ShipmentLine extends Model implements AuditableContract
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'base_quantity' => 'decimal:3', 'freight_cost' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<ShipmentContainer, $this>
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(ShipmentContainer::class, 'shipment_container_id');
    }

    /**
     * @return BelongsTo<PurchaseItem, $this>
     */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }
}
