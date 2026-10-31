<?php

namespace App\Http\Resources;

use App\Enums\SaleReturnStatus;
use App\Models\SaleReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One return in full, with what was returned and why. Expects `sale`, `cashShift` and `items.saleItem`. The links
 * are left to the screen that shows it, since the admin pages and the till have different ones.
 *
 * @mixin SaleReturn
 */
class SaleReturnResource extends JsonResource
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
            'refund_method' => $this->refund_method->value,
            'refund_method_label' => $this->refund_method->label(),
            'refund_amount' => $this->refund_amount,
            'reason' => $this->reason,
            'cashier_name' => $this->cashShift->cashier_name,
            'sale' => ['id' => $this->sale->id, 'number' => $this->sale->number, 'customer_name' => $this->sale->customer_name],
            'items' => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'name' => $item->saleItem->product_name,
                'unit' => $item->saleItem->unit_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'refund_subtotal' => $item->refund_subtotal,
                'disposition' => $item->disposition->value,
                'disposition_label' => $item->disposition->label(),
            ])->values()->all(),
            'is_pending' => $this->status === SaleReturnStatus::Pending,
        ];
    }
}
