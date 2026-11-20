<?php

namespace App\Models;

use App\Enums\CodSettlementStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. The money of a COD receipt, received after its goods were delivered; one per receipt.
 * `amount` is what came in and `expected_amount` what was owed then. When they differ it carries a reason and waits for
 * an approval (`awaiting_approval`), but the money already counts in the shift it landed in. `received_by` and
 * `approved_by` are central user ids (no foreign key across databases).
 */
#[Fillable(['client_uuid', 'sale_id', 'cash_shift_id', 'business_date', 'received_at', 'method', 'amount', 'expected_amount', 'difference_reason', 'reference', 'notes', 'status', 'received_by', 'received_by_name'])]
class CodSettlement extends Model implements AuditableContract
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
            'approved_at' => 'datetime',
            'amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'status' => CodSettlementStatus::class,
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
