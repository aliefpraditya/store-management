<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\CustomerOrderException;
use App\Exceptions\SaleException;
use App\Models\CashShift;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\CustomerOrderPayment;
use App\Models\DailyClosing;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Goods the customer ordered that the store does not have yet (a PO). The order records what was agreed, at the price
 * agreed, and any deposit (DP) taken; it moves no stock and is no sale. Once the goods have come in (through a stock
 * opname) the order is marked ready and its quantities are set aside from what other sales may take. When the customer
 * collects, {@see SaleService} makes the receipt and the order is completed. Cancelling gives the deposit back.
 *
 * The `client_uuid` makes a double submit return the first order instead of creating two.
 *
 * @phpstan-import-type CartLine from CartPricer
 *
 * @phpstan-type DepositInput array{method: string, amount: numeric-string|float|int, reference?: string|null}
 * @phpstan-type OrderData array{client_uuid: string, customer_name: string, customer_phone: string, customer_address?: string|null, source_note?: string|null, expected_date?: string|null, items: list<CartLine>, deposits?: list<DepositInput>, origin_sale_id?: int|null}
 */
class CustomerOrderService
{
    private const ORDER_TYPE = 'PS';

    public function __construct(
        private readonly CartPricer $cartPricer,
        private readonly DocumentNumberGenerator $documentNumbers,
        private readonly ShiftService $shifts,
        private readonly StockService $stockLedger,
    ) {}

    /**
     * @param  OrderData  $orderInput
     *
     * @throws CustomerOrderException
     * @throws SaleException
     */
    public function create(CashShift $shift, User $cashier, array $orderInput): CustomerOrder
    {
        return DB::transaction(function () use ($shift, $cashier, $orderInput): CustomerOrder {
            $shift = CashShift::query()->with('register')->lockForUpdate()->findOrFail($shift->id);

            $alreadyRecordedOrder = CustomerOrder::query()->where('client_uuid', $orderInput['client_uuid'])->first();

            if ($alreadyRecordedOrder !== null) {
                return $this->orderOfResubmit($alreadyRecordedOrder, $cashier);
            }

            if ($shift->status !== CashShiftStatus::Open) {
                throw CustomerOrderException::shiftNotOpen();
            }

            $lines = $this->cartPricer->price($orderInput['items']);
            $total = $this->cartPricer->totalOf($lines);
            $deposits = $orderInput['deposits'] ?? [];
            $depositTotal = array_reduce($deposits, fn (BigDecimal $sum, array $deposit): BigDecimal => $sum->plus((string) $deposit['amount']), BigDecimal::zero());

            if ($depositTotal->isGreaterThan($total)) {
                throw CustomerOrderException::depositMoreThanTotal();
            }

            $order = $this->recordOrder($shift, $cashier, $orderInput, $total);
            $this->recordLines($order, $lines);

            foreach ($deposits as $deposit) {
                $order->payments()->create([
                    'cash_shift_id' => $shift->id,
                    'kind' => CustomerOrderPayment::KIND_DEPOSIT,
                    'method' => PaymentMethod::from($deposit['method']),
                    'amount' => (string) BigDecimal::of((string) $deposit['amount'])->toScale(2),
                    'reference' => filled($deposit['reference'] ?? null) ? trim($deposit['reference']) : null,
                    'paid_at' => now(),
                ]);
            }

            return $order->load(['items', 'payments']);
        });
    }

    /**
     * The goods have come in: the order is set aside from stock so no other sale takes them. Refused while the stock
     * does not cover it, which tells the staff the arrival still has to go through a stock opname.
     *
     * @throws CustomerOrderException
     */
    public function markReady(CustomerOrder $order): CustomerOrder
    {
        return DB::transaction(function () use ($order): CustomerOrder {
            $lockedOrder = CustomerOrder::query()->with('items.product')->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status !== OrderStatus::Ordered) {
                throw CustomerOrderException::notOrderedYet($lockedOrder->status->label());
            }

            $this->requireGoodsInStock($lockedOrder);

            $lockedOrder->forceFill(['status' => OrderStatus::Ready, 'ready_at' => now()])->save();

            return $lockedOrder;
        }, StockService::DEADLOCK_ATTEMPTS);
    }

    /**
     * For orders still waiting for their goods: whether the stock now covers them, and what is still short. The oldest
     * order is served first from what is not already set aside for ready orders, and takes its share before the next one
     * is looked at, so two orders asking for the same goods are not both told that the stock is enough. Nothing is locked or
     * changed: the answer only tells staff which orders can be marked as arrived, and {@see self::markReady()} decides.
     *
     * @param  Collection<int, CustomerOrder>  $orders  with `items.product` loaded; orders that are not waiting are skipped
     * @return array<int, array{is_covered: bool, shortages: list<array{name: string, text: string}>}> by order id
     */
    public function stockCoverageOf(Collection $orders): array
    {
        $coverage = [];

        foreach ($orders->where('status', OrderStatus::Ordered)->groupBy('warehouse_id') as $warehouseId => $warehouseOrders) {
            $productIds = $warehouseOrders->flatMap(fn (CustomerOrder $order) => $order->items->pluck('product_id'))->unique()->values()->all();
            $reserved = CustomerOrderItem::reservedQuantities($warehouseId, $productIds);
            $available = Stock::query()->where('warehouse_id', $warehouseId)->whereIn('product_id', $productIds)->pluck('quantity', 'product_id')
                ->map(fn (mixed $quantity, int $productId): BigDecimal => BigDecimal::max(BigDecimal::of((string) $quantity)->minus($reserved[$productId] ?? '0'), '0'))
                ->all();

            foreach ($warehouseOrders->sortBy('id') as $order) {
                $coverage[$order->id] = $this->coverageOfOrder($order, $available);
            }
        }

        return $coverage;
    }

    /**
     * @param  array<int, BigDecimal>  $available  what is left of each product, reduced here when the order is covered
     * @return array{is_covered: bool, shortages: list<array{name: string, text: string}>}
     */
    private function coverageOfOrder(CustomerOrder $order, array &$available): array
    {
        $shortages = [];
        $needs = [];

        foreach ($order->items->groupBy('product_id') as $productId => $productItems) {
            $required = $productItems->reduce(fn (BigDecimal $sum, CustomerOrderItem $item): BigDecimal => $sum->plus((string) $item->base_quantity), BigDecimal::zero());
            $left = $available[$productId] ?? BigDecimal::zero();
            $needs[$productId] = $required;

            if ($required->isGreaterThan($left)) {
                $shortages[] = ['name' => $productItems->first()->product_name, 'text' => SaleException::quantityText($required->minus($left), $productItems->first()->product->base_unit_code)];
            }
        }

        if ($shortages === []) {
            foreach ($needs as $productId => $required) {
                $available[$productId] = $available[$productId]->minus($required);
            }
        }

        return ['is_covered' => $shortages === [], 'shortages' => $shortages];
    }

    /**
     * How many orders are open, ready to be collected, and still waiting but already covered by stock (they only need to
     * be marked as arrived): the numbers the till's badge shows.
     *
     * @return array{open: int, ready: int, arrived: int}
     */
    public function badgeCounts(): array
    {
        $openOrders = CustomerOrder::query()->whereIn('status', OrderStatus::unfinished())->with('items.product')->get();

        return [
            'open' => $openOrders->count(),
            'ready' => $openOrders->where('status', OrderStatus::Ready)->count(),
            'arrived' => collect($this->stockCoverageOf($openOrders))->where('is_covered', true)->count(),
        ];
    }

    /**
     * Locks an order that is ready to be collected and returns it with its lines and deposits. Called inside the
     * transaction of the receipt that collects it.
     *
     * @throws CustomerOrderException
     */
    public function claimForCollection(int $orderId): CustomerOrder
    {
        $lockedOrder = CustomerOrder::query()->with(['items', 'payments'])->lockForUpdate()->findOrFail($orderId);

        if ($lockedOrder->status !== OrderStatus::Ready) {
            throw CustomerOrderException::notReadyToCollect($lockedOrder->status->label());
        }

        return $lockedOrder;
    }

    /**
     * The order's lines as the cart of the receipt that collects it, at the price agreed when it was made.
     *
     * @return list<CartLine>
     */
    public function collectionLines(CustomerOrder $order): array
    {
        return $order->items
            ->map(fn (CustomerOrderItem $item): array => ['product_unit_id' => $item->product_unit_id, 'quantity' => (string) $item->quantity, 'unit_price' => (string) $item->unit_price])
            ->values()
            ->all();
    }

    public function completeWith(CustomerOrder $order, Sale $sale): void
    {
        $order->forceFill(['status' => OrderStatus::Completed, 'sale_id' => $sale->id])->save();
    }

    /**
     * Cancels an order that is not finished and gives the deposit back. A cash deposit comes out of the drawer of the
     * canceller's running shift (so it lowers the cash that should be there); a deposit by transfer, QRIS or EDC goes back
     * to the account given.
     *
     * @throws CustomerOrderException
     */
    public function cancel(CustomerOrder $order, User $canceller, string $reason, ?string $refundAccount): CustomerOrder
    {
        return DB::transaction(function () use ($order, $canceller, $reason, $refundAccount): CustomerOrder {
            DailyClosing::lockAgainstClosing();

            $lockedOrder = CustomerOrder::query()->with('payments')->lockForUpdate()->findOrFail($order->id);

            if (! $lockedOrder->status->isUnfinished()) {
                throw CustomerOrderException::alreadyFinished($lockedOrder->status->label());
            }

            if ($lockedOrder->depositBalance()->isPositive()) {
                $this->refundDeposit($lockedOrder, $canceller, $refundAccount);
            }

            $lockedOrder->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancel_reason' => trim($reason),
                'cancelled_at' => now(),
            ])->save();

            return $lockedOrder;
        });
    }

    /**
     * @param  OrderData  $orderInput
     */
    private function recordOrder(CashShift $shift, User $cashier, array $orderInput, BigDecimal $total): CustomerOrder
    {
        $register = $shift->register;

        return CustomerOrder::query()->create([
            'client_uuid' => $orderInput['client_uuid'],
            'number' => $this->documentNumbers->next(self::ORDER_TYPE, now()),
            'register_id' => $register->id,
            'cash_shift_id' => $shift->id,
            'warehouse_id' => $register->warehouse_id,
            'status' => OrderStatus::Ordered,
            'customer_name' => trim($orderInput['customer_name']),
            'customer_phone' => trim($orderInput['customer_phone']),
            'customer_address' => filled($orderInput['customer_address'] ?? null) ? trim($orderInput['customer_address']) : null,
            'source_note' => filled($orderInput['source_note'] ?? null) ? trim($orderInput['source_note']) : null,
            'expected_date' => $orderInput['expected_date'] ?? null,
            'total' => (string) $total->toScale(2),
            'ordered_at' => now(),
            'business_date' => $shift->business_date,
            'origin_sale_id' => $orderInput['origin_sale_id'] ?? null,
            'created_by' => $cashier->id,
            'created_by_name' => $cashier->name,
        ]);
    }

    /**
     * @param  Collection<int, array{attributes: array<string, mixed>}>  $lines
     */
    private function recordLines(CustomerOrder $order, Collection $lines): void
    {
        foreach ($lines as $line) {
            $order->items()->create(Arr::only($line['attributes'], ['product_id', 'product_unit_id', 'product_name', 'unit_name', 'quantity', 'conversion_snapshot', 'base_quantity', 'unit_price', 'subtotal']));
        }
    }

    /**
     * A double submit gets the order it already made. A `client_uuid` that belongs to someone else's order is refused.
     *
     * @throws CustomerOrderException
     */
    private function orderOfResubmit(CustomerOrder $alreadyRecordedOrder, User $cashier): CustomerOrder
    {
        if ($alreadyRecordedOrder->created_by !== $cashier->id) {
            throw CustomerOrderException::orderIdInUse();
        }

        return $alreadyRecordedOrder->load(['items', 'payments']);
    }

    /**
     * Every product must be in the warehouse in full, on top of what other ready orders already hold. Stock rows are
     * locked in product order like a sale does, so the two cannot wait on each other.
     *
     * @throws CustomerOrderException
     */
    private function requireGoodsInStock(CustomerOrder $order): void
    {
        $itemsByProduct = $order->items->groupBy('product_id')->sortKeys();
        $reserved = CustomerOrderItem::reservedQuantities($order->warehouse_id, $itemsByProduct->keys()->all());

        foreach ($itemsByProduct as $productId => $productItems) {
            $required = $productItems->reduce(fn (BigDecimal $sum, CustomerOrderItem $item): BigDecimal => $sum->plus((string) $item->base_quantity), BigDecimal::zero());
            $available = BigDecimal::of($this->stockLedger->lockedQuantity($productId, $order->warehouse_id))->minus($reserved[$productId] ?? '0');

            if ($required->isGreaterThan($available)) {
                throw CustomerOrderException::goodsNotInStock($productItems->first()->product_name, SaleException::quantityText(BigDecimal::max($available, '0'), $productItems->first()->product->base_unit_code));
            }
        }
    }

    /**
     * @throws CustomerOrderException
     */
    private function refundDeposit(CustomerOrder $order, User $canceller, ?string $refundAccount): void
    {
        if (DailyClosing::isClosedOn(today())) {
            throw CustomerOrderException::dayClosed(today()->format('d/m/Y'));
        }

        $refundShift = $this->runningShiftOf($canceller);
        $deposits = $order->payments->where('kind', CustomerOrderPayment::KIND_DEPOSIT);

        if ($deposits->contains(fn (CustomerOrderPayment $deposit): bool => $deposit->method !== PaymentMethod::Cash) && blank($refundAccount)) {
            throw CustomerOrderException::refundAccountNeeded();
        }

        foreach ($deposits as $deposit) {
            $order->payments()->create([
                'cash_shift_id' => $refundShift->id,
                'kind' => CustomerOrderPayment::KIND_REFUND,
                'method' => $deposit->method,
                'amount' => $deposit->amount,
                'reference' => $deposit->method === PaymentMethod::Cash ? null : trim((string) $refundAccount),
                'paid_at' => now(),
            ]);
        }
    }

    /**
     * The shift whose drawer the refund comes out of. It is locked, so the shift cannot be closed while the refund is
     * being booked on it.
     *
     * @throws CustomerOrderException
     */
    private function runningShiftOf(User $canceller): CashShift
    {
        $openShift = $this->shifts->openShiftOf($canceller);

        if ($openShift === null) {
            throw CustomerOrderException::shiftNeededForRefund();
        }

        $lockedShift = CashShift::query()->lockForUpdate()->findOrFail($openShift->id);

        if ($lockedShift->status !== CashShiftStatus::Open) {
            throw CustomerOrderException::shiftNeededForRefund();
        }

        return $lockedShift;
    }
}
