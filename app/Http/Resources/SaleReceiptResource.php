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
            'id' => $this->id,
            'print_url' => route('tenant.cashier.receipts.print', ['tenant' => tenant('code'), 'sale' => $this->id]),
            'delivery_url' => route('tenant.cashier.receipts.delivery', ['tenant' => tenant('code'), 'sale' => $this->id]),
            'delivery_note_store_url' => route('tenant.cashier.receipts.delivery-notes.store', ['tenant' => tenant('code'), 'sale' => $this->id]),
            'number' => $this->number,
            'sold_at' => $this->sold_at->toIso8601String(),
            'cashier_name' => $this->cashier_name,
            'customer_name' => $this->customer_name,
            'total' => $this->total,
            'paid_total' => $this->paid_total,
            'change_amount' => $this->change_amount,
            'items' => SaleDetailResource::itemsOf($this->resource),
            'payments' => SaleDetailResource::paymentsOf($this->resource),
            'is_cod' => $this->isCod(),
            'fulfillment' => $this->fulfillment->value,
            'cod_amount' => $this->codAmount(),
            'delivery_notes' => $this->relationLoaded('deliveryNotes')
                ? $this->deliveryNotes->map(fn ($note): array => [
                    'id' => $note->id,
                    'number' => $note->number,
                    'print_url' => route('tenant.cashier.delivery-notes.print', ['tenant' => tenant('code'), 'delivery_note' => $note->id]),
                ])->values()->all()
                : [],
        ];
    }
}
