<?php

namespace App\Http\Resources;

use App\Models\SaleReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the return list. Expects `sale` and `cashShift`.
 *
 * @mixin SaleReturn
 */
class SaleReturnListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'returned_at' => $this->returned_at->toIso8601String(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'sale_number' => $this->sale->number,
            'cashier_name' => $this->cashShift->cashier_name,
            'refund_method_label' => $this->refund_method->label(),
            'refund_amount' => $this->refund_amount,
            'show_url' => route('tenant.returns.show', ['tenant' => tenant('code'), 'sale_return' => $this->id]),
        ];
    }
}
