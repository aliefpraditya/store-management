<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Exceptions\CustomerOrderException;
use App\Exceptions\SaleException;
use App\Models\CashShift;
use App\Models\CustomerOrder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
    /** A cart rung up again within this many seconds of the same cart is asked about before it is sold twice. */
    public const REPEAT_WINDOW_SECONDS = 60;

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
     * The receipt this checkout would repeat: one the same cashier made on the same shift in the last
     * {@see self::REPEAT_WINDOW_SECONDS} seconds with the same lines (the same units in the same quantities). A cashier who
     * did not see a receipt get made, or whose screen was reloaded, rings the cart up again; this is how the till tells
     * them before a second receipt is made. A cart that was already recorded under its own `client_uuid` is a resubmit, not a
     * repeat, and the collection of an order has no cart to compare.
     *
     * @param  CheckoutData  $checkoutInput
     */
    public function recentRepeatOf(CashShift $shift, User $cashier, array $checkoutInput): ?Sale
    {
        if (! filled($checkoutInput['items'] ?? null) || filled($checkoutInput['customer_order_id'] ?? null)) {
            return null;
        }

        if (Sale::query()->where('client_uuid', $checkoutInput['client_uuid'])->exists()) {
            return null;
        }

        $wantedLines = $this->quantityByUnit($checkoutInput['items']);

        return Sale::query()
            ->where('cash_shift_id', $shift->id)
            ->where('cashier_id', $cashier->id)
            ->where('status', SaleStatus::Completed)
            ->where('sold_at', '>=', now()->subSeconds(self::REPEAT_WINDOW_SECONDS))
            ->with('items')
            ->orderByDesc('id')
            ->get()
            ->first(fn (Sale $sale): bool => $this->quantityByUnit($sale->items->map(fn (SaleItem $item): array => ['product_unit_id' => $item->product_unit_id, 'quantity' => $item->quantity])->all()) === $wantedLines);
    }

    /**
     * The quantity of each unit in a list of lines, so two lists are the same when they hold the same units in the same
     * quantities whatever their order or how a unit is split over lines.
     *
     * @param  list<array{product_unit_id: int|string, quantity: numeric-string|float|int}>  $lines
     * @return array<int, string> quantity by product unit id, in the order of the ids
     */
    private function quantityByUnit(array $lines): array
    {
        $totals = [];

        foreach ($lines as $line) {
            $unitId = (int) $line['product_unit_id'];
            $totals[$unitId] = ($totals[$unitId] ?? BigDecimal::zero())->plus((string) $line['quantity']);
        }

        ksort($totals);

        return array_map(fn (BigDecimal $quantity): string => (string) $quantity->toScale(3, RoundingMode::HalfUp), $totals);
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
