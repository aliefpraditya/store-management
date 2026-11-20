<?php

namespace App\Http\Resources;

use App\Enums\CodSettlementStatus;
use App\Enums\DeliveryNoteStatus;
use App\Models\DeliveryNote;
use App\Models\Sale;
use App\Services\CodSettlementService;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A delivered COD sale as the till's "money not received yet" list shows it. The resource is one row of
 * {@see CodSettlementService::settlementQueue()}, with `payments`, `codSettlement` already loaded on its sale.
 *
 * @property array{sale: Sale, offset_amount: BigDecimal, amount_due: BigDecimal, has_unreturned_goods: bool} $resource
 */
class CodSettlementQueueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Sale $sale */
        $sale = $this->resource['sale'];
        $settlement = $sale->codSettlement;
        $tenant = tenant('code');

        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'sold_at' => $sale->sold_at->toIso8601String(),
            'customer_name' => $sale->customer_name,
            'recipient_name' => $this->recipientName($sale),
            'cod_amount' => $sale->codAmount(),
            'offset_amount' => (string) $this->resource['offset_amount']->toScale(2),
            'amount_due' => (string) $this->resource['amount_due']->toScale(2),
            'has_unreturned_goods' => $this->resource['has_unreturned_goods'],
            'state' => $settlement?->status === CodSettlementStatus::AwaitingApproval ? 'awaiting_approval' : 'unsettled',
            'settlement' => $settlement === null ? null : CodSettlementResource::make($settlement)->resolve(),
            'settle_url' => route('tenant.cashier.cod.settle', ['tenant' => $tenant, 'sale' => $sale->id]),
            'approve_url' => route('tenant.cashier.cod.approve', ['tenant' => $tenant, 'sale' => $sale->id]),
        ];
    }

    /**
     * The name on the delivery note that carried the goods, the latest one that was not cancelled.
     */
    private function recipientName(Sale $sale): ?string
    {
        if (! $sale->relationLoaded('deliveryNotes')) {
            return null;
        }

        return $sale->deliveryNotes
            ->reject(fn (DeliveryNote $note): bool => $note->status === DeliveryNoteStatus::Cancelled)
            ->sortByDesc('id')
            ->first()?->recipient_name;
    }
}
