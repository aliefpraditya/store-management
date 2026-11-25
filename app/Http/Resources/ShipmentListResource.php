<?php

namespace App\Http\Resources;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the shipment list. Expects `carrier` loaded and `purchases_count`, `containers_count` and `total_freight_cost`.
 *
 * @mixin Shipment
 */
class ShipmentListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'carrier_name' => $this->carrier->name,
            'vessel_name' => $this->vessel_name,
            'departure_date' => $this->departure_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'purchases_count' => $this->purchases_count,
            'containers_count' => $this->containers_count,
            'total_freight_cost' => $this->total_freight_cost,
            'url' => route('tenant.shipments.show', ['tenant' => tenant('code'), 'shipment' => $this->id]),
        ];
    }
}
