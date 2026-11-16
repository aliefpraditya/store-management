<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\CustomerOrderPayment;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer order (PO) as the till shows and prints it. Expects `items` and `payments` to be loaded (and `originSale`
 * when known).
 *
 * @mixin CustomerOrder
 */
class CustomerOrderResource extends JsonResource
{
    /**
     * The money put down on the order, by the method's label, in the shape of a receipt's payments. Expects `payments`
     * to be loaded.
     *
     * @return list<array{method: string, amount: string, reference: string|null}>
     */
    public static function depositPaymentsOf(CustomerOrder $order): array
    {
        return $order->payments
            ->where('kind', CustomerOrderPayment::KIND_DEPOSIT)
            ->map(fn (CustomerOrderPayment $payment): array => ['method' => $payment->method->label(), 'amount' => (string) $payment->amount, 'reference' => $payment->reference])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenant = tenant('code');
        $deposit = $this->depositBalance();
        $deposits = $this->payments->where('kind', CustomerOrderPayment::KIND_DEPOSIT);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'ordered_at' => $this->ordered_at->toIso8601String(),
            'expected_date' => $this->expected_date?->toDateString(),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_address' => $this->customer_address,
            'source_note' => $this->source_note,
            'created_by_name' => $this->created_by_name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'total' => $this->total,
            'deposit' => (string) $deposit->toScale(2),
            'remaining' => (string) BigDecimal::of((string) $this->total)->minus($deposit)->toScale(2),
            'deposit_methods' => $deposits->map(fn (CustomerOrderPayment $payment): string => $payment->method->label())->unique()->values()->all(),
            'has_cash_deposit' => $deposit->isPositive() && $deposits->contains(fn (CustomerOrderPayment $payment): bool => $payment->method === PaymentMethod::Cash),
            'has_non_cash_deposit' => $deposit->isPositive() && $deposits->contains(fn (CustomerOrderPayment $payment): bool => $payment->method !== PaymentMethod::Cash),
            'origin_sale_number' => $this->relationLoaded('originSale') ? $this->originSale?->number : null,
            'items' => $this->items->map(fn (CustomerOrderItem $item): array => [
                'name' => $item->product_name,
                'unit' => $item->unit_name,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'subtotal' => (string) $item->subtotal,
            ])->values()->all(),
            'can_mark_ready' => $this->status === OrderStatus::Ordered,
            'can_collect' => $this->status === OrderStatus::Ready,
            'can_cancel' => $this->status->isUnfinished(),
            'ready_url' => route('tenant.cashier.orders.ready', ['tenant' => $tenant, 'order' => $this->id]),
            'cancel_url' => route('tenant.cashier.orders.cancel', ['tenant' => $tenant, 'order' => $this->id]),
            'print_url' => route('tenant.cashier.orders.print', ['tenant' => $tenant, 'order' => $this->id]),
        ];
    }
}
