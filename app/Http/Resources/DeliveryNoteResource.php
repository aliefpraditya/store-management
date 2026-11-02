<?php

namespace App\Http\Resources;

use App\Models\DeliveryNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One delivery note in full, with what may be done to it next. No prices: the paper goes to the customer without
 * them. Expects `items` and `sale`. There is no address for the sheet: the print step answers a signed one for the
 * exact print it counted.
 *
 * @mixin DeliveryNote
 */
class DeliveryNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actionUrl = fn (string $action): string => route("tenant.delivery-notes.{$action}", ['tenant' => tenant('code'), 'delivery_note' => $this->id]);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'business_date' => $this->business_date->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'recipient_address' => $this->recipient_address,
            'driver_name' => $this->driver_name,
            'vehicle_number' => $this->vehicle_number,
            'notes' => $this->notes,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_by_name' => $this->created_by_name,
            'print_count' => $this->print_count,
            'sale' => [
                'number' => $this->sale->number,
                'show_url' => route('tenant.sales.show', ['tenant' => tenant('code'), 'sale' => $this->sale_id]),
                'new_return_url' => route('tenant.returns.create', ['tenant' => tenant('code'), 'sale' => $this->sale_id]),
            ],
            'items' => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'name' => $item->product_name,
                'unit' => $item->unit_name,
                'quantity' => $item->quantity,
                'quantity_delivered' => $item->quantity_delivered,
                'quantity_returned' => $item->quantity_returned,
            ])->values()->all(),
            'can_ship' => $this->status->canBeShipped(),
            'can_complete' => $this->status->canBeCompleted(),
            'can_cancel' => $this->status->canBeCancelled(),
            'urls' => [
                'index' => route('tenant.delivery-notes.index', ['tenant' => tenant('code')]),
                'ship' => $actionUrl('ship'),
                'complete' => $actionUrl('complete'),
                'cancel' => $actionUrl('cancel'),
                'print' => $actionUrl('print'),
            ],
        ];
    }
}
