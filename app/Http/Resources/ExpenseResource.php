<?php

namespace App\Http\Resources;

use App\Enums\ExpenseSource;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the admin's expense list. Expects `category`, and `cashShift.register` for a drawer expense.
 *
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'business_date' => $this->business_date->toDateString(),
            'category_name' => $this->category->name,
            'description' => $this->description,
            'paid_to' => $this->paid_to,
            'amount' => $this->amount,
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'drawer_label' => $this->source === ExpenseSource::Drawer ? "{$this->cashShift->register->code} · {$this->cashShift->cashier_name}" : null,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'void_reason' => $this->void_reason,
            'voided_by_name' => $this->voided_by_name,
            'created_by_name' => $this->created_by_name,
            'can_be_voided' => $this->isVoidable(),
            'void_url' => route('tenant.expenses.void', ['tenant' => tenant('code'), 'expense' => $this->id]),
        ];
    }
}
