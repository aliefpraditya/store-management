<?php

namespace App\Http\Resources;

use App\Enums\DeliveryNoteStatus;
use App\Models\DeliveryNote;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A COD sale as the till's COD list shows it, with what is still to pay on delivery and where its delivery stands.
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
        $tenant = tenant('code');

        return [
            'id' => $this->id,
            'number' => $this->number,
            'sold_at' => $this->sold_at->toIso8601String(),
            'customer_name' => $this->customer_name,
            'recipient_name' => $latestNote?->recipient_name,
            'cod_amount' => $this->codAmount(),
            'deposit_amount' => (string) $this->cashDeposit()->plus($this->nonCashDeposit())->toScale(2),
            'has_cash_deposit' => $this->cashDeposit()->isPositive(),
            'has_non_cash_deposit' => $this->nonCashDeposit()->isPositive(),
            'delivery_status' => $latestNote?->status->value,
            'delivery_status_label' => $latestNote?->status->label() ?? 'Belum ada surat jalan',
            'can_mark_delivered' => $activeNotes->contains(fn (DeliveryNote $note): bool => $note->status->isUnfinished()),
            'delivered_url' => route('tenant.cashier.cod.delivered', ['tenant' => $tenant, 'sale' => $this->id]),
            'void_url' => route('tenant.cashier.cod.void', ['tenant' => $tenant, 'sale' => $this->id]),
        ];
    }
}
