<?php

namespace App\Models;

use Database\Factories\CarrierContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database: a person to call at a carrier (customer service, a driver, a branch). A carrier can have
 * several, and unlike the carrier itself a contact may be deleted, since nothing else refers to it.
 */
#[Fillable(['name', 'phone'])]
class CarrierContact extends Model implements AuditableContract
{
    /** @use HasFactory<CarrierContactFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<Carrier, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }
}
