<?php

namespace App\Http\Resources;

use App\Enums\CodSettlementStatus;
use App\Enums\DeliveryNoteCodState;
use App\Enums\DeliveryNoteStatus;
use App\Models\DeliveryNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the delivery note list. Expects `sale.payments`, `sale.codSettlement`, `sales` and the `items_count` count.
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
        $codState = $this->codState();

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
            'cod_state' => $codState?->value,
            'cod_state_label' => $codState?->label(),
            'show_url' => route('tenant.delivery-notes.show', ['tenant' => tenant('code'), 'delivery_note' => $this->id]),
        ];
    }

    /**
     * Where the money of the note's COD receipt stands, so the list shows which notes still wait for it; null when the
     * receipt is not COD or the note carries nothing to be paid (cancelled, or all came back). Needs `sale.payments` and
     * `sale.codSettlement` loaded.
     */
    private function codState(): ?DeliveryNoteCodState
    {
        if (! $this->sale->isCod() || in_array($this->status, [DeliveryNoteStatus::Cancelled, DeliveryNoteStatus::Returned], true)) {
            return null;
        }

        return match (true) {
            $this->sale->codSettlement?->status === CodSettlementStatus::AwaitingApproval => DeliveryNoteCodState::AwaitingApproval,
            $this->sale->codSettlement !== null => DeliveryNoteCodState::Received,
            $this->status->hasDeliveredGoods() => DeliveryNoteCodState::Unreceived,
            default => DeliveryNoteCodState::NotDelivered,
        };
    }
}
