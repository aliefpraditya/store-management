<?php

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the receipt history. Expects `payments`, `deliveryNotes` and the `items_count` count.
 *
 * @mixin Sale
 */
class SaleHistoryRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'sold_at' => $this->sold_at->toIso8601String(),
            'cashier_name' => $this->cashier_name,
            'customer_name' => $this->customer_name,
            'total' => $this->total,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'items_count' => $this->getAttribute('items_count'),
            'fulfillment' => $this->fulfillment->value,
            'fulfillment_label' => $this->fulfillment->label(),
            'is_delivery_pending' => $this->isDeliveryPending(),
            'payment_methods' => $this->payments->map(fn ($payment): string => $payment->method->label())->unique()->values()->all(),
            'show_url' => route('tenant.sales.show', ['tenant' => tenant('code'), 'sale' => $this->id]),
        ];
    }
}
