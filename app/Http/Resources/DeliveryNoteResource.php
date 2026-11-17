<?php

namespace App\Http\Resources;

use App\Models\DeliveryNote;
use App\Models\Sale;
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
            ],
            'other_sale_numbers' => $this->items->loadMissing('saleItem.sale')
                ->map(fn ($item): string => $item->saleItem->sale->number)
                ->unique()
                ->reject(fn (string $number): bool => $number === $this->sale->number)
                ->values()
                ->all(),
            'return_sales' => $this->returnSales(),
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

    /**
     * The receipts that have goods that came back on this note, each with the page to make the return on: a return is
     * made on the receipt the goods were sold on, which for a combined note is not always the note's own receipt.
     *
     * @return list<array{number: string, new_return_url: string}>
     */
    private function returnSales(): array
    {
        return $this->items->loadMissing('saleItem.sale')
            ->filter(fn ($item): bool => (float) $item->quantity_returned > 0)
            ->map(fn ($item) => $item->saleItem->sale)
            ->unique('id')
            ->map(fn (Sale $sale): array => [
                'number' => $sale->number,
                'new_return_url' => route('tenant.returns.create', ['tenant' => tenant('code'), 'sale' => $sale->id]),
            ])
            ->values()
            ->all();
    }
}
