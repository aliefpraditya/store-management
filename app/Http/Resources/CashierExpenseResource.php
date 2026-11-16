<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An expense as the till shows it: what was taken from the drawer, without any admin links. Expects `category`.
 *
 * @mixin Expense
 */
class CashierExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'category_name' => $this->category->name,
            'description' => $this->description,
            'paid_to' => $this->paid_to,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
