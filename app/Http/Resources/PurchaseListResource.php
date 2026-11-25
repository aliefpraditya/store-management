<?php

namespace App\Http\Resources;

use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the purchase list. Expects `supplier` loaded and `items_count` and `attachments_count`.
 *
 * @mixin Purchase
 */
class PurchaseListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier_name' => $this->supplier->name,
            'supplier_document_number' => $this->supplier_document_number,
            'purchase_date' => $this->purchase_date->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'total_amount' => $this->total_amount,
            'items_count' => $this->items_count,
            'attachments_count' => $this->attachments_count,
            'url' => route('tenant.purchases.show', ['tenant' => tenant('code'), 'purchase' => $this->id]),
        ];
    }
}
