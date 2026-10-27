<?php

namespace App\Models;

use App\Enums\CashShiftStatus;
use App\Enums\ShiftDifferenceStatus;
use Database\Factories\CashShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. `cashier_id` points at a central user, so it has no relation.
 */
class CashShift extends Model implements AuditableContract
{
    /** @use HasFactory<CashShiftFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'difference_approved_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'cash_difference' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'status' => CashShiftStatus::class,
            'difference_status' => ShiftDifferenceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Register, $this>
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }
}
