<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. One trip of whole purchases with a carrier, with the carrier's packing list as containers and
 * lines. Filling in `departure_date` is what makes it shipped and its purchases shipped with it. `created_by` is a central
 * user id (no foreign key across databases).
 */
#[Fillable(['client_uuid', 'number', 'carrier_id', 'vessel_name', 'departure_date', 'status', 'notes', 'created_by', 'created_by_name', 'shipped_at', 'cancel_reason', 'cancelled_at'])]
class Shipment extends Model implements AuditableContract
{
    /** @use HasFactory<ShipmentFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Carrier, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class)->orderBy('id');
    }

    /**
     * @return HasMany<ShipmentContainer, $this>
     */
    public function containers(): HasMany
    {
        return $this->hasMany(ShipmentContainer::class)->orderBy('id');
    }
}
