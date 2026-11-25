<?php

namespace App\Models;

use App\Enums\PurchasePaymentMethod;
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
 * that happens when the goods are received. `warehouse_id` is where the goods are meant to go by default. `expected_date` is
 * the day the goods are expected at the carrier (`carrier_id`, optional), not at the store. `created_by` and
 * `confirmed_by` and `paid_by` are central user ids (no foreign key across databases). The payment (always in full, in
 * advance, when the nota is confirmed) is recorded on the nota only; it is not tied to the store's cash.
 */
#[Fillable(['client_uuid', 'number', 'supplier_id', 'supplier_document_number', 'purchase_date', 'expected_date', 'carrier_id', 'shipment_id', 'warehouse_id', 'status', 'total_amount', 'notes', 'created_by', 'created_by_name', 'confirmed_by', 'confirmed_by_name', 'confirmed_at', 'paid_date', 'paid_amount', 'payment_method', 'payment_notes', 'paid_by', 'paid_by_name', 'cancel_reason', 'cancelled_at'])]
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
            'paid_date' => 'date',
            'paid_amount' => 'decimal:2',
            'payment_method' => PurchasePaymentMethod::class,
            'cancelled_at' => 'datetime',
        ];
    }

    /** A nota with nothing to pay (all lines free) is never paid; it is just confirmed. */
    public function isPaid(): bool
    {
        return $this->paid_date !== null;
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Carrier, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    /**
     * The shipment this purchase travels in, once it was put in one; a purchase is in at most one.
     *
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
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
