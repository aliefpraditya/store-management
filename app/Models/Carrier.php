<?php

namespace App\Models;

use Database\Factories\CarrierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database: a carrier (jasa logistik) that brings purchased goods to the store. `transit_days` is how
 * long it usually takes. A carrier is never deleted, only switched off, so the purchases sent with it keep their carrier.
 */
#[Fillable(['name', 'phone', 'address', 'province', 'city', 'transit_days', 'notes', 'is_active'])]
class Carrier extends Model implements AuditableContract
{
    /** @use HasFactory<CarrierFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'transit_days' => 'integer'];
    }

    /**
     * @return HasMany<CarrierContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CarrierContact::class)->orderBy('id');
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
