<?php

namespace App\Models;

use App\Enums\CashShiftStatus;
use App\Enums\ShiftDifferenceStatus;
use Database\Factories\CashShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. `cashier_id` points at a central user, so it has no relation.
 */
#[Fillable(['client_uuid', 'register_id', 'business_date', 'cashier_id', 'cashier_name', 'opened_at', 'opening_cash', 'closed_at', 'expected_cash', 'counted_cash', 'cash_difference', 'deposit_amount', 'status', 'difference_status', 'closing_note'])]
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
            'followed_up_at' => 'datetime',
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
     * A refused cash difference stays open until an admin marks it followed up.
     */
    public function isAwaitingFollowUp(): bool
    {
        return $this->difference_status === ShiftDifferenceStatus::Rejected && $this->followed_up_at === null;
    }

    /**
     * How people refer to the shift, e.g. "Shift #0002".
     */
    public function label(): string
    {
        return 'Shift #'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  Builder<CashShift>  $query
     * @return Builder<CashShift>
     */
    public function scopeAwaitingFollowUp(Builder $query): Builder
    {
        return $query->where('difference_status', ShiftDifferenceStatus::Rejected)->whereNull('followed_up_at');
    }

    /**
     * @return BelongsTo<Register, $this>
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
