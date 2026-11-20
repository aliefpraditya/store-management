<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ReturnResolution;
use App\Enums\SaleReturnStatus;
use Database\Factories\SaleReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. A return of goods from one receipt; it stays `pending` until an admin approves it,
 * and only a `completed` return moves stock and cash. `cash_shift_id` is the shift whose drawer the refund comes from.
 * `refund_amount` is the money that goes out; `cod_offset_amount` is the part of the value cut from a COD receipt that was
 * not paid yet, which pays nothing out.
 */
#[Fillable(['client_uuid', 'number', 'sale_id', 'register_id', 'cash_shift_id', 'returned_at', 'resolution', 'refund_method', 'refund_account', 'credit_amount', 'refund_amount', 'cod_offset_amount', 'exchange_sale_id', 'exchange_data', 'status', 'reason', 'requested_by', 'approved_by'])]
class SaleReturn extends Model implements AuditableContract
{
    /** @use HasFactory<SaleReturnFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
            'credit_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'cod_offset_amount' => 'decimal:2',
            'status' => SaleReturnStatus::class,
            'resolution' => ReturnResolution::class,
            'refund_method' => PaymentMethod::class,
            'exchange_data' => 'array',
        ];
    }

    public function isExchange(): bool
    {
        return $this->resolution === ReturnResolution::Exchange;
    }

    /**
     * The receipt made for the replacement goods, once the return is approved.
     *
     * @return BelongsTo<Sale, $this>
     */
    public function exchangeSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'exchange_sale_id');
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return HasMany<SaleReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    /**
     * @return BelongsTo<CashShift, $this>
     */
    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }
}
