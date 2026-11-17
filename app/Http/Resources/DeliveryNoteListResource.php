<?php

namespace App\Http\Resources;

use App\Models\DeliveryNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the delivery note list. Expects `sale`, `sales` and the `items_count` count.
 *
 * @mixin DeliveryNote
 */
class DeliveryNoteListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'business_date' => $this->business_date->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'recipient_name' => $this->recipient_name,
            'sale_number' => $this->sale->number,
            'other_sale_numbers' => $this->sales->pluck('number')->reject(fn (string $number): bool => $number === $this->sale->number)->values()->all(),
            'items_count' => $this->getAttribute('items_count'),
            'show_url' => route('tenant.delivery-notes.show', ['tenant' => tenant('code'), 'delivery_note' => $this->id]),
        ];
    }
}
