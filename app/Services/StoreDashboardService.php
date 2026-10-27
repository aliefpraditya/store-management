<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\DeliveryNoteStatus;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Enums\ShiftDifferenceStatus;
use App\Models\CashShift;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;

/**
 * Numbers for the store dashboard. Must run while the store's tenancy is initialized.
 * Money is returned as decimal strings so nothing is rounded on the way to the screen.
 *
 * @phpstan-type Summary array{
 *     sales: array{gross_total: string, returns_total: string, net_total: string, receipt_count: int},
 *     low_stock_count: int,
 *     pending: array{returns: int, cash_differences: int, delivery_notes: int, total: int},
 *     approvals: list<array{kind: string, reference: string, amount: string}>,
 *     open_shifts: list<array{id: int, cashier_name: string, register_name: string, opened_at: string}>
 * }
 */
class StoreDashboardService
{
    private const APPROVAL_LIMIT = 5;

    /**
     * @return Summary
     */
    public function summary(CarbonInterface $today): array
    {
        $pendingReturns = SaleReturn::query()->where('status', SaleReturnStatus::Pending);
        $pendingDifferences = CashShift::query()->where('difference_status', ShiftDifferenceStatus::PendingApproval);
        $unfinishedDeliveryNotes = DeliveryNote::query()->whereIn('status', DeliveryNoteStatus::unfinished());

        $pending = [
            'returns' => $pendingReturns->count(),
            'cash_differences' => $pendingDifferences->count(),
            'delivery_notes' => $unfinishedDeliveryNotes->count(),
        ];

        return [
            'sales' => $this->salesOf($today),
            'low_stock_count' => Product::query()->belowMinimumStock()->count(),
            'pending' => [...$pending, 'total' => array_sum($pending)],
            'approvals' => [
                ...$this->pendingReturnApprovals(),
                ...$this->pendingCashDifferenceApprovals(),
            ],
            'open_shifts' => $this->openShifts(),
        ];
    }

    /**
     * Net sales are completed receipts of the day minus the value of returns completed that day.
     *
     * @return array{gross_total: string, returns_total: string, net_total: string, receipt_count: int}
     */
    private function salesOf(CarbonInterface $day): array
    {
        $completedSales = Sale::query()
            ->where('business_date', $day->toDateString())
            ->where('status', SaleStatus::Completed);

        $gross = BigDecimal::of((string) $completedSales->sum('total'))->toScale(2);
        $returns = BigDecimal::of((string) SaleReturn::query()
            ->where('status', SaleReturnStatus::Completed)
            ->whereDate('returned_at', $day->toDateString())
            ->sum('credit_amount'))->toScale(2);

        return [
            'gross_total' => (string) $gross,
            'returns_total' => (string) $returns,
            'net_total' => (string) $gross->minus($returns),
            'receipt_count' => $completedSales->count(),
        ];
    }

    /**
     * @return list<array{kind: string, reference: string, amount: string}>
     */
    private function pendingReturnApprovals(): array
    {
        return SaleReturn::query()
            ->where('status', SaleReturnStatus::Pending)
            ->latest('id')
            ->limit(self::APPROVAL_LIMIT)
            ->get()
            ->map(fn (SaleReturn $return): array => [
                'kind' => 'return',
                'reference' => $return->number,
                'amount' => $return->credit_amount,
            ])
            ->all();
    }

    /**
     * @return list<array{kind: string, reference: string, amount: string}>
     */
    private function pendingCashDifferenceApprovals(): array
    {
        return CashShift::query()
            ->where('difference_status', ShiftDifferenceStatus::PendingApproval)
            ->latest('id')
            ->limit(self::APPROVAL_LIMIT)
            ->get()
            ->map(fn (CashShift $shift): array => [
                'kind' => 'cash_difference',
                'reference' => 'Shift #'.str_pad((string) $shift->id, 4, '0', STR_PAD_LEFT),
                'amount' => $shift->cash_difference,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, cashier_name: string, register_name: string, opened_at: string}>
     */
    private function openShifts(): array
    {
        return CashShift::query()
            ->with('register')
            ->where('status', CashShiftStatus::Open)
            ->orderBy('opened_at')
            ->get()
            ->map(fn (CashShift $shift): array => [
                'id' => $shift->id,
                'cashier_name' => $shift->cashier_name,
                'register_name' => $shift->register->name,
                'opened_at' => $shift->opened_at->toIso8601String(),
            ])
            ->all();
    }
}
