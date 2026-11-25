<?php

namespace App\Models;

use Database\Factories\SupplierContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database: a person to call at a supplier (usually a salesperson). A supplier can have several, and
 * unlike the supplier itself a contact may be deleted, since nothing else refers to it.
 */
#[Fillable(['name', 'phone'])]
class SupplierContact extends Model implements AuditableContract
{
    /** @use HasFactory<SupplierContactFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
