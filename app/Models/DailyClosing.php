<?php

namespace App\Models;

use App\Enums\DailyClosingStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. The end of one business day: the figures of the day as they were when it was closed (a
 * snapshot, never worked out again) and the lock on the day's transactions. `closed_by` and `reopened_by` are central
 * user ids (no foreign key across databases). A day that is reopened keeps its row, with the reason, until it is closed
 * again.
 */
#[Fillable(['business_date', 'status', 'shifts_count', 'sales_count', 'total_sales', 'sales_cash', 'sales_transfer', 'sales_qris', 'sales_edc', 'sales_cod', 'cod_received_cash', 'cod_received_transfer', 'cod_received_qris', 'cod_received_edc', 'total_returns', 'refunds_cash', 'voids_count', 'total_voids', 'order_deposits_received', 'order_deposits_refunded', 'expenses_drawer', 'expenses_other', 'total_expected_cash', 'total_counted_cash', 'total_cash_difference', 'total_deposit', 'notes', 'closed_by', 'closed_at', 'reopened_by', 'reopened_at', 'reopen_reason'])]
class DailyClosing extends Model implements AuditableContract
{
    use Auditable;

    /** The money columns of the snapshot, in the order the report shows them. */
    public const MONEY_FIELDS = ['total_sales', 'sales_cash', 'sales_transfer', 'sales_qris', 'sales_edc', 'sales_cod', 'cod_received_cash', 'cod_received_transfer', 'cod_received_qris', 'cod_received_edc', 'total_returns', 'refunds_cash', 'total_voids', 'order_deposits_received', 'order_deposits_refunded', 'expenses_drawer', 'expenses_other', 'total_expected_cash', 'total_counted_cash', 'total_cash_difference', 'total_deposit'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'status' => DailyClosingStatus::class,
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
            ...array_fill_keys(self::MONEY_FIELDS, 'decimal:2'),
        ];
    }

    /**
     * Whether the transactions of this business day are locked. A reopened day is open again until it is closed once more.
     */
    public static function isClosedOn(CarbonInterface $date): bool
    {
        return self::query()->where('business_date', $date->toDateString())->where('status', DailyClosingStatus::Closed)->exists();
    }

    /**
     * Takes the lock that closing a day takes, so closing a day and booking something on it (opening a shift, adding or
     * voiding an expense of another source) wait for each other instead of running at the same moment. Without it a
     * booking that commits just after the closing has read the day would be left out of the snapshot, or would land on a
     * day that is already closed. Registers are what every such booking can be made to share: opening a shift locks its
     * own register, so it needs no call to this. Call it first in the transaction, before anything is read, and keep the
     * order (registers by id) so two callers cannot deadlock.
     */
    public static function lockAgainstClosing(): void
    {
        Register::query()->orderBy('id')->lockForUpdate()->pluck('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }
}
