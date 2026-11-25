<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. What a store orders from one supplier (the nota). It changes no stock and no cost price:
 * that happens when the goods are received. `warehouse_id` is where the goods are meant to go by default. `created_by` and
 * `confirmed_by` are central user ids (no foreign key across databases).
 */
#[Fillable(['client_uuid', 'number', 'supplier_id', 'supplier_document_number', 'purchase_date', 'expected_date', 'warehouse_id', 'status', 'total_amount', 'notes', 'created_by', 'created_by_name', 'confirmed_by', 'confirmed_by_name', 'confirmed_at', 'cancel_reason', 'cancelled_at'])]
class Purchase extends Model implements AuditableContract
{
    /** @use HasFactory<PurchaseFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'expected_date' => 'date',
            'status' => PurchaseStatus::class,
            'total_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * @return HasMany<PurchaseAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PurchaseAttachment::class);
    }
}
