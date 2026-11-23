<?php

namespace App\Http\Resources;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the receipt list of the sales report, with the goods and payments to open under it. Expects `items` and
 * `payments`. Who rang the receipt up is left out, as in the rest of the sales report.
 *
 * @mixin Sale
 */
class SalesReportReceiptResource extends JsonResource
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
            'customer_name' => $this->customer_name,
            'fulfillment_label' => $this->fulfillment->label(),
            'total' => $this->total,
            'discount_total' => $this->discount_total,
            'change_amount' => $this->change_amount,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'void_reason' => $this->void_reason,
            'items' => $this->items->map(fn (SaleItem $item): array => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit' => $item->unit_name,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ])->all(),
            'payments' => $this->payments->map(fn (SalePayment $payment): array => [
                'label' => $payment->method->label(),
                'amount' => $payment->amount,
                'reference' => $payment->reference,
            ])->all(),
            'show_url' => route('tenant.sales.show', ['tenant' => tenant('code'), 'sale' => $this->id]),
        ];
    }
}
