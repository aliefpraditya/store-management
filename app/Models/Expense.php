<?php

namespace App\Models;

use App\Enums\CashShiftStatus;
use App\Enums\ExpenseSource;
use App\Enums\ExpenseStatus;
use App\Exceptions\ExpenseException;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. Money that left the store for running costs: from the cash drawer of a shift (which
 * lowers the cash that should be in it) or from somewhere else (which does not). `created_by` is a central user id
 * (no foreign key across databases).
 */
#[Fillable(['client_uuid', 'number', 'business_date', 'cash_shift_id', 'expense_category_id', 'source', 'description', 'paid_to', 'amount', 'status', 'void_reason', 'voided_at', 'voided_by_name', 'created_by', 'created_by_name'])]
class Expense extends Model implements AuditableContract
{
    /** @use HasFactory<ExpenseFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'voided_at' => 'datetime',
            'amount' => 'decimal:2',
            'source' => ExpenseSource::class,
            'status' => ExpenseStatus::class,
        ];
    }

    /**
     * Why this expense cannot be voided now, or null when it can. A drawer expense of a shift that has been counted and
     * closed can no longer be undone: the closed shift's cash figures were settled with it. Needs `cashShift` loaded
     * for a drawer expense. The one place this rule lives, for the button on the list and for the voiding itself.
     */
    public function voidBlocker(): ?ExpenseException
    {
        if ($this->status !== ExpenseStatus::Recorded) {
            return ExpenseException::alreadyVoided();
        }

        if ($this->source === ExpenseSource::Drawer && $this->cashShift?->status !== CashShiftStatus::Open) {
            return ExpenseException::drawerShiftClosed();
        }

        return null;
    }

    public function isVoidable(): bool
    {
        return $this->voidBlocker() === null;
    }

    /**
     * @return BelongsTo<CashShift, $this>
     */
    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
