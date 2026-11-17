<?php

namespace App\Models;

use App\Enums\DeliveryNoteStatus;
use Database\Factories\DeliveryNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. A delivery note never moves stock: the stock left when the receipt was made.
 * `created_by` is a central user id (no foreign key across databases). `driver_name` is only written when the note is
 * shipped (a snapshot of the chosen driver); `vehicle_number` is no longer asked for and is kept for the notes made
 * while it still was.
 */
#[Fillable(['client_uuid', 'number', 'sale_id', 'business_date', 'status', 'recipient_name', 'recipient_phone', 'recipient_address', 'shipped_at', 'completed_at', 'notes', 'created_by', 'created_by_name'])]
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
     * A note always carries the goods of its own receipt, so the link is made as soon as the note exists.
     */
    protected static function booted(): void
    {
        static::created(fn (self $note) => $note->sales()->syncWithoutDetaching([$note->sale_id]));
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Every receipt that has goods on this note: its own, and the receipt made together with the order it collected when
     * that receipt's goods go out on the same note.
     *
     * @return BelongsToMany<Sale, $this>
     */
    public function sales(): BelongsToMany
    {
        return $this->belongsToMany(Sale::class, 'delivery_note_sales');
    }

    /**
     * @return HasMany<DeliveryNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }
}
