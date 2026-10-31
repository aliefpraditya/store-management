<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\SaleStatus;
use App\Enums\ShiftDifferenceStatus;
use App\Exceptions\ShiftException;
use App\Models\CashShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * The admin's view of the shifts: looking them up, and deciding on the cash difference a closed shift ended with.
 * The decision never blocks the next shift; it only settles whether the difference is accepted.
 *
 * @phpstan-type RecapFilters array{from: string|null, to: string|null, status: string|null, difference: string|null, search: string|null}
 */
class ShiftRecapService
{
    /** A filter choice next to the difference statuses: refused differences nobody has followed up yet. */
    public const AWAITING_FOLLOW_UP = 'awaiting_follow_up';

    /**
     * Newest shifts first, with the count and total of their completed receipts.
     *
     * @param  RecapFilters  $filters
     * @return LengthAwarePaginator<int, CashShift>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return CashShift::query()
            ->with('register')
            ->withCount(['sales as sale_count' => fn (Builder $sales) => $sales->where('status', SaleStatus::Completed)])
            ->withSum(['sales as sales_total' => fn (Builder $sales) => $sales->where('status', SaleStatus::Completed)], 'total')
            ->when($filters['from'], fn (Builder $query, string $from) => $query->whereDate('business_date', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->whereDate('business_date', '<=', $to))
            ->when(CashShiftStatus::tryFrom((string) $filters['status']), fn (Builder $query, CashShiftStatus $status) => $query->where('status', $status))
            ->when($filters['difference'] === self::AWAITING_FOLLOW_UP, fn (Builder $query) => $query->awaitingFollowUp())
            ->when(ShiftDifferenceStatus::tryFrom((string) $filters['difference']), fn (Builder $query, ShiftDifferenceStatus $difference) => $query->where('difference_status', $difference))
            ->when(filled($filters['search']), fn (Builder $query) => $query->where('cashier_name', 'like', "%{$filters['search']}%"))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function pendingDifferenceCount(): int
    {
        return CashShift::query()->where('difference_status', ShiftDifferenceStatus::PendingApproval)->count();
    }

    public function followUpCount(): int
    {
        return CashShift::query()->awaitingFollowUp()->count();
    }

    /**
     * Notes that the follow-up of a refused difference has been done, so it leaves the admin's list.
     *
     * @throws ShiftException
     */
    public function markFollowedUp(CashShift $shift, User $admin): CashShift
    {
        return DB::transaction(function () use ($shift, $admin): CashShift {
            $shift = CashShift::query()->lockForUpdate()->findOrFail($shift->id);

            if (! $shift->needsFollowUp()) {
                throw ShiftException::notAwaitingFollowUp();
            }

            $shift->forceFill(['followed_up_by' => $admin->id, 'followed_up_at' => now()])->save();

            return $shift;
        });
    }

    /**
     * Accepts or refuses the cash difference of a closed shift, with the admin's note.
     *
     * @throws ShiftException
     */
    public function decideDifference(CashShift $shift, User $admin, bool $isApproved, string $note): CashShift
    {
        return DB::transaction(function () use ($shift, $admin, $isApproved, $note): CashShift {
            $shift = CashShift::query()->lockForUpdate()->findOrFail($shift->id);

            if ($shift->difference_status !== ShiftDifferenceStatus::PendingApproval) {
                throw ShiftException::differenceNotPending($shift->difference_status->label());
            }

            $shift->forceFill([
                'difference_status' => $isApproved ? ShiftDifferenceStatus::Approved : ShiftDifferenceStatus::Rejected,
                'difference_approved_by' => $admin->id,
                'difference_approved_at' => now(),
                'approval_note' => trim($note),
            ])->save();

            return $shift;
        });
    }
}
