<?php

namespace App\Models;

use Database\Factories\DeliveryNoteItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. How much of one receipt line goes out on a delivery note. The name and unit are
 * copied from the receipt line. `quantity_delivered` and `quantity_returned` are filled when the delivery ends;
 * together they equal `quantity`. Only changes are audited, which is where what arrived and what came back is recorded.
 */
#[Fillable(['delivery_note_id', 'sale_item_id', 'product_id', 'product_unit_id', 'product_name', 'unit_name', 'quantity', 'quantity_delivered', 'quantity_returned'])]
class DeliveryNoteItem extends Model implements AuditableContract
{
    /** @use HasFactory<DeliveryNoteItemFactory> */
    use Auditable, HasFactory;

    /** @var list<string> */
    protected array $auditEvents = ['updated'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'quantity_delivered' => 'decimal:3',
            'quantity_returned' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<DeliveryNote, $this>
     */
    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    /**
     * @return BelongsTo<SaleItem, $this>
     */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
