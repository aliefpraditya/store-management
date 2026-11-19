<?php

namespace App\Models;

use App\Enums\CashShiftStatus;
use App\Enums\Permission;
use App\Enums\ShiftDifferenceStatus;
use Database\Factories\CashShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. `cashier_id` points at a central user, so it has no relation.
 */
#[Fillable(['client_uuid', 'register_id', 'business_date', 'cashier_id', 'cashier_name', 'opened_at', 'opening_cash', 'opening_cash_note', 'closed_at', 'closed_by', 'counted_at', 'expected_cash', 'counted_cash', 'cash_difference', 'deposit_amount', 'drawer_left_cash', 'status', 'difference_status', 'closing_note'])]
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
            'counted_at' => 'datetime',
            'difference_approved_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'cash_difference' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'drawer_left_cash' => 'decimal:2',
            'status' => CashShiftStatus::class,
            'difference_status' => ShiftDifferenceStatus::class,
        ];
    }

    /**
     * How people refer to the shift, e.g. "Shift #0002".
     */
    public function label(): string
    {
        return 'Shift #'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Whether the cashier of the shift may review shifts themself, so that only a Super Admin can decide theirs. `cashier_id`
     * points at a central user, so it is looked up; a user who is gone, or who has lost access to the store, counts as an
     * ordinary cashier. Must run while the store's tenancy is initialized.
     */
    public function isWorkedByReviewer(): bool
    {
        /** @var Store $store */
        $store = tenant();

        return User::query()->find($this->cashier_id)?->hasPermissionIn($store, Permission::ShiftsReview) ?? false;
    }

    /**
     * Who decides the cash difference of this shift: the store's admin for an ordinary cashier, the Super Admin for someone
     * who reviews shifts, and a Super Admin decides their own. Must run while the store's tenancy is initialized.
     */
    public function reviewerLabel(): string
    {
        if (User::query()->find($this->cashier_id)?->is_super_admin) {
            return 'Super Admin (Anda sendiri bisa memutuskannya di Rekap Shift)';
        }

        return $this->isWorkedByReviewer() ? 'Super Admin' : 'Admin Toko';
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
