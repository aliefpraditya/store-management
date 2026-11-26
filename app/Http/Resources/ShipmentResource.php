<?php

namespace App\Http\Resources;

use App\Models\Shipment;
use App\Models\ShipmentAttachment;
use App\Models\ShipmentContainer;
use App\Models\ShipmentLine;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shipment for its detail and edit screens. Expects `carrier`, `attachments`, `purchases.supplier`, `purchases.items.product` and
 * `containers.lines.purchaseItem.purchase.supplier` plus `containers.lines.purchaseItem.product` loaded.
 *
 * @mixin Shipment
 */
class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $routeParameters = ['tenant' => tenant('code'), 'shipment' => $this->id];

        return [
            'id' => $this->id,
            'number' => $this->number,
            'carrier_id' => $this->carrier_id,
            'carrier_name' => $this->carrier->name,
            'vessel_name' => $this->vessel_name,
            'departure_date' => $this->departure_date?->toDateString(),
            'estimated_arrival_date' => $this->estimated_arrival_date?->toDateString(),
            'expected_arrival_date' => $this->expectedArrivalDate()?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'notes' => $this->notes,
            'created_by_name' => $this->created_by_name,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'is_editable' => $this->status->isEditable(),
            'purchases' => ShipmentPurchaseResource::collection($this->purchases)->resolve(),
            'containers' => $this->containers->map(fn (ShipmentContainer $container): array => [
                'id' => $container->id,
                'number' => $container->number,
                'total_freight_cost' => $this->sumOfFreight($container->lines->all()),
                'lines' => $container->lines->map(fn (ShipmentLine $line): array => [
                    'purchase_item_id' => $line->purchase_item_id,
                    'purchase_number' => $line->purchaseItem->purchase->number,
                    'supplier_name' => $line->purchaseItem->purchase->supplier->name,
                    'sku' => $line->purchaseItem->product->sku,
                    'product_name' => $line->purchaseItem->product->name,
                    'unit_code' => $line->purchaseItem->unit_code,
                    'quantity' => $line->quantity,
                    'freight_cost' => $line->freight_cost,
                ])->values(),
            ])->values(),
            'allocations' => $this->containers->flatMap(fn (ShipmentContainer $container) => $container->lines->map(fn (ShipmentLine $line): array => [
                'purchase_item_id' => $line->purchase_item_id,
                'container_number' => $container->number,
                'quantity' => $line->quantity,
                'freight_cost' => $line->freight_cost,
            ]))->values(),
            'attachments' => $this->attachments->map(fn (ShipmentAttachment $attachment): array => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'size' => $attachment->size,
                'url' => route('tenant.shipments.attachments.show', [...$routeParameters, 'attachment' => $attachment->id]),
                'delete_url' => route('tenant.shipments.attachments.destroy', [...$routeParameters, 'attachment' => $attachment->id]),
            ])->values(),
            'total_freight_cost' => $this->sumOfFreight($this->containers->flatMap(fn (ShipmentContainer $container) => $container->lines)->all()),
            'urls' => [
                'index' => route('tenant.shipments.index', ['tenant' => tenant('code')]),
                'edit' => route('tenant.shipments.edit', $routeParameters),
                'update' => route('tenant.shipments.update', $routeParameters),
                'cancel' => route('tenant.shipments.cancel', $routeParameters),
                'attachments' => route('tenant.shipments.attachments.store', $routeParameters),
            ],
        ];
    }

    /**
     * @param  list<ShipmentLine>  $lines
     */
    private function sumOfFreight(array $lines): string
    {
        $total = array_reduce($lines, fn (BigDecimal $sum, ShipmentLine $line): BigDecimal => $sum->plus((string) $line->freight_cost), BigDecimal::zero());

        return (string) $total->toScale(2);
    }
}
