<?php

namespace App\Models;

use App\Enums\DeliveryNoteStatus;
use Database\Factories\DeliveryNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. A delivery note never moves stock: the stock left when the receipt was made.
 * `created_by` is a central user id (no foreign key across databases).
 */
#[Fillable(['client_uuid', 'number', 'sale_id', 'business_date', 'status', 'recipient_name', 'recipient_phone', 'recipient_address', 'driver_name', 'vehicle_number', 'shipped_at', 'completed_at', 'notes', 'created_by', 'created_by_name'])]
class DeliveryNote extends Model implements AuditableContract
{
    /** @use HasFactory<DeliveryNoteFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => DeliveryNoteStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return HasMany<DeliveryNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }
}
