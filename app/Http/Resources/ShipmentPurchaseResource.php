<?php

namespace App\Http\Resources;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A purchase as the shipment form works with it: who it is from and the lines whose goods go into containers. Expects
 * `supplier` and `items.product` loaded.
 *
 * @mixin Purchase
 */
class ShipmentPurchaseResource extends JsonResource
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
            'url' => route('tenant.purchases.show', ['tenant' => tenant('code'), 'purchase' => $this->id]),
            'items' => $this->items->map(fn (PurchaseItem $item): array => [
                'id' => $item->id,
                'sku' => $item->product->sku,
                'name' => $item->product->name,
                'unit_code' => $item->unit_code,
                'quantity' => $item->quantity,
            ])->values(),
        ];
    }
}
