<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lives in the store database. A file of the carrier's packing list kept with a shipment; `path` is on the private disk, never
 * a public address. `uploaded_by` is a central user id (no foreign key across databases).
 */
#[Fillable(['shipment_id', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'])]
class ShipmentAttachment extends Model
{
    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
