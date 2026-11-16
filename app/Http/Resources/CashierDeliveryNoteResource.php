<?php

namespace App\Http\Resources;

use App\Models\DeliveryNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A delivery note as the till's dialog shows it: what is on it and how far the delivery is, with the address to count
 * a print. Following the note through shipping and delivery stays with the admin pages, so there are no such actions.
 * The sheet's address comes from the print answer. Expects `items`.
 *
 * @mixin DeliveryNote
 */
class CashierDeliveryNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'print_count' => (int) $this->print_count,
            'print_url' => route('tenant.cashier.delivery-notes.print', ['tenant' => tenant('code'), 'delivery_note' => $this->id]),
            'items' => $this->items->map(fn ($noteItem): array => [
                'sale_item_id' => $noteItem->sale_item_id,
                'name' => $noteItem->product_name,
                'unit' => $noteItem->unit_name,
                'quantity' => $noteItem->quantity,
                'quantity_delivered' => $noteItem->quantity_delivered,
                'quantity_returned' => $noteItem->quantity_returned,
            ])->values()->all(),
        ];
    }
}
