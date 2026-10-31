<?php

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One receipt in full. Expects `items`, `payments` and `register` to be loaded.
 *
 * @mixin Sale
 */
class SaleDetailResource extends JsonResource
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
            'business_date' => $this->business_date->toDateString(),
            'register' => ['code' => $this->register->code, 'name' => $this->register->name],
            'cashier_name' => $this->cashier_name,
            'customer_name' => $this->customer_name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'print_count' => $this->print_count,
            'void_reason' => $this->void_reason,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
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
