<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Enums\SaleReturnStatus;
use App\Models\SaleReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One return in full, with what was returned and why. Expects `sale`, `cashShift` and `items.saleItem`. The links
 * are left to the screen that shows it, since the admin pages and the till have different ones.
 *
 * @mixin SaleReturn
 */
class SaleReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'returned_at' => $this->returned_at->toIso8601String(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'resolution' => $this->resolution->value,
            'resolution_label' => $this->resolution->label(),
            'refund_method' => $this->refund_method->value,
            'refund_method_label' => $this->refund_method->label(),
            'refund_account' => $this->refund_account,
            'credit_amount' => $this->credit_amount,
            'refund_amount' => $this->refund_amount,
            'exchange' => $this->isExchange() ? $this->exchangeOf() : null,
            'reason' => $this->reason,
            'cashier_name' => $this->cashShift->cashier_name,
            'sale' => ['id' => $this->sale->id, 'number' => $this->sale->number, 'customer_name' => $this->sale->customer_name],
            'items' => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'name' => $item->saleItem->product_name,
                'unit' => $item->saleItem->unit_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'refund_subtotal' => $item->refund_subtotal,
                'disposition' => $item->disposition->value,
                'disposition_label' => $item->disposition->label(),
            ])->values()->all(),
            'is_pending' => $this->status === SaleReturnStatus::Pending,
        ];
    }

    /**
     * The replacement goods as the customer chose them, what the customer paid on top, and the receipt once approved.
     *
     * The address to count a print of that receipt is the till's receipt one, which both an admin and the cashier whose
     * receipt it is may use; counting answers the signed address of the sheet.
     *
     * @return array{items: list<array<string, string>>, total: string, payments: list<array{method: string, amount: string}>, sale_number: string|null, print_url: string|null}
     */
    private function exchangeOf(): array
    {
        $exchange = $this->exchange_data;
        $saleId = $this->exchange_sale_id;
        $receiptUrl = fn (string $name): ?string => $saleId === null ? null : route("tenant.cashier.receipts.{$name}", ['tenant' => tenant('code'), 'sale' => $saleId]);

        return [
            'items' => $exchange['items'],
            'total' => $exchange['replacement_total'],
            'payments' => array_map(fn (array $payment): array => ['method' => PaymentMethod::from($payment['method'])->label(), 'amount' => $payment['amount']], $exchange['payments']),
            'sale_number' => $this->exchangeSale?->number,
            'print_url' => $receiptUrl('print'),
        ];
    }
}
