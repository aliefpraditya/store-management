<?php

namespace App\Http\Resources;

use App\Models\CashShift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the shift recap. Expects `register` and the `sale_count` and `sales_total` aggregates.
 *
 * @mixin CashShift
 */
class CashShiftListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
            'sale_count' => (int) $this->getAttribute('sale_count'),
            'sales_total' => (string) ($this->getAttribute('sales_total') ?? '0.00'),
            'cash_difference' => $this->cash_difference,
            'difference_status' => $this->difference_status->value,
            'difference_status_label' => $this->difference_status->label(),
            'needs_follow_up' => $this->needsFollowUp(),
            'show_url' => route('tenant.shifts.show', ['tenant' => tenant('code'), 'shift' => $this->id]),
        ];
    }
}
