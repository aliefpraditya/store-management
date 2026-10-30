<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\ShiftDifferenceStatus;
use App\Exceptions\ShiftException;
use App\Models\CashShift;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opens and closes a cashier's shift. A register has one open shift at a time; the opening cash is the register's
 * fixed amount, and closing compares the counted cash with what the receipts say should be in the drawer.
 *
 * @phpstan-type ShiftSummary array{sale_count: int, total_sales: string, payments: array<string, string>, opening_cash: string, expected_cash: string}
 */
class ShiftService
{
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

            if ($this->openShiftOf($user) !== null) {
                throw ShiftException::alreadyOpen();
            }

            $busyShift = CashShift::query()->where('register_id', $register->id)->where('status', CashShiftStatus::Open)->first();

            if ($busyShift !== null) {
                throw ShiftException::registerInUse($register->name, $busyShift->cashier_name);
            }

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
        });
    }

    /**
     * What happened in the shift so far. Cash is what stayed in the drawer: received minus the change given.
     *
     * @return ShiftSummary
     */
    public function summarize(CashShift $shift): array
    {
        $completedSales = Sale::query()->where('cash_shift_id', $shift->id)->where('status', SaleStatus::Completed);

        $received = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.cash_shift_id', $shift->id)
            ->where('sales.status', SaleStatus::Completed)
            ->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method as method, sum(sale_payments.amount) as amount')
            ->pluck('amount', 'method');

        $payments = [];

        foreach (PaymentMethod::forCashier() as $method) {
            $payments[$method->value] = (string) BigDecimal::of((string) ($received[$method->value] ?? 0))->toScale(2);
        }

        $changeGiven = BigDecimal::of((string) (clone $completedSales)->sum('change_amount'));
        $cashInDrawer = BigDecimal::of($payments[PaymentMethod::Cash->value])->minus($changeGiven);
        $payments[PaymentMethod::Cash->value] = (string) $cashInDrawer->toScale(2);

        return [
            'sale_count' => (clone $completedSales)->count(),
            'total_sales' => (string) BigDecimal::of((string) (clone $completedSales)->sum('total'))->toScale(2),
            'payments' => $payments,
            'opening_cash' => (string) BigDecimal::of((string) $shift->opening_cash)->toScale(2),
            'expected_cash' => (string) BigDecimal::of((string) $shift->opening_cash)->plus($cashInDrawer)->toScale(2),
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
                'deposit_amount' => (string) $counted->minus($shift->register->fixed_opening_cash)->toScale(2),
                'difference_status' => $difference->isZero() ? ShiftDifferenceStatus::None : ShiftDifferenceStatus::PendingApproval,
                'closing_note' => filled($note) ? trim($note) : null,
            ])->save();

            return $shift;
        });
    }
}
