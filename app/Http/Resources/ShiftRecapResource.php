<?php

namespace App\Http\Resources;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\ShiftDifferenceStatus;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\SaleReturn;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One shift in full for the admin's recap: how it ended, what happened in it, the returns made in it and the money
 * taken from its drawer. Expects `register`, and the `summary`, `returns` and `expenses` that the controller works out
 * and hands in (the expenses with their `category`). The cashier screen has its own, smaller {@see CashShiftResource}.
 *
 * @mixin CashShift
 */
class ShiftRecapResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $summary  {@see ShiftService::summarize()}
     * @param  iterable<SaleReturn>  $returns
     * @param  iterable<Expense>  $expenses
     */
    public function __construct(CashShift $resource, private readonly array $summary, private readonly iterable $returns, private readonly iterable $expenses)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isCounted = $this->status !== CashShiftStatus::Open;

        return [
            'id' => $this->id,
            'label' => $this->label(),
            'business_date' => $this->business_date->toDateString(),
            'register_name' => $this->register->name,
            'cashier_name' => $this->cashier_name,
            'opened_at' => $this->opened_at->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'opening_cash' => $this->opening_cash,
            'opening_cash_note' => $this->opening_cash_note,
            'expected_cash' => $isCounted ? $this->expected_cash : $this->summary['expected_cash'],
            'counted_cash' => $this->counted_cash,
            'cash_difference' => $this->cash_difference,
            'deposit_amount' => $this->deposit_amount,
            'drawer_left_cash' => $this->drawer_left_cash,
            'closing_note' => $this->closing_note,
            'difference_status' => $this->difference_status->value,
            'difference_status_label' => $this->difference_status->label(),
            'decided_by_name' => $this->difference_approved_by === null ? null : User::query()->find($this->difference_approved_by)?->name,
            'decided_at' => $this->difference_approved_at?->toIso8601String(),
            'approval_note' => $this->approval_note,
            'is_own_shift' => $this->cashier_id === $request->user()->id,
            'can_decide' => $this->difference_status === ShiftDifferenceStatus::PendingApproval && $request->user()->canDecideDifferenceOf($this->resource),
            'sale_count' => $this->summary['sale_count'],
            'total_sales' => $this->summary['total_sales'],
            'returns_total' => $this->summary['returns_total'],
            'cash_refunds' => $this->summary['cash_refunds'],
            'void_cash_refunds' => $this->summary['void_cash_refunds'],
            'void_deposits' => $this->summary['void_deposits'],
            'cod' => $this->summary['cod'],
            'order_deposit_cash' => $this->summary['order_deposit_cash'],
            'drawer_expenses' => $this->summary['drawer_expenses'],
            'payments' => collect($this->summary['payments'])
                ->map(fn (string $amount, string $method): array => ['method' => PaymentMethod::from($method)->label(), 'amount' => $amount])
                ->values()
                ->all(),
            'returns' => collect($this->returns)->map(fn (SaleReturn $saleReturn): array => [
                'number' => $saleReturn->number,
                'status_label' => $saleReturn->status->label(),
                'status' => $saleReturn->status->value,
                'refund_amount' => $saleReturn->refund_amount,
                'refund_method_label' => $saleReturn->refund_method->label(),
                'show_url' => route('tenant.returns.show', ['tenant' => tenant('code'), 'sale_return' => $saleReturn->id]),
            ])->values()->all(),
            'expenses' => collect($this->expenses)->map(fn (Expense $expense): array => [
                'number' => $expense->number,
                'description' => $expense->description,
                'category_name' => $expense->category->name,
                'amount' => $expense->amount,
                'status' => $expense->status->value,
                'status_label' => $expense->status->label(),
            ])->values()->all(),
        ];
    }
}
