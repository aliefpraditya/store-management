<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\DailyClosingStatus;
use App\Enums\DeliveryNoteStatus;
use App\Enums\ExpenseSource;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Enums\ShiftDifferenceStatus;
use App\Exceptions\DailyClosingException;
use App\Models\CashShift;
use App\Models\DailyClosing;
use App\Models\DeliveryNote;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Closing a business day (tutup hari): an admin checks that the day is settled, its figures are saved as a snapshot,
 * and the day's transactions are locked. A closed day can only be reopened by a Super Admin, with a reason, and then
 * has to be closed again.
 *
 * What a day holds follows the shifts opened on it: the receipts and expenses carry the shift's business date, and a
 * return belongs to the day of the shift it was made in (a return of an old receipt is booked on the day it is made).
 *
 * @phpstan-type Figures array{shifts_count: int, sales_count: int, total_sales: string, sales_cash: string, sales_transfer: string, sales_qris: string, sales_edc: string, sales_cod: string, total_returns: string, refunds_cash: string, voids_count: int, total_voids: string, expenses_drawer: string, expenses_other: string, total_expected_cash: string, total_counted_cash: string, total_cash_difference: string, total_deposit: string}
 * @phpstan-type Checklist array{can_close: bool, open_shifts: list<array{label: string, cashier_name: string, show_url: string}>, pending_differences: list<array{label: string, amount: string, show_url: string}>, pending_return_count: int, unfinished_delivery_note_count: int}
 */
class DailyClosingService
{
    private const HISTORY_LIMIT = 10;

    /** Closing runs again this many times when the database picks it as the victim of a deadlock. */
    private const TRANSACTION_ATTEMPTS = 3;

    public function find(CarbonInterface $date): ?DailyClosing
    {
        return DailyClosing::query()->with(['closedBy', 'reopenedBy'])->where('business_date', $date->toDateString())->first();
    }

    /**
     * The latest days that have been closed (or reopened), newest first.
     *
     * @return Collection<int, DailyClosing>
     */
    public function recent(): Collection
    {
        return DailyClosing::query()->with(['closedBy', 'reopenedBy'])->orderByDesc('business_date')->limit(self::HISTORY_LIMIT)->get();
    }

    /**
     * What stands in the way of closing the day. Only the first three block it; unfinished delivery notes are a
     * reminder, since a delivery can take days.
     *
     * @return Checklist
     */
    public function checklist(CarbonInterface $date): array
    {
        $shiftsOfDayQuery = CashShift::query()->with('register')->where('business_date', $date->toDateString());

        $openShifts = (clone $shiftsOfDayQuery)->where('status', CashShiftStatus::Open)->orderBy('id')->get();
        $pendingDifferences = (clone $shiftsOfDayQuery)->where('difference_status', ShiftDifferenceStatus::PendingApproval)->orderBy('id')->get();
        $pendingReturnCount = SaleReturn::query()
            ->whereIn('cash_shift_id', $this->shiftIdsOf($date))
            ->where('status', SaleReturnStatus::Pending)
            ->count();

        return [
            'can_close' => $openShifts->isEmpty() && $pendingDifferences->isEmpty() && $pendingReturnCount === 0,
            'open_shifts' => $openShifts->map(fn (CashShift $shift): array => [
                'label' => $shift->label(),
                'cashier_name' => $shift->cashier_name,
                'show_url' => route('tenant.shifts.show', ['tenant' => tenant('code'), 'shift' => $shift->id]),
            ])->all(),
            'pending_differences' => $pendingDifferences->map(fn (CashShift $shift): array => [
                'label' => $shift->label(),
                'amount' => $shift->cash_difference,
                'show_url' => route('tenant.shifts.show', ['tenant' => tenant('code'), 'shift' => $shift->id]),
            ])->all(),
            'pending_return_count' => $pendingReturnCount,
            'unfinished_delivery_note_count' => DeliveryNote::query()
                ->whereIn('status', DeliveryNoteStatus::unfinished())
                ->where('business_date', '<=', $date->toDateString())
                ->count(),
        ];
    }

    /**
     * The figures of the day as they stand now. Cash is counted from the shifts that were closed; the sales per method
     * are what was received (cash after the change given back), and refunds and drawer expenses are shown apart.
     *
     * @return Figures
     */
    public function summarize(CarbonInterface $date): array
    {
        $dateText = $date->toDateString();

        return [
            'shifts_count' => CashShift::query()->where('business_date', $dateText)->count(),
            ...$this->salesFigures($dateText),
            ...$this->returnFigures($date),
            ...$this->voidFigures($dateText),
            ...$this->expenseFigures($dateText),
            ...$this->cashFigures($dateText),
        ];
    }

    /**
     * Closes the day: re-checks the list, saves the figures and locks the day. A day that was reopened is closed
     * again on the same row.
     *
     * @throws DailyClosingException
     */
    public function close(CarbonInterface $date, User $admin, ?string $notes): DailyClosing
    {
        if ($date->toDateString() > today()->toDateString()) {
            throw DailyClosingException::futureDate();
        }

        try {
            return DB::transaction(fn (): DailyClosing => $this->closeInTransaction($date, $admin, $notes), self::TRANSACTION_ATTEMPTS);
        } catch (UniqueConstraintViolationException) {
            // Two closings of a day that had no row yet both got as far as inserting it; the other one won.
            throw DailyClosingException::alreadyClosed($date);
        }
    }

    /**
     * Opens a closed day again so it can be corrected. Whoever calls this must already have been checked to be a Super Admin.
     *
     * @throws DailyClosingException
     */
    public function reopen(CarbonInterface $date, User $superAdmin, string $reason): DailyClosing
    {
        return DB::transaction(function () use ($date, $superAdmin, $reason): DailyClosing {
            $closing = DailyClosing::query()->where('business_date', $date->toDateString())->lockForUpdate()->first();

            if ($closing?->status !== DailyClosingStatus::Closed) {
                throw DailyClosingException::notClosed($date);
            }

            $closing->forceFill([
                'status' => DailyClosingStatus::Reopened,
                'reopened_by' => $superAdmin->id,
                'reopened_at' => now(),
                'reopen_reason' => trim($reason),
            ])->save();

            return $closing;
        });
    }

    /**
     * The registers are locked before anything is read, so a shift opened or an expense booked at the same moment is
     * either in the checklist and the figures or turned away by the closed day ({@see DailyClosing::lockAgainstClosing()}).
     */
    private function closeInTransaction(CarbonInterface $date, User $admin, ?string $notes): DailyClosing
    {
        DailyClosing::lockAgainstClosing();

        $existingClosing = DailyClosing::query()->where('business_date', $date->toDateString())->lockForUpdate()->first();

        if ($existingClosing?->status === DailyClosingStatus::Closed) {
            throw DailyClosingException::alreadyClosed($date);
        }

        if (! $this->checklist($date)['can_close']) {
            throw DailyClosingException::notReady($date);
        }

        $closingColumns = [
            ...$this->summarize($date),
            'status' => DailyClosingStatus::Closed,
            'notes' => filled($notes) ? trim($notes) : null,
            'closed_by' => $admin->id,
            'closed_at' => now(),
        ];

        if ($existingClosing === null) {
            return DailyClosing::query()->create(['business_date' => $date->toDateString(), ...$closingColumns]);
        }

        $existingClosing->forceFill($closingColumns)->save();

        return $existingClosing;
    }

    /**
     * Sales are counted by their total, but what came in per method also holds the deposits of receipts that were
     * voided since (the money did come in, and goes back out as a refund below). COD is not money in: it is what
     * customers still owe on delivery.
     *
     * @return array{sales_count: int, total_sales: string, sales_cash: string, sales_transfer: string, sales_qris: string, sales_edc: string, sales_cod: string}
     */
    private function salesFigures(string $dateText): array
    {
        $completedSalesQuery = Sale::query()->where('business_date', $dateText)->where('status', SaleStatus::Completed);
        $moneyTakenInQuery = Sale::query()->where('business_date', $dateText)->whereIn('status', [SaleStatus::Completed, SaleStatus::Voided]);

        $receivedByMethod = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.business_date', $dateText)
            ->whereIn('sales.status', [SaleStatus::Completed, SaleStatus::Voided])
            ->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method as method, sum(sale_payments.amount) as amount')
            ->pluck('amount', 'method');

        $receivedAmountOf = fn (PaymentMethod $method): BigDecimal => BigDecimal::of((string) ($receivedByMethod[$method->value] ?? 0));

        $codOfCompletedSales = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.business_date', $dateText)
            ->where('sales.status', SaleStatus::Completed)
            ->where('sale_payments.method', PaymentMethod::Cod)
            ->sum('sale_payments.amount');

        return [
            'sales_count' => (clone $completedSalesQuery)->count(),
            'total_sales' => $this->money((clone $completedSalesQuery)->sum('total')),
            'sales_cash' => $this->money($receivedAmountOf(PaymentMethod::Cash)->minus((string) $moneyTakenInQuery->sum('change_amount'))),
            'sales_transfer' => $this->money($receivedAmountOf(PaymentMethod::Transfer)),
            'sales_qris' => $this->money($receivedAmountOf(PaymentMethod::Qris)),
            'sales_edc' => $this->money($receivedAmountOf(PaymentMethod::Edc)),
            'sales_cod' => $this->money($codOfCompletedSales),
        ];
    }

    /**
     * Cash refunds are the cash returns of the day's shifts plus the cash deposits given back for voided receipts.
     *
     * @return array{total_returns: string, refunds_cash: string}
     */
    private function returnFigures(CarbonInterface $date): array
    {
        $completedReturnsQuery = SaleReturn::query()->whereIn('cash_shift_id', $this->shiftIdsOf($date))->where('status', SaleReturnStatus::Completed);
        $voidCashRefunds = Sale::query()->whereIn('void_cash_shift_id', $this->shiftIdsOf($date))->sum('void_cash_refund');

        return [
            'total_returns' => $this->money((clone $completedReturnsQuery)->sum('credit_amount')),
            'refunds_cash' => $this->money(BigDecimal::of((string) (clone $completedReturnsQuery)->where('refund_method', PaymentMethod::Cash)->sum('refund_amount'))->plus((string) $voidCashRefunds)),
        ];
    }

    /**
     * The receipts voided on the day, whatever day they were sold: a COD sale made yesterday and refused at the door
     * today is a void of today. The sales figures of the day it was sold no longer hold it.
     *
     * @return array{voids_count: int, total_voids: string}
     */
    private function voidFigures(string $dateText): array
    {
        $voidedOnDayQuery = Sale::query()->where('status', SaleStatus::Voided)->whereDate('voided_at', $dateText);

        return [
            'voids_count' => (clone $voidedOnDayQuery)->count(),
            'total_voids' => $this->money((clone $voidedOnDayQuery)->sum('total')),
        ];
    }

    /**
     * The receipts voided on the day, for the day's page and report.
     *
     * @return list<array{number: string, sold_on: string, customer_name: string|null, total: string, reason: string|null, voided_by_name: string|null, show_url: string}>
     */
    public function voidsOf(CarbonInterface $date): array
    {
        return Sale::query()
            ->where('status', SaleStatus::Voided)
            ->whereDate('voided_at', $date->toDateString())
            ->orderBy('voided_at')
            ->get()
            ->map(fn (Sale $sale): array => [
                'number' => $sale->number,
                'sold_on' => $sale->business_date->toDateString(),
                'customer_name' => $sale->customer_name,
                'total' => $sale->total,
                'reason' => $sale->void_reason,
                'voided_by_name' => $sale->voided_by_name,
                'show_url' => route('tenant.sales.show', ['tenant' => tenant('code'), 'sale' => $sale->id]),
            ])
            ->all();
    }

    /**
     * @return array{expenses_drawer: string, expenses_other: string}
     */
    private function expenseFigures(string $dateText): array
    {
        $expensesBySource = Expense::query()
            ->where('business_date', $dateText)
            ->where('status', ExpenseStatus::Recorded)
            ->groupBy('source')
            ->selectRaw('source, sum(amount) as amount')
            ->pluck('amount', 'source');

        return [
            'expenses_drawer' => $this->money($expensesBySource[ExpenseSource::Drawer->value] ?? 0),
            'expenses_other' => $this->money($expensesBySource[ExpenseSource::Other->value] ?? 0),
        ];
    }

    /**
     * @return array{total_expected_cash: string, total_counted_cash: string, total_cash_difference: string, total_deposit: string}
     */
    private function cashFigures(string $dateText): array
    {
        $closedShiftsQuery = CashShift::query()->where('business_date', $dateText)->where('status', CashShiftStatus::Closed);

        return [
            'total_expected_cash' => $this->money((clone $closedShiftsQuery)->sum('expected_cash')),
            'total_counted_cash' => $this->money((clone $closedShiftsQuery)->sum('counted_cash')),
            'total_cash_difference' => $this->money((clone $closedShiftsQuery)->sum('cash_difference')),
            'total_deposit' => $this->money((clone $closedShiftsQuery)->sum('deposit_amount')),
        ];
    }

    /**
     * @return Collection<int, int>
     */
    private function shiftIdsOf(CarbonInterface $date): Collection
    {
        return CashShift::query()->where('business_date', $date->toDateString())->pluck('id');
    }

    private function money(BigDecimal|string|int|float|null $amount): string
    {
        return (string) BigDecimal::of((string) ($amount ?? 0))->toScale(2);
    }
}
