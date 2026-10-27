<?php

namespace App\Models;

use App\Enums\DeliveryNoteStatus;
use Database\Factories\DeliveryNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Lives in the store database. A delivery note never moves stock.
 */
class DeliveryNote extends Model
{
    /** @use HasFactory<DeliveryNoteFactory> */
    use HasFactory;

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
}
