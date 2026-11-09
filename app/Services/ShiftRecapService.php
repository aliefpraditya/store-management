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
 * The admin's view of the shifts: looking them up, and deciding on the cash difference of a shift waiting for review.
 * A shift whose count did not match only closes once the difference is decided: accepted, or left to the cashier (the report and
 * the cashier paying the money back are kept outside the system). Nobody decides the difference of a shift they worked themselves: it is for another admin or the Super Admin.
 *
 * @phpstan-type RecapFilters array{from: string|null, to: string|null, status: string|null, difference: string|null, search: string|null}
 */
class ShiftRecapService
{
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
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('business_date', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('business_date', '<=', $to))
            ->when(CashShiftStatus::tryFrom((string) $filters['status']), fn (Builder $query, CashShiftStatus $status) => $query->where('status', $status))
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

    /**
     * Accepts the cash difference of a shift waiting for review, or leaves it to the cashier (`$isApproved` false, stored as
     * rejected), with the admin's note, and closes the shift either way.
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

            if ($shift->cashier_id === $admin->id) {
                throw ShiftException::cannotDecideOwnShift();
            }

            $shift->forceFill([
                'status' => CashShiftStatus::Closed,
                'closed_at' => $shift->closed_at ?? now(),
                'difference_status' => $isApproved ? ShiftDifferenceStatus::Approved : ShiftDifferenceStatus::Rejected,
                'difference_approved_by' => $admin->id,
                'difference_approved_at' => now(),
                'approval_note' => trim($note),
            ])->save();

            return $shift;
        });
    }
}
