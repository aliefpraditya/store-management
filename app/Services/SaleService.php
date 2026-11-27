<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleFulfillment;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\CustomerOrderException;
use App\Exceptions\DeliveryNoteException;
use App\Exceptions\SaleException;
use App\Models\CashShift;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Sale;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rings up a sale in one transaction: the receipt with its lines and payments, and the stock taken from the
 * register's warehouse. Prices and costs are read from the database, never from the cashier's screen. The
 * `client_uuid` makes a double submit return the first receipt instead of selling twice.
 *
 * A receipt can also be the collection of a customer order (`customer_order_id`): the lines and prices come from the
 * order, and the deposit already taken goes on the receipt as the `order_deposit` payment, so only the rest is paid.
 *
 * @phpstan-import-type CartLine from CartPricer
 * @phpstan-import-type PricedLine from CartPricer
 *
 * @phpstan-type SalePaymentInput array{method: string, amount: numeric-string|float|int, reference?: string|null}
 * @phpstan-type DeliveryNoteInput array{recipient_name?: string|null, recipient_address?: string|null, recipient_phone?: string|null, notes?: string|null, include_linked_sale_items?: bool|null}
 * @phpstan-type SaleData array{client_uuid: string, customer_name?: string|null, items?: list<CartLine>, payments?: list<SalePaymentInput>, delivery_note?: DeliveryNoteInput|null, fulfillment?: string|null, hold_delivery?: bool|null, customer_order_id?: int|null}
 */
class SaleService
{
    private const RECEIPT_TYPE = 'INV';

    public function __construct(
        private readonly StockService $stockLedger,
        private readonly DocumentNumberGenerator $documentNumbers,
        private readonly DeliveryNoteService $deliveryNotes,
        private readonly StoreSettingService $settings,
        private readonly CartPricer $cartPricer,
        private readonly CustomerOrderService $customerOrders,
        private readonly StockOpnameService $stockOpnames,
    ) {}

    /**
     * @param  SaleData  $saleInput
     *
     * @throws SaleException
     */
    public function create(CashShift $shift, User $cashier, array $saleInput): Sale
    {
        return DB::transaction(function () use ($shift, $cashier, $saleInput): Sale {
            $shift = CashShift::query()->with('register')->lockForUpdate()->findOrFail($shift->id);

            // Checked under the shift lock so two submits of the same cart cannot both get past it.
            $alreadyRecordedSale = Sale::query()->where('client_uuid', $saleInput['client_uuid'])->first();

            if ($alreadyRecordedSale !== null) {
                return $this->receiptOfResubmit($alreadyRecordedSale, $cashier);
            }

            if ($shift->status !== CashShiftStatus::Open) {
                throw SaleException::shiftNotOpen();
            }

            $warehouseId = $shift->register->warehouse_id;
            $collectedOrder = $this->collectedOrderOf($saleInput, $warehouseId);

            $lines = $this->cartPricer->price($collectedOrder === null ? $saleInput['items'] : $this->customerOrders->collectionLines($collectedOrder));

            $this->requireNoOpnameLock($lines, $warehouseId);
            $this->requireStock($lines, $warehouseId, $collectedOrder?->id);
            $packChanges = $this->planPackChanges($lines, $warehouseId, $collectedOrder?->id);

            $total = $this->cartPricer->totalOf($lines);
            $payments = $saleInput['payments'] ?? [];
            $depositOfOrder = $collectedOrder?->depositBalance() ?? BigDecimal::zero();
            [$paidTotal, $changeAmount] = $this->settle($total, $payments, $depositOfOrder);

            $sale = $this->recordReceipt($shift, $cashier, $saleInput, $total, $paidTotal, $changeAmount, $this->fulfillmentOf($saleInput));
            $this->recordLines($sale, $lines, $cashier, $packChanges);
            $this->recordPayments($sale, $payments, $depositOfOrder);

            if ($collectedOrder !== null) {
                $this->customerOrders->completeWith($collectedOrder, $sale);
            }

            $this->recordDeliveryNote($sale->load('items'), $cashier, $saleInput);

            return $sale->load(['items', 'payments', 'deliveryNotes']);
        }, StockService::DEADLOCK_ATTEMPTS);
    }

    /**
     * The customer order this receipt collects, locked, or null for an ordinary sale. Its goods sit in one warehouse,
     * so it can only be collected at a register of that warehouse.
     *
     * @param  SaleData  $saleInput
     *
     * @throws SaleException
     */
    private function collectedOrderOf(array $saleInput, int $warehouseId): ?CustomerOrder
    {
        if (blank($saleInput['customer_order_id'] ?? null)) {
            return null;
        }

        try {
            $order = $this->customerOrders->claimForCollection($saleInput['customer_order_id']);
        } catch (CustomerOrderException $exception) {
            throw new SaleException($exception->getMessage(), previous: $exception);
        }

        if ($order->warehouse_id !== $warehouseId) {
            throw SaleException::orderIsInAnotherWarehouse();
        }

        return $order;
    }

    /**
     * How the goods reach the customer. When the cashier does not say, a receipt paid on delivery (COD) is delivered and
     * any other is taken at the store. COD only exists for a delivered receipt, so asking for COD with the goods taken at
     * the store is refused.
     *
     * @param  SaleData  $saleInput
     *
     * @throws SaleException
     */
    private function fulfillmentOf(array $saleInput): SaleFulfillment
    {
        $isCod = collect($saleInput['payments'] ?? [])->contains(fn (array $payment): bool => $payment['method'] === PaymentMethod::Cod->value);
        $chosen = SaleFulfillment::tryFrom((string) ($saleInput['fulfillment'] ?? ''));

        if ($chosen === null) {
            return $isCod ? SaleFulfillment::Delivery : SaleFulfillment::InStore;
        }

        if ($isCod && $chosen === SaleFulfillment::InStore) {
            throw SaleException::codNeedsDelivery();
        }

        return $chosen;
    }

    /**
     * A delivered receipt cannot leave without its delivery note: it is made in the same transaction as the receipt, for
     * everything on it, so a note cannot be forgotten and a note cannot exist without its receipt. A receipt taken at the
     * store has none. The one exception is a delivered receipt made together with a customer order (`hold_delivery`): its
     * goods wait in the store and go out on the delivery note of the order when it arrives, so everything is delivered once.
     *
     * @param  SaleData  $saleInput
     *
     * @throws SaleException
     */
    private function recordDeliveryNote(Sale $sale, User $cashier, array $saleInput): void
    {
        if ($sale->fulfillment !== SaleFulfillment::Delivery || ($saleInput['hold_delivery'] ?? false)) {
            return;
        }

        $noteInput = $saleInput['delivery_note'] ?? [];

        if (blank($noteInput['recipient_name'] ?? null) || blank($noteInput['recipient_address'] ?? null)) {
            throw SaleException::deliveryNoteRequired();
        }

        try {
            $this->deliveryNotes->create($sale, $cashier, [
                ...$noteInput,
                'client_uuid' => (string) Str::uuid(),
                'items' => $this->linesOfCodNote($sale, (bool) ($noteInput['include_linked_sale_items'] ?? false)),
            ]);
        } catch (DeliveryNoteException $exception) {
            throw new SaleException($exception->getMessage(), previous: $exception);
        }
    }

    /**
     * Everything on the receipt, and, when asked, what is still undelivered of the receipt made together with the order this
     * receipt collects, so the customer gets one delivery for both.
     *
     * @return list<array{sale_item_id: int, quantity: string}>
     */
    private function linesOfCodNote(Sale $sale, bool $isIncludingLinkedSale): array
    {
        return collect($this->deliveryNotes->deliverableLines($sale))
            ->filter(fn (array $line): bool => $line['sale_number'] === $sale->number || $isIncludingLinkedSale)
            ->filter(fn (array $line): bool => BigDecimal::of($line['remaining'])->isPositive())
            ->map(fn (array $line): array => ['sale_item_id' => $line['id'], 'quantity' => $line['remaining']])
            ->values()
            ->all();
    }

    /**
     * What the cart would cost right now, line by line, without selling anything. Used to settle an exchange before
     * the replacement receipt exists.
     *
     * @param  list<CartLine>  $items
     * @return array{total: string, lines: list<array{product_unit_id: int, name: string, unit: string, quantity: string, unit_price: string, subtotal: string}>}
     *
     * @throws SaleException
     */
    public function quote(array $items): array
    {
        $lines = $this->cartPricer->price($items);

        return [
            'total' => (string) $this->cartPricer->totalOf($lines)->toScale(2),
            'lines' => $lines->map(fn (array $line): array => [
                'product_unit_id' => $line['attributes']['product_unit_id'],
                'name' => $line['product_name'],
                'unit' => $line['attributes']['unit_name'],
                'quantity' => $line['attributes']['quantity'],
                'unit_price' => $line['attributes']['unit_price'],
                'subtotal' => $line['attributes']['subtotal'],
            ])->values()->all(),
        ];
    }

    /**
     * A double submit gets the receipt it already made. A `client_uuid` that belongs to someone else's sale is
     * refused, so a receipt cannot be read by sending its uuid.
     *
     * @throws SaleException
     */
    private function receiptOfResubmit(Sale $alreadyRecordedSale, User $cashier): Sale
    {
        if ($alreadyRecordedSale->cashier_id !== $cashier->id) {
            throw SaleException::receiptIdInUse();
        }

        return $alreadyRecordedSale->load(['items', 'payments', 'deliveryNotes']);
    }

    /**
     * @param  SaleData  $saleInput
     */
    private function recordReceipt(CashShift $shift, User $cashier, array $saleInput, BigDecimal $total, BigDecimal $paidTotal, BigDecimal $changeAmount, SaleFulfillment $fulfillment): Sale
    {
        $register = $shift->register;

        return Sale::query()->create([
            'client_uuid' => $saleInput['client_uuid'],
            'number' => $this->documentNumbers->next(self::RECEIPT_TYPE, now(), $register),
            'register_id' => $register->id,
            'cash_shift_id' => $shift->id,
            'warehouse_id' => $register->warehouse_id,
            'sold_at' => now(),
            'business_date' => $shift->business_date,
            'cashier_id' => $cashier->id,
            'cashier_name' => $cashier->name,
            'customer_name' => filled($saleInput['customer_name'] ?? null) ? trim($saleInput['customer_name']) : Sale::GENERAL_CUSTOMER_NAME,
            'subtotal' => (string) $total,
            'total' => (string) $total,
            'paid_total' => (string) $paidTotal,
            'change_amount' => (string) $changeAmount,
            'status' => SaleStatus::Completed,
            'fulfillment' => $fulfillment,
        ]);
    }

    /**
     * @param  Collection<int, array{product_id: int, base_quantity: string, attributes: array<string, mixed>}>  $lines
     * @param  array<int, int>  $packChanges  change in whole sealed packs by line index, see {@see self::planPackChanges()}
     */
    private function recordLines(Sale $sale, Collection $lines, User $cashier, array $packChanges): void
    {
        foreach ($lines as $index => $line) {
            $sale->items()->create($line['attributes']);

            $this->stockLedger->record(
                $line['product_id'],
                $sale->warehouse_id,
                StockMovementType::Sale,
                '-'.$line['base_quantity'],
                $sale,
                $cashier->id,
                packChange: $packChanges[$index] ?? 0,
            );
        }
    }

    /**
     * @param  list<SalePaymentInput>  $payments
     * @param  BigDecimal  $depositOfOrder  what the customer already put down on the order this receipt collects
     */
    private function recordPayments(Sale $sale, array $payments, BigDecimal $depositOfOrder): void
    {
        foreach ($payments as $payment) {
            $sale->payments()->create([
                'method' => PaymentMethod::from($payment['method']),
                'amount' => $payment['amount'],
                'reference' => filled($payment['reference'] ?? null) ? trim($payment['reference']) : null,
            ]);
        }

        if ($depositOfOrder->isPositive()) {
            $sale->payments()->create(['method' => PaymentMethod::OrderDeposit, 'amount' => (string) $depositOfOrder->toScale(2)]);
        }
    }

    /**
     * When the store locks sales during opname, a product that an opname is counting cannot be sold.
     *
     * @param  Collection<int, array{product_id: int, product_name: string}>  $lines
     *
     * @throws SaleException
     */
    private function requireNoOpnameLock(Collection $lines, int $warehouseId): void
    {
        if (! $this->settings->isSalesLockedDuringOpname()) {
            return;
        }

        $countedProductIds = $this->stockOpnames->productIdsBeingCounted($warehouseId, $lines->pluck('product_id')->all());
        $countedLine = $lines->first(fn (array $line): bool => in_array($line['product_id'], $countedProductIds, true));

        if ($countedLine !== null) {
            throw SaleException::productIsBeingCounted($countedLine['product_name']);
        }
    }

    /**
     * Every product must have enough in the warehouse for all its lines together, even when sold in different units.
     * What is set aside for customer orders that are ready to be collected is not available, except to the order being
     * collected (`$collectedOrderId`). Stock rows are locked in a fixed order so two sales cannot wait on each other,
     * and the set-aside quantities are read only after that, with a locking read: an order marked ready at the same
     * moment (it locks the same stock rows) is then either seen here or waits for this sale.
     *
     * @param  Collection<int, array{product_id: int, product_name: string, base_unit_code: string, base_quantity: string}>  $lines
     *
     * @throws SaleException
     */
    private function requireStock(Collection $lines, int $warehouseId, ?int $collectedOrderId): void
    {
        $linesByProduct = $lines->groupBy('product_id')->sortKeys();
        $productIds = $linesByProduct->keys()->all();
        $lockedQuantities = $this->stockLedger->lockedQuantities($productIds, $warehouseId);
        $reserved = CustomerOrderItem::reservedQuantities($warehouseId, $productIds, $collectedOrderId, isReadForDecision: true);

        foreach ($linesByProduct as $productId => $productLines) {
            $required = $productLines->reduce(fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['base_quantity']), BigDecimal::zero());
            $available = BigDecimal::max(BigDecimal::of($lockedQuantities[$productId])->minus($reserved[$productId] ?? '0'), '0');

            if ($required->isGreaterThan($available)) {
                throw SaleException::insufficientStock($productLines[0]['product_name'], SaleException::quantityText($available, $productLines[0]['base_unit_code']));
            }
        }
    }

    /**
     * For products with a sealed pack unit (a roll of wire), the total in the base unit being enough is not enough: whole
     * packs can only be sold while whole packs are left, and a smaller unit opens a pack once the loose pieces run out. This
     * works out, line by line in cart order, how many whole packs each line takes off the stock, and refuses a line the
     * packs cannot cover. The stock rows are already locked by {@see self::requireStock()}. Packs that other ready orders
     * have set aside are not counted as there (the order being collected, `$collectedOrderId`, may take its own).
     *
     * @param  Collection<int, array{product: Product, product_id: int, product_name: string, base_unit_code: string, base_quantity: string, is_sealed_pack: bool, attributes: array<string, mixed>}>  $lines
     * @return array<int, int> change in whole packs (zero or negative) by line index, for the lines of products that have a sealed pack unit
     *
     * @throws SaleException
     */
    private function planPackChanges(Collection $lines, int $warehouseId, ?int $collectedOrderId): array
    {
        $productIds = $lines->filter(fn (array $line): bool => $line['product']->sealedPackUnit() !== null)->pluck('product_id')->unique()->sort()->values()->all();

        if ($productIds === []) {
            return [];
        }

        $lockedStocks = $this->stockLedger->lockedStocks($productIds, $warehouseId);
        $reservedPacks = CustomerOrderItem::reservedPacks($warehouseId, $productIds, $collectedOrderId, isReadForDecision: true);
        $shelves = [];
        $packChanges = [];

        foreach ($lines as $index => $line) {
            $packUnit = $line['product']->sealedPackUnit();

            if ($packUnit === null) {
                continue;
            }

            $productId = $line['product_id'];
            $shelves[$productId] ??= new SealedPackStock(
                BigDecimal::of($lockedStocks[$productId]['quantity']),
                $lockedStocks[$productId]['intact_packs'],
                BigDecimal::of($packUnit->conversion_factor),
                $line['product_name'],
                $packUnit->unit_code,
                $line['base_unit_code'],
                $reservedPacks[$productId] ?? 0,
            );

            $packChanges[$index] = $line['is_sealed_pack']
                ? $shelves[$productId]->takeSealedPacks(BigDecimal::of($line['attributes']['quantity'])->toInt())
                : $shelves[$productId]->takeLoose(BigDecimal::of($line['base_quantity']));
        }

        return $packChanges;
    }

    /**
     * Checks the payments cover the total and that any change can come out of the cash received. A COD payment counts
     * as covering its part (it is paid on delivery), so it must be the one row that makes the payments add up to the
     * total exactly.
     *
     * @param  list<SalePaymentInput>  $payments
     * @param  BigDecimal|null  $alreadyPaid  money already received before this sale, such as the deposit of a customer order
     * @return array{0: BigDecimal, 1: BigDecimal} paid total and change
     *
     * @throws SaleException
     */
    public function settle(BigDecimal $total, array $payments, ?BigDecimal $alreadyPaid = null): array
    {
        $paidTotal = $alreadyPaid ?? BigDecimal::zero();
        $cashReceived = BigDecimal::zero();
        $codPaymentCount = 0;

        foreach ($payments as $payment) {
            $amount = BigDecimal::of((string) $payment['amount']);
            $paidTotal = $paidTotal->plus($amount);

            if ($payment['method'] === PaymentMethod::Cash->value) {
                $cashReceived = $cashReceived->plus($amount);
            }

            if ($payment['method'] === PaymentMethod::Cod->value) {
                $codPaymentCount++;
            }
        }

        if ($codPaymentCount > 1) {
            throw SaleException::codMoreThanOnce();
        }

        if ($paidTotal->isLessThan($total)) {
            throw $codPaymentCount === 1 ? SaleException::codMustBeTheRest() : SaleException::paymentTooLow($total);
        }

        if ($codPaymentCount === 1 && ! $paidTotal->isEqualTo($total)) {
            throw SaleException::codMustBeTheRest();
        }

        $change = $paidTotal->minus($total);

        if ($change->isGreaterThan($cashReceived)) {
            throw SaleException::changeExceedsCash();
        }

        return [$paidTotal->toScale(2), $change->toScale(2)];
    }
}
