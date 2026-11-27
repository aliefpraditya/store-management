<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. Money a customer paid on a COD receipt before its goods were delivered: part of the bill,
 * or all of it, since a sale already rung up as COD may be paid in full before delivery. `received_by` is a central user id (no foreign key across databases).
 */
#[Fillable(['client_uuid', 'sale_id', 'cash_shift_id', 'business_date', 'received_at', 'method', 'amount', 'reference', 'notes', 'received_by', 'received_by_name'])]
class CodAdvancePayment extends Model implements AuditableContract
{
    use Auditable;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'received_at' => 'datetime',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
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
     * @return BelongsTo<CashShift, $this>
     */
    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }
}
