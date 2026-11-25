<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lives in the store database. A photo or PDF of the supplier's nota kept with a purchase; `path` is on the private disk,
 * never a public address. `uploaded_by` is a central user id (no foreign key across databases).
 */
#[Fillable(['purchase_id', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'])]
class PurchaseAttachment extends Model
{
    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
