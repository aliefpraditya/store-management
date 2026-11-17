<?php

namespace App\Services;

use App\Exceptions\CustomerOrderException;
use App\Exceptions\SaleException;
use App\Models\CashShift;
use App\Models\CustomerOrder;
use App\Models\Sale;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * What one press of "Bayar" can produce at the till: a receipt for what is in stock, a customer order (PO) for what the
 * store has to get first, or both. Both are made in one transaction, so a customer never walks away with a receipt for
 * half of the cart and no order for the rest. A cart with only an order, or the collection of an earlier order, works too.
 *
 * When the order takes a deposit (`order.deposit_amount`) the payments are one tender for the receipt and the deposit
 * together, so the customer can hand over everything at once and get one change; {@see TenderSplitter} divides it.
 *
 * @phpstan-import-type SaleData from SaleService
 * @phpstan-import-type OrderData from CustomerOrderService
 *
 * @phpstan-type CheckoutOrderData array{client_uuid: string, customer_name: string, customer_phone: string, customer_address?: string|null, source_note?: string|null, expected_date?: string|null, items: list<array{product_unit_id: int, quantity: numeric-string|float|int}>, deposit_amount?: numeric-string|float|int|null}
 * @phpstan-type CheckoutData SaleData&array{order?: CheckoutOrderData|null}
 */
class CheckoutService
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly CustomerOrderService $customerOrders,
        private readonly TenderSplitter $tenderSplitter,
    ) {}

    /**
     * @param  CheckoutData  $checkoutInput
     * @return array{sale: Sale|null, order: CustomerOrder|null}
     *
     * @throws SaleException
     * @throws CustomerOrderException
     */
    public function checkout(CashShift $shift, User $cashier, array $checkoutInput): array
    {
        return DB::transaction(function () use ($shift, $cashier, $checkoutInput): array {
            $isSaleWanted = filled($checkoutInput['items'] ?? null) || filled($checkoutInput['customer_order_id'] ?? null);
            $orderInput = $checkoutInput['order'] ?? null;
            $deposit = BigDecimal::of((string) ($orderInput['deposit_amount'] ?? 0));
            $depositPayments = [];

            if (($checkoutInput['hold_delivery'] ?? false) && $orderInput === null) {
                throw SaleException::deliveryHoldNeedsOrder();
            }

            if ($orderInput !== null && $deposit->isPositive() && ! $this->isAlreadyRecorded($checkoutInput, $isSaleWanted)) {
                $saleTotal = filled($checkoutInput['items'] ?? null) ? BigDecimal::of($this->sales->quote($checkoutInput['items'])['total']) : BigDecimal::zero();
                $tender = $this->tenderSplitter->split($checkoutInput['payments'] ?? [], $saleTotal, $deposit);

                $checkoutInput['payments'] = $tender['sale'];
                $depositPayments = $tender['deposit'];
            }

            $sale = $isSaleWanted ? $this->sales->create($shift, $cashier, $checkoutInput) : null;

            $order = $orderInput === null
                ? null
                : $this->customerOrders->create($shift, $cashier, [...Arr::except($orderInput, 'deposit_amount'), 'deposits' => $depositPayments, 'origin_sale_id' => $sale?->id]);

            return ['sale' => $sale, 'order' => $order];
        }, StockService::DEADLOCK_ATTEMPTS);
    }

    /**
     * Whether a double submit is coming in: every document this checkout would make already exists under its
     * `client_uuid`. The receipt and the order are made in one transaction, so they exist together. The tender is then not
     * priced and divided again, because the first receipt and order are returned as they were, and pricing again would
     * refuse a cart whose product was switched off since.
     *
     * @param  CheckoutData  $checkoutInput
     */
    private function isAlreadyRecorded(array $checkoutInput, bool $isSaleWanted): bool
    {
        $orderInput = $checkoutInput['order'] ?? null;

        return ($isSaleWanted || $orderInput !== null)
            && (! $isSaleWanted || Sale::query()->where('client_uuid', $checkoutInput['client_uuid'])->exists())
            && ($orderInput === null || CustomerOrder::query()->where('client_uuid', $orderInput['client_uuid'])->exists());
    }
}
