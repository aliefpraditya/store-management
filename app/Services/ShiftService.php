<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Enums\ShiftDifferenceStatus;
use App\Exceptions\ShiftException;
use App\Models\CashShift;
use App\Models\CustomerOrderPayment;
use App\Models\DailyClosing;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opens and closes a cashier's shift. A register has one open shift at a time. The petty cash travels with the
 * drawer: closing records what the cashier leaves in it, and the next shift on that register opens with that amount (or
 * another one, with a reason). Closing compares the counted cash with what the receipts say should be in the drawer.
 *
 * @phpstan-import-type CodTotals from CodSaleService
 *
 * @phpstan-type ShiftSummary array{sale_count: int, total_sales: string, returns_total: string, cash_refunds: string, void_cash_refunds: string, cod: CodTotals, drawer_expenses: string, order_deposit_cash: string, payments: array<string, string>, opening_cash: string, expected_cash: string}
 */
class ShiftService
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly CodSaleService $codSales,
    ) {}

    public function openShiftOf(User $user): ?CashShift
    {
        return CashShift::query()
            ->where('cashier_id', $user->id)
            ->where('status', CashShiftStatus::Open)
            ->with('register')
            ->first();
    }

    /**
     * What the last closed shift left in the register's drawer, or null when the register has never been closed (its
     * first shift has to be told how much cash it starts with).
     *
     * @return numeric-string|null
     */
    public function carriedOverCash(Register $register): ?string
    {
        $left = CashShift::query()
            ->where('register_id', $register->id)
            ->where('status', CashShiftStatus::Closed)
            ->whereNotNull('drawer_left_cash')
            ->latest('id')
            ->value('drawer_left_cash');

        return $left === null ? null : (string) BigDecimal::of((string) $left)->toScale(2);
    }

    /**
     * Opens a shift on the register. Without `$openingCash` the shift starts with what the drawer was left with (zero
     * when there is nothing to carry over). Another amount than the carried-over one needs a reason, so a shortage
     * cannot be hidden by quietly typing a lower opening cash.
     *
     * @param  numeric-string|null  $openingCash
     *
     * @throws ShiftException
     */
    public function open(User $user, Register $register, ?string $openingCash = null, ?string $openingNote = null): CashShift
    {
        return DB::transaction(function () use ($user, $register, $openingCash, $openingNote): CashShift {
            $register = Register::query()->lockForUpdate()->findOrFail($register->id);

            if (! $register->is_active) {
                throw ShiftException::noRegister();
            }

            if (DailyClosing::isClosedOn(today())) {
                throw ShiftException::dayClosed(today()->format('d/m/Y'));
            }

            if ($this->openShiftOf($user) !== null) {
                throw ShiftException::alreadyOpen();
            }

            $busyShift = CashShift::query()->where('register_id', $register->id)->where('status', CashShiftStatus::Open)->first();

            if ($busyShift !== null) {
                throw ShiftException::registerInUse($register->name, $busyShift->cashier_name);
            }

            $carriedOverCash = $this->carriedOverCash($register);
            $opening = BigDecimal::of($openingCash ?? $carriedOverCash ?? '0')->toScale(2);
            $isOpeningChanged = $carriedOverCash !== null && ! $opening->isEqualTo($carriedOverCash);

            if ($isOpeningChanged && blank($openingNote)) {
                throw ShiftException::openingCashNeedsNote($carriedOverCash);
            }

            try {
                return CashShift::query()->create([
                    'client_uuid' => (string) Str::uuid(),
                    'register_id' => $register->id,
                    'business_date' => today(),
                    'cashier_id' => $user->id,
                    'cashier_name' => $user->name,
                    'opened_at' => now(),
                    'opening_cash' => (string) $opening,
                    'opening_cash_note' => $isOpeningChanged ? trim($openingNote) : null,
                    'status' => CashShiftStatus::Open,
                    'difference_status' => ShiftDifferenceStatus::None,
                ]);
            } catch (UniqueConstraintViolationException) {
                // The checks above only hold one register at a time; the database is what stops the same cashier
                // opening shifts on two registers at once.
                throw ShiftException::alreadyOpen();
            }
        });
    }

    /**
     * What happened in the shift so far. The cash of the sales is what was received minus the change given; what
     * goes back out of the drawer (cash refunds, void refunds, drawer expenses) is reported apart, not taken off it, so
     * the report shows each figure on its own and the expected cash is opening + sales cash − those. A return still
     * waiting for approval has paid nothing yet.
     *
     * The money a voided receipt took in (a deposit) still came in, so it is counted here; the deposit goes back out
     * as a void refund in the shift that voided it. COD is not money in the drawer: it is shown apart, split by whether
     * the goods were delivered yet.
     *
     * @return ShiftSummary
     */
    public function summarize(CashShift $shift): array
    {
        $completedSalesQuery = Sale::query()->where('cash_shift_id', $shift->id)->where('status', SaleStatus::Completed);
        $moneyTakenInStatuses = [SaleStatus::Completed, SaleStatus::Voided];

        $receivedByMethod = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.cash_shift_id', $shift->id)
            ->whereIn('sales.status', $moneyTakenInStatuses)
            ->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method as method, sum(sale_payments.amount) as amount')
            ->pluck('amount', 'method');

        $payments = [];

        foreach (PaymentMethod::forCashier() as $method) {
            $payments[$method->value] = (string) BigDecimal::of((string) ($receivedByMethod[$method->value] ?? 0))->toScale(2);
        }

        $completedReturnsQuery = SaleReturn::query()->where('cash_shift_id', $shift->id)->where('status', SaleReturnStatus::Completed);
        $cashRefunds = BigDecimal::of((string) (clone $completedReturnsQuery)->where('refund_method', PaymentMethod::Cash)->sum('refund_amount'));

        $voidCashRefunds = BigDecimal::of((string) Sale::query()->where('void_cash_shift_id', $shift->id)->sum('void_cash_refund'));
        $changeGiven = BigDecimal::of((string) Sale::query()->where('cash_shift_id', $shift->id)->whereIn('status', $moneyTakenInStatuses)->sum('change_amount'));
        $cashFromSales = BigDecimal::of($payments[PaymentMethod::Cash->value])->minus($changeGiven);
        $payments[PaymentMethod::Cash->value] = (string) $cashFromSales->toScale(2);

        $drawerExpenses = BigDecimal::of($this->expenseService->drawerTotalOf($shift));
        $orderDepositCash = $this->orderDepositCashOf($shift);

        return [
            'sale_count' => (clone $completedSalesQuery)->count(),
            'total_sales' => (string) BigDecimal::of((string) (clone $completedSalesQuery)->sum('total'))->toScale(2),
            'returns_total' => (string) BigDecimal::of((string) (clone $completedReturnsQuery)->sum('credit_amount'))->toScale(2),
            'cash_refunds' => (string) $cashRefunds->toScale(2),
            'void_cash_refunds' => (string) $voidCashRefunds->toScale(2),
            'cod' => $this->codSales->totalsOfShift($shift),
            'drawer_expenses' => (string) $drawerExpenses->toScale(2),
            'order_deposit_cash' => (string) $orderDepositCash->toScale(2),
            'payments' => $payments,
            'opening_cash' => (string) BigDecimal::of((string) $shift->opening_cash)->toScale(2),
            'expected_cash' => (string) BigDecimal::of((string) $shift->opening_cash)->plus($cashFromSales)->plus($orderDepositCash)->minus($cashRefunds)->minus($voidCashRefunds)->minus($drawerExpenses)->toScale(2),
        ];
    }

    /**
     * The cash that customer order deposits put into the drawer in this shift, net of deposits given back from it. A
     * deposit is money in the drawer even though it is no sale yet; the receipt that later collects the order carries it
     * as `order_deposit`, which is not counted again.
     */
    private function orderDepositCashOf(CashShift $shift): BigDecimal
    {
        $cashByKind = CustomerOrderPayment::query()
            ->where('cash_shift_id', $shift->id)
            ->where('method', PaymentMethod::Cash)
            ->groupBy('kind')
            ->selectRaw('kind, sum(amount) as amount')
            ->pluck('amount', 'kind');

        return BigDecimal::of((string) ($cashByKind[CustomerOrderPayment::KIND_DEPOSIT] ?? 0))->minus((string) ($cashByKind[CustomerOrderPayment::KIND_REFUND] ?? 0));
    }

    /**
     * Closes the shift with the cash counted. A difference needs a note and waits for an admin's approval. The cashier
     * says how much stays in the drawer for the next shift, and the rest of the counted cash is the deposit to hand
     * over. Without `$drawerLeftCash` the opening cash stays (or all the counted cash when that is less).
     *
     * @param  numeric-string|null  $drawerLeftCash
     *
     * @throws ShiftException
     */
    public function close(CashShift $shift, string $countedCash, ?string $note, ?string $drawerLeftCash = null): CashShift
    {
        return DB::transaction(function () use ($shift, $countedCash, $note, $drawerLeftCash): CashShift {
            $shift = CashShift::query()->with('register')->lockForUpdate()->findOrFail($shift->id);

            if ($shift->status !== CashShiftStatus::Open) {
                throw ShiftException::notOpen();
            }

            $pendingReturnCount = SaleReturn::query()->where('cash_shift_id', $shift->id)->where('status', SaleReturnStatus::Pending)->count();

            if ($pendingReturnCount > 0) {
                throw ShiftException::hasPendingReturns($pendingReturnCount);
            }

            $expectedCash = BigDecimal::of($this->summarize($shift)['expected_cash']);
            $counted = BigDecimal::of($countedCash);
            $difference = $counted->minus($expectedCash);

            if (! $difference->isZero() && blank($note)) {
                throw ShiftException::differenceNeedsNote();
            }

            $left = $drawerLeftCash === null
                ? BigDecimal::min(BigDecimal::of((string) $shift->opening_cash), $counted)
                : BigDecimal::of($drawerLeftCash);

            if ($left->isGreaterThan($counted)) {
                throw ShiftException::drawerLeftExceedsCounted();
            }

            $shift->forceFill([
                'status' => CashShiftStatus::Closed,
                'closed_at' => now(),
                'expected_cash' => (string) $expectedCash->toScale(2),
                'counted_cash' => (string) $counted->toScale(2),
                'cash_difference' => (string) $difference->toScale(2),
                'deposit_amount' => (string) $counted->minus($left)->toScale(2),
                'drawer_left_cash' => (string) $left->toScale(2),
                'difference_status' => $difference->isZero() ? ShiftDifferenceStatus::None : ShiftDifferenceStatus::PendingApproval,
                'closing_note' => filled($note) ? trim($note) : null,
            ])->save();

            return $shift;
        });
    }
}
