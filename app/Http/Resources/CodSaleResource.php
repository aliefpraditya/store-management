<?php

namespace App\Http\Resources;

use App\Enums\DeliveryNoteStatus;
use App\Enums\Permission;
use App\Http\Middleware\EnsureUserCanAccessStore;
use App\Models\DeliveryNote;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A delivered receipt as the till's delivery list shows it, with what is still to pay on delivery (nothing for a receipt paid
 * in advance) and where its delivery stands. The
 * delivery is finished on the delivery note's page, so the list only points there (`open_delivery_note_url`, for those who
 * may view delivery notes).
 * Expects `payments` and `deliveryNotes` to be loaded.
 *
 * @mixin Sale
 */
class CodSaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeNotes = $this->deliveryNotes->reject(fn (DeliveryNote $note): bool => $note->status === DeliveryNoteStatus::Cancelled);
        $latestNote = $activeNotes->sortByDesc('id')->first();
        $openNote = $this->openDeliveryNote();
        $tenant = tenant('code');

        return [
            'id' => $this->id,
            'number' => $this->number,
            'sold_at' => $this->sold_at->toIso8601String(),
            'customer_name' => $this->customer_name,
            'recipient_name' => $latestNote?->recipient_name,
            'is_cod' => $this->isCod(),
            'total' => $this->total,
            'cod_amount' => $this->codAmount(),
            'deposit_amount' => (string) $this->cashDeposit()->plus($this->nonCashDeposit())->toScale(2),
            'has_cash_deposit' => $this->cashDeposit()->isPositive(),
            'has_non_cash_deposit' => $this->nonCashDeposit()->isPositive(),
            'delivery_note' => $latestNote === null ? null : DeliveryNoteLinkResource::make($latestNote)->resolve($request),
            'delivery_status' => $latestNote?->status->value,
            'delivery_status_label' => $latestNote?->status->label() ?? 'Belum ada surat jalan',
            'has_open_delivery_note' => $openNote !== null,
            'open_delivery_note_url' => $openNote !== null && EnsureUserCanAccessStore::grants($request, Permission::DeliveryNotesView)
                ? route('tenant.delivery-notes.show', ['tenant' => $tenant, 'delivery_note' => $openNote->id])
                : null,
            'void_url' => route('tenant.cashier.cod.void', ['tenant' => $tenant, 'sale' => $this->id]),
        ];
    }
}
