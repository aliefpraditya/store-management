<?php

namespace App\Models;

use App\Enums\PaymentMethod;
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
 */
#[Fillable(['number', 'sale_id', 'register_id', 'cash_shift_id', 'returned_at', 'resolution', 'refund_method', 'credit_amount', 'refund_amount', 'exchange_sale_id', 'status', 'reason', 'requested_by', 'approved_by'])]
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
            'status' => SaleReturnStatus::class,
            'refund_method' => PaymentMethod::class,
        ];
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
