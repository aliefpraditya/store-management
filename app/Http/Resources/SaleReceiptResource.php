<?php

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A finished receipt as the cashier screen shows it. Expects `items` and `payments` to be loaded.
 *
 * @mixin Sale
 */
class SaleReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'sold_at' => $this->sold_at->toIso8601String(),
            'cashier_name' => $this->cashier_name,
            'customer_name' => $this->customer_name,
            'total' => $this->total,
            'paid_total' => $this->paid_total,
            'change_amount' => $this->change_amount,
            'items' => $this->items->map(fn ($item): array => [
                'name' => $item->product_name,
                'unit' => $item->unit_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ])->values()->all(),
            'payments' => $this->payments->map(fn ($payment): array => [
                'method' => $payment->method->label(),
                'amount' => $payment->amount,
                'reference' => $payment->reference,
            ])->values()->all(),
        ];
    }
}
