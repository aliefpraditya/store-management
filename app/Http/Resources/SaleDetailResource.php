<?php

namespace App\Http\Resources;

use App\Enums\DeliveryNoteStatus;
use App\Models\DeliveryNote;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One receipt in full. Expects `items`, `payments` and `register` to be loaded.
 *
 * @mixin Sale
 */
class SaleDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'sold_at' => $this->sold_at->toIso8601String(),
            'business_date' => $this->business_date->toDateString(),
            'register' => ['code' => $this->register->code, 'name' => $this->register->name],
            'cashier_name' => $this->cashier_name,
            'customer_name' => $this->customer_name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'print_count' => $this->print_count,
            'void_reason' => $this->void_reason,
            'voided_by_name' => $this->voided_by_name,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_cash_refund' => $this->void_cash_refund,
            'void_refund_account' => $this->void_refund_account,
            'is_cod' => $this->isCod(),
            'fulfillment' => $this->fulfillment->value,
            'fulfillment_label' => $this->fulfillment->label(),
            'is_delivery_pending' => $this->loadMissing('deliveryNotes')->isDeliveryPending(),
            'has_open_delivery_note' => $this->openDeliveryNote() !== null,
            'delivery_note' => $this->latestDeliveryNoteSummary($request),
            'cod_amount' => $this->codAmount(),
            'has_cash_deposit' => $this->cashDeposit()->isPositive(),
            'has_non_cash_deposit' => $this->nonCashDeposit()->isPositive(),
            'cod_settlement' => $this->relationLoaded('codSettlement') && $this->codSettlement !== null
                ? [
                    ...CodSettlementResource::make($this->codSettlement)->resolve(),
                    'approve_url' => route('tenant.sales.cod-settlement.approve', ['tenant' => tenant('code'), 'sale' => $this->id]),
                ]
                : null,
            'cod_advance_payments' => $this->relationLoaded('codAdvancePayments')
                ? CodAdvancePaymentResource::collection($this->codAdvancePayments)->resolve($request)
                : [],
            'delivery_notes' => $this->relationLoaded('deliveryNotes')
                ? $this->deliveryNotes->map(fn ($note): array => [
                    'number' => $note->number,
                    'status' => $note->status->value,
                    'status_label' => $note->status->label(),
                    'show_url' => route('tenant.delivery-notes.show', ['tenant' => tenant('code'), 'delivery_note' => $note->id]),
                ])->values()->all()
                : [],
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'total' => $this->total,
            'paid_total' => $this->paid_total,
            'change_amount' => $this->change_amount,
            'items' => self::itemsOf($this->resource),
            'payments' => self::paymentsOf($this->resource),
        ];
    }

    /**
     * The latest delivery note that was not cancelled, shown with the receipt as its number and status (a link to its page
     * for those who may view delivery notes) and the address to count a print from the till; null without one. Needs
     * `deliveryNotes` loaded.
     *
     * @return array<string, mixed>|null
     */
    private function latestDeliveryNoteSummary(Request $request): ?array
    {
        $note = $this->deliveryNotes
            ->reject(fn (DeliveryNote $note): bool => $note->status === DeliveryNoteStatus::Cancelled)
            ->sortByDesc('id')
            ->first();

        if ($note === null) {
            return null;
        }

        return [
            ...DeliveryNoteLinkResource::make($note)->resolve($request),
            'print_url' => route('tenant.cashier.delivery-notes.print', ['tenant' => tenant('code'), 'delivery_note' => $note->id]),
        ];
    }

    /**
     * The lines of a receipt as every receipt view shows them. Expects items to be loaded.
     *
     * @return list<array{name: string, unit: string, quantity: string, unit_price: string, subtotal: string}>
     */
    public static function itemsOf(Sale $sale): array
    {
        return $sale->items->map(fn ($item): array => [
            'name' => $item->product_name,
            'unit' => $item->unit_name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'subtotal' => $item->subtotal,
        ])->values()->all();
    }

    /**
     * The payments of a receipt, by the method's label. Expects payments to be loaded.
     *
     * @return list<array{method: string, amount: string, reference: string|null}>
     */
    public static function paymentsOf(Sale $sale): array
    {
        return $sale->payments->map(fn ($payment): array => [
            'method' => $payment->method->label(),
            'amount' => $payment->amount,
            'reference' => $payment->reference,
        ])->values()->all();
    }
}
