<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Enums\ShiftDifferenceStatus;
use App\Exceptions\ShiftException;
use App\Models\CashShift;
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
 * Opens and closes a cashier's shift. A register has one open shift at a time; the opening cash is the register's
 * fixed amount, and closing compares the counted cash with what the receipts say should be in the drawer.
 *
 * @phpstan-import-type CodTotals from CodSaleService
 *
 * @phpstan-type ShiftSummary array{sale_count: int, total_sales: string, returns_total: string, cash_refunds: string, void_cash_refunds: string, cod: CodTotals, drawer_expenses: string, payments: array<string, string>, opening_cash: string, expected_cash: string}
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
     * @throws ShiftException
     */
    public function open(User $user, Register $register): CashShift
    {
        return DB::transaction(function () use ($user, $register): CashShift {
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

            try {
                return CashShift::query()->create([
                    'client_uuid' => (string) Str::uuid(),
                    'register_id' => $register->id,
                    'business_date' => today(),
                    'cashier_id' => $user->id,
                    'cashier_name' => $user->name,
                    'opened_at' => now(),
                    'opening_cash' => $register->fixed_opening_cash,
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
     * What happened in the shift so far. Cash is what stayed in the drawer: received minus the change given and the
     * refunds paid out in cash. A return still waiting for approval has paid nothing yet. Expenses taken from the
     * drawer lower the cash that should be in it, but not the cash of the sales.
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
        $cashInDrawer = BigDecimal::of($payments[PaymentMethod::Cash->value])->minus($changeGiven)->minus($cashRefunds)->minus($voidCashRefunds);
        $payments[PaymentMethod::Cash->value] = (string) $cashInDrawer->toScale(2);

        $drawerExpenses = BigDecimal::of($this->expenseService->drawerTotalOf($shift));

        return [
            'sale_count' => (clone $completedSalesQuery)->count(),
            'total_sales' => (string) BigDecimal::of((string) (clone $completedSalesQuery)->sum('total'))->toScale(2),
            'returns_total' => (string) BigDecimal::of((string) (clone $completedReturnsQuery)->sum('credit_amount'))->toScale(2),
            'cash_refunds' => (string) $cashRefunds->toScale(2),
            'void_cash_refunds' => (string) $voidCashRefunds->toScale(2),
            'cod' => $this->codSales->totalsOfShift($shift),
            'drawer_expenses' => (string) $drawerExpenses->toScale(2),
            'payments' => $payments,
            'opening_cash' => (string) BigDecimal::of((string) $shift->opening_cash)->toScale(2),
            'expected_cash' => (string) BigDecimal::of((string) $shift->opening_cash)->plus($cashInDrawer)->minus($drawerExpenses)->toScale(2),
        ];
    }

    /**
     * Closes the shift with the cash counted. A difference needs a note and waits for an admin's approval; the
     * cash above the register's fixed amount is the deposit to hand over.
     *
     * @throws ShiftException
     */
    public function close(CashShift $shift, string $countedCash, ?string $note): CashShift
    {
        return DB::transaction(function () use ($shift, $countedCash, $note): CashShift {
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

            $shift->forceFill([
                'status' => CashShiftStatus::Closed,
                'closed_at' => now(),
                'expected_cash' => (string) $expectedCash->toScale(2),
                'counted_cash' => (string) $counted->toScale(2),
                'cash_difference' => (string) $difference->toScale(2),
                'deposit_amount' => (string) $counted->minus($shift->opening_cash)->toScale(2),
                'difference_status' => $difference->isZero() ? ShiftDifferenceStatus::None : ShiftDifferenceStatus::PendingApproval,
                'closing_note' => filled($note) ? trim($note) : null,
            ])->save();

            return $shift;
        });
    }
}
