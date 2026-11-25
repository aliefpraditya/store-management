<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database: who a store buys from. A supplier is never deleted, only switched off, so the purchases made
 * from it keep their supplier.
 */
#[Fillable(['name', 'phone', 'address', 'province', 'city', 'email', 'tax_number', 'notes', 'is_active'])]
class Supplier extends Model implements AuditableContract
{
    /** @use HasFactory<SupplierFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<SupplierContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class)->orderBy('id');
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
