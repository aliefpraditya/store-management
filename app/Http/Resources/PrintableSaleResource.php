<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Models\CustomerOrder;
use App\Models\Sale;
use App\Services\StoreSettingService;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * What the browser prints for a receipt: only what the paper shows, so a cashier printing again is not handed the void
 * reason, the refund account or the like. Includes the customer order made in the same checkout, which is printed on
 * the receipt itself so the customer holds one paper for what was paid and what was ordered. Expects `items` and
 * `payments` to be loaded.
 *
 * @phpstan-import-type Letterhead from StoreSettingService
 *
 * @mixin Sale
 */
class PrintableSaleResource extends JsonResource
{
    /**
     * @param  Letterhead  $letterhead
     */
    public function __construct(
        Sale $resource,
        private readonly bool $isCopy,
        private readonly array $letterhead,
        private readonly string $footer,
        private readonly int $paperWidth,
        private readonly ?CustomerOrder $order,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payments = SaleDetailResource::paymentsOf($this->resource);
        $printedPayments = $this->order === null ? $payments : $this->paymentsWithDeposit($payments, $this->order);

        return [
            'number' => $this->number,
            'sold_at' => $this->sold_at->toIso8601String(),
            'cashier_name' => $this->cashier_name,
            'customer_name' => $this->customer_name,
            'status' => $this->status->value,
            'total' => $this->total,
            'change_amount' => $this->change_amount,
            'items' => SaleDetailResource::itemsOf($this->resource),
            'payments' => array_map(self::namedAsOnPaper(...), $printedPayments),
            'order' => $this->order === null ? null : CustomerOrderResource::make($this->order)->resolve(),
            'is_copy' => $this->isCopy,
            'store' => $this->letterhead,
            'footer' => $this->footer,
            'paper_width' => $this->paperWidth,
        ];
    }

    /**
     * What is left to pay on delivery is the receipt's "kekurangan", so a COD line is named that way on paper.
     *
     * @param  array{method: string, amount: string, reference: string|null}  $payment
     * @return array{method: string, amount: string, reference: string|null}
     */
    private static function namedAsOnPaper(array $payment): array
    {
        return $payment['method'] === PaymentMethod::Cod->label() ? [...$payment, 'method' => 'Kekurangan (COD)'] : $payment;
    }

    /**
     * What the customer handed over for the receipt and the order's deposit together, one line per method, so the paper
     * adds up: payments minus the change is the total plus the deposit.
     *
     * @param  list<array{method: string, amount: string, reference: string|null}>  $receiptPayments
     * @return list<array{method: string, amount: string, reference: string|null}>
     */
    private function paymentsWithDeposit(array $receiptPayments, CustomerOrder $order): array
    {
        return collect([...$receiptPayments, ...CustomerOrderResource::depositPaymentsOf($order)])
            ->groupBy('method')
            ->map(fn (Collection $payments, string $method): array => [
                'method' => $method,
                'amount' => (string) $payments->reduce(fn (BigDecimal $sum, array $payment): BigDecimal => $sum->plus($payment['amount']), BigDecimal::zero())->toScale(2),
                'reference' => $payments->pluck('reference')->filter()->first(),
            ])
            ->values()
            ->all();
    }
}
