<?php

namespace App\Http\Resources;

use App\Models\Purchase;
use App\Models\PurchaseAttachment;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A purchase for its detail and edit screens. Expects `supplier`, `warehouse`, `items.product.productUnits`,
 * `items.product.baseUnit` and `attachments` loaded.
 *
 * @mixin Purchase
 */
class PurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $routeParameters = ['tenant' => tenant('code'), 'purchase' => $this->id];

        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier->name,
            'supplier_document_number' => $this->supplier_document_number,
            'purchase_date' => $this->purchase_date->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse->name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'total_amount' => $this->total_amount,
            'notes' => $this->notes,
            'created_by_name' => $this->created_by_name,
            'confirmed_by_name' => $this->confirmed_by_name,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'is_editable' => $this->status->isEditable(),
            'is_cancellable' => $this->status->isCancellable(),
            'items' => $this->items->map(fn (PurchaseItem $item): array => [
                'id' => $item->id,
                'product' => [
                    'id' => $item->product->id,
                    'sku' => $item->product->sku,
                    'name' => $item->product->name,
                    'brand' => null,
                    'base_unit' => $item->product->base_unit_code,
                    'units' => $item->product->productUnits->pluck('unit_code')->values(),
                ],
                'unit_code' => $item->unit_code,
                'conversion_factor' => $item->conversion_factor,
                'quantity' => $item->quantity,
                'base_quantity' => $item->base_quantity,
                'unit_cost' => $item->unit_cost,
                'subtotal' => $item->subtotal,
                'received_base_quantity' => $item->received_base_quantity,
                'notes' => $item->notes,
            ])->values(),
            'attachments' => $this->attachments->map(fn (PurchaseAttachment $attachment): array => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'size' => $attachment->size,
                'is_image' => str_starts_with($attachment->mime_type, 'image/'),
                'url' => route('tenant.purchases.attachments.show', [...$routeParameters, 'attachment' => $attachment->id]),
                'delete_url' => route('tenant.purchases.attachments.destroy', [...$routeParameters, 'attachment' => $attachment->id]),
            ])->values(),
            'urls' => [
                'index' => route('tenant.purchases.index', ['tenant' => tenant('code')]),
                'edit' => route('tenant.purchases.edit', $routeParameters),
                'update' => route('tenant.purchases.update', $routeParameters),
                'confirm' => route('tenant.purchases.confirm', $routeParameters),
                'cancel' => route('tenant.purchases.cancel', $routeParameters),
                'attachments' => route('tenant.purchases.attachments.store', $routeParameters),
            ],
        ];
    }
}
