<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReturnDisposition;
use App\Enums\ReturnResolution;
use App\Enums\SaleReturnProgress;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\SaleException;
use App\Exceptions\SaleReturnException;
use App\Models\CashShift;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use App\Models\Warehouse;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Takes goods back from a receipt and gives the money back. A return is asked for in the asker's running shift and
 * waits for an admin; only when it is approved does the stock come back (to the shelf or to dead stock) and the
 * refund count against the shift's drawer. Goods waiting for approval already count as returned, so the same
 * goods cannot be returned twice. The value follows the price on the receipt.
 *
 * A return is either a refund or an exchange. In an exchange the customer takes other goods instead: they are sold on
 * a receipt of their own, paid with the credit of the returned goods, and the customer pays what is more or gets the
 * rest back as a refund. That receipt is made when the return is approved, so nothing of it moves before then.
 *
 * `client_uuid` makes a double submit return the first return instead of refunding twice.
 *
 * @phpstan-type ReturnLine array{sale_item_id: int, quantity: numeric-string|float|int, disposition: string}
 * @phpstan-type ExchangeLine array{product_unit_id: int, quantity: numeric-string|float|int}
 * @phpstan-type ExchangePayment array{method: string, amount: numeric-string|float|int, reference?: string|null}
 * @phpstan-type ReturnInput array{client_uuid: string, resolution?: string, refund_method: string, refund_account?: string|null, reason?: string|null, items: list<ReturnLine>, exchange_items?: list<ExchangeLine>, exchange_payments?: list<ExchangePayment>}
 */
class SaleReturnService
{
    private const NUMBER_TYPE = 'RET';

    /** Returns that hold goods: waiting ones reserve them, finished ones took them. */
    private const GOODS_HOLDING_STATUSES = [SaleReturnStatus::Pending, SaleReturnStatus::Completed];

    /** How long the till's link for returning one receipt stays valid: a shift's length. */
    private const RETURN_LINK_HOURS = 12;

    public function __construct(
        private readonly StockService $stockLedger,
        private readonly DocumentNumberGenerator $documentNumbers,
        private readonly SaleService $saleService,
    ) {}

    /**
     * The returns list, newest first, narrowed by status, a search on the return or receipt number or the customer,
     * and the day the return was made.
     *
     * @return LengthAwarePaginator<int, SaleReturn>
     */
    public function paginateReturns(string $statusValue, string $search, ?CarbonInterface $from, ?CarbonInterface $to, int $perPage): LengthAwarePaginator
    {
        return SaleReturn::query()
            ->with(['sale', 'cashShift'])
            ->when(SaleReturnStatus::tryFrom($statusValue), fn (Builder $query, SaleReturnStatus $status) => $query->where('status', $status))
            ->when($from, fn (Builder $query, CarbonInterface $from) => $query->where('returned_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn (Builder $query, CarbonInterface $to) => $query->where('returned_at', '<', $to->copy()->addDay()->startOfDay()))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('sale', fn (Builder $sale) => $sale->where('number', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%"))))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Completed receipts that still have goods left to return, found by receipt number or customer name, newest first.
     * The "something left" test runs in the query, so receipts that are used up cannot crowd out the ones that are not.
     *
     * @return Collection<int, Sale>
     */
    public function returnableSales(string $term, int $limit): Collection
    {
        $holdingStatuses = array_map(fn (SaleReturnStatus $status): string => $status->value, self::GOODS_HOLDING_STATUSES);
        $statusPlaceholders = implode(', ', array_fill(0, count($holdingStatuses), '?'));

        return Sale::query()
            ->with(['items', 'payments'])
            ->where('status', SaleStatus::Completed)
            ->whereRaw(
                "exists (select 1 from sale_items where sale_items.sale_id = sales.id and sale_items.quantity > coalesce((
                    select sum(sale_return_items.quantity) from sale_return_items
                    join sale_returns on sale_returns.id = sale_return_items.sale_return_id
                    where sale_return_items.sale_item_id = sale_items.id and sale_returns.status in ({$statusPlaceholders})
                ), 0))",
                $holdingStatuses,
            )
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")))
            ->orderByDesc('sold_at')
            ->limit($limit)
            ->get();
    }

    /**
     * The addresses the till uses to load and to save a return for one receipt. They are signed, so a cashier can
     * only reach a receipt through a link the server gave them (the exact-number lookup, or their own shift's
     * receipts) and cannot walk through the receipt ids.
     *
     * @return array{return_url: string, return_store_url: string}
     */
    public function signedLinksFor(Sale $sale): array
    {
        $expiresAt = now()->addHours(self::RETURN_LINK_HOURS);
        $parameters = ['tenant' => tenant('code'), 'sale' => $sale->id];

        return [
            'return_url' => URL::temporarySignedRoute('tenant.cashier.receipts.return', $expiresAt, $parameters),
            'return_store_url' => URL::temporarySignedRoute('tenant.cashier.receipts.returns.store', $expiresAt, $parameters),
        ];
    }

    /**
     * The receipt lines with how much of each can still be returned.
     *
     * @return list<array{id: int, name: string, unit: string, quantity: string, returned: string, remaining: string, unit_price: string}>
     */
    public function returnableLines(Sale $sale): array
    {
        $returnedQuantityBySaleItemId = $this->returnedQuantities($sale, self::GOODS_HOLDING_STATUSES);

        return $sale->items->map(fn (SaleItem $saleItem): array => [
            'id' => $saleItem->id,
            'name' => $saleItem->product_name,
            'unit' => $saleItem->unit_name,
            'quantity' => $saleItem->quantity,
            'returned' => (string) BigDecimal::of((string) ($returnedQuantityBySaleItemId[$saleItem->id] ?? 0))->toScale(3),
            'remaining' => (string) $this->remainingQuantity($saleItem, $returnedQuantityBySaleItemId),
            'unit_price' => $saleItem->unit_price,
        ])->values()->all();
    }

    /**
     * The way the customer paid most, so the refund goes back the same way unless the screen changes it.
     */
    public function defaultRefundMethod(Sale $sale): PaymentMethod
    {
        $paidMost = $sale->payments
            ->filter(fn ($payment): bool => $payment->method !== PaymentMethod::ReturnCredit)
            ->sortByDesc('amount')
            ->first();

        return $paidMost?->method ?? PaymentMethod::Cash;
    }

    /**
     * Records a return that waits for an admin's decision. A `client_uuid` that was already recorded returns that
     * return, so a retried request cannot refund twice.
     *
     * @param  ReturnInput  $returnInput
     *
     * @throws SaleReturnException
     */
    public function create(Sale $sale, CashShift $shift, User $requester, array $returnInput): SaleReturn
    {
        return DB::transaction(function () use ($sale, $shift, $requester, $returnInput): SaleReturn {
            $sale = Sale::query()->with('items')->lockForUpdate()->findOrFail($sale->id);
            $shift = CashShift::query()->lockForUpdate()->findOrFail($shift->id);

            // Checked under the sale lock so two submits of the same return cannot both get past it.
            $alreadyRecordedReturn = SaleReturn::query()->where('client_uuid', $returnInput['client_uuid'])->first();

            if ($alreadyRecordedReturn !== null) {
                return $this->returnOfResubmit($alreadyRecordedReturn, $sale, $requester);
            }

            if ($sale->status !== SaleStatus::Completed) {
                throw SaleReturnException::saleNotCompleted();
            }

            if ($shift->status !== CashShiftStatus::Open) {
                throw SaleReturnException::shiftNotOpen();
            }

            $pricedRows = $this->priceRows($sale, $returnInput['items']);
            $returnedGoodsCredit = $pricedRows->reduce(fn (BigDecimal $sum, array $row): BigDecimal => $sum->plus($row['refund_subtotal']), BigDecimal::zero());

            $resolution = ReturnResolution::from($returnInput['resolution'] ?? ReturnResolution::Refund->value);
            $exchange = $resolution === ReturnResolution::Exchange ? $this->priceExchange($returnInput, $returnedGoodsCredit) : null;
            $refund = $exchange['refund'] ?? $returnedGoodsCredit;
            $refundMethod = PaymentMethod::from($returnInput['refund_method']);
            $refundAccount = $this->refundAccountOf($refundMethod, $refund, $returnInput['refund_account'] ?? null);

            $saleReturn = SaleReturn::query()->create([
                'client_uuid' => $returnInput['client_uuid'],
                'number' => $this->documentNumbers->next(self::NUMBER_TYPE, now()),
                'sale_id' => $sale->id,
                'register_id' => $shift->register_id,
                'cash_shift_id' => $shift->id,
                'returned_at' => now(),
                'resolution' => $resolution,
                'refund_method' => $refundMethod,
                'refund_account' => $refundAccount,
                'credit_amount' => (string) $returnedGoodsCredit->toScale(2),
                'refund_amount' => (string) $refund->toScale(2),
                'exchange_data' => $exchange['data'] ?? null,
                'status' => SaleReturnStatus::Pending,
                'reason' => filled($returnInput['reason'] ?? null) ? trim($returnInput['reason']) : null,
                'requested_by' => $requester->id,
            ]);

            foreach ($pricedRows as $pricedRow) {
                $saleReturn->items()->create($pricedRow);
            }

            return $saleReturn->load('items.saleItem');
        });
    }

    /**
     * An admin's own return needs no one else's approval, so it is asked for and approved in one go. A resubmit
     * finds the return already done and returns it as it is.
     *
     * @param  ReturnInput  $returnInput
     *
     * @throws SaleReturnException
     */
    public function createApproved(Sale $sale, CashShift $shift, User $admin, array $returnInput): SaleReturn
    {
        return DB::transaction(function () use ($sale, $shift, $admin, $returnInput): SaleReturn {
            $saleReturn = $this->create($sale, $shift, $admin, $returnInput);

            return $saleReturn->status === SaleReturnStatus::Pending ? $this->approve($saleReturn, $admin) : $saleReturn;
        }, StockService::DEADLOCK_ATTEMPTS);
    }

    /**
     * Takes the goods back into their warehouses; from now on the refund counts against the shift's drawer.
     *
     * @throws SaleReturnException
     */
    public function approve(SaleReturn $saleReturn, User $approver): SaleReturn
    {
        return DB::transaction(function () use ($saleReturn, $approver): SaleReturn {
            // Locked before the return itself, the same order create() takes them, so approvals of one receipt queue up.
            $sale = Sale::query()->lockForUpdate()->findOrFail($saleReturn->sale_id);
            $saleReturn = $this->lockedPending($saleReturn, 'disetujui');

            foreach ($saleReturn->items->sortBy('product_id') as $item) {
                $this->stockLedger->record(
                    $item->product_id,
                    $item->destination_warehouse_id,
                    StockMovementType::SaleReturn,
                    $item->base_quantity,
                    $saleReturn,
                    $approver->id,
                );
            }

            if ($saleReturn->isExchange()) {
                $this->sellReplacement($saleReturn, $sale);
            }

            $saleReturn->forceFill(['status' => SaleReturnStatus::Completed, 'approved_by' => $approver->id])->save();
            $this->refreshReturnStatus($sale);

            return $saleReturn->load('items.saleItem');
        }, StockService::DEADLOCK_ATTEMPTS);
    }

    /**
     * @throws SaleReturnException
     */
    public function reject(SaleReturn $saleReturn, User $approver): SaleReturn
    {
        return DB::transaction(function () use ($saleReturn, $approver): SaleReturn {
            $saleReturn = $this->lockedPending($saleReturn, 'ditolak');

            $saleReturn->forceFill(['status' => SaleReturnStatus::Rejected, 'approved_by' => $approver->id])->save();

            return $saleReturn->load('items.saleItem');
        });
    }

    /**
     * A `client_uuid` that belongs to someone else's return, or to another receipt, is refused, so a return cannot be
     * read by sending its uuid.
     *
     * @throws SaleReturnException
     */
    private function returnOfResubmit(SaleReturn $alreadyRecordedReturn, Sale $sale, User $requester): SaleReturn
    {
        if ($alreadyRecordedReturn->requested_by !== $requester->id || $alreadyRecordedReturn->sale_id !== $sale->id) {
            throw SaleReturnException::returnIdInUse();
        }

        return $alreadyRecordedReturn->load('items.saleItem');
    }

    /**
     * Money paid back by transfer, QRIS or EDC needs the account it goes to. Cash, or nothing to pay back, needs none.
     */
    private function isRefundAccountNeeded(PaymentMethod $method, BigDecimal $refund): bool
    {
        return $method !== PaymentMethod::Cash && ! $refund->isZero();
    }

    /**
     * An account typed for a non-cash method is kept even when nothing is to be paid back yet: an exchange can end up
     * owing money once prices move before approval, and that is when the account is needed.
     *
     * @throws SaleReturnException
     */
    private function refundAccountOf(PaymentMethod $method, BigDecimal $refund, ?string $account): ?string
    {
        if ($method === PaymentMethod::Cash) {
            return null;
        }

        if (blank($account)) {
            return $this->isRefundAccountNeeded($method, $refund) ? throw SaleReturnException::refundAccountMissing() : null;
        }

        return trim($account);
    }

    /**
     * Prices the replacement goods now and settles them against the value of what comes back: the customer pays
     * what is more, or gets the rest back. Checked when the exchange is asked for, so it is refused on the spot
     * rather than when an admin looks at it.
     *
     * @param  ReturnInput  $returnInput
     * @return array{data: array<string, mixed>, refund: BigDecimal}
     *
     * @throws SaleReturnException
     */
    private function priceExchange(array $returnInput, BigDecimal $credit): array
    {
        $extraPayments = $this->extraPaymentsOf($returnInput['exchange_payments'] ?? []);

        try {
            $quote = $this->saleService->quote($returnInput['exchange_items']);
            $replacementTotal = BigDecimal::of($quote['total']);

            $this->saleService->settle($replacementTotal, $this->exchangePayments($credit, $replacementTotal, $extraPayments));
        } catch (SaleException $exception) {
            throw SaleReturnException::fromSale($exception);
        }

        return [
            'data' => ['items' => $quote['lines'], 'replacement_total' => $quote['total'], 'payments' => $extraPayments],
            'refund' => $credit->minus($this->creditUsed($credit, $replacementTotal)),
        ];
    }

    /**
     * Makes the receipt for the replacement goods in the shift of the return, paid with the return's credit and
     * whatever the customer added. Prices are read again: if they moved since the exchange was asked for and the
     * payments no longer cover it, approving fails with the reason instead of selling short.
     *
     * @throws SaleReturnException
     */
    private function sellReplacement(SaleReturn $saleReturn, Sale $returnedSale): void
    {
        $exchange = $saleReturn->exchange_data;
        $credit = BigDecimal::of($saleReturn->credit_amount);
        $items = array_map(fn (array $line): array => ['product_unit_id' => $line['product_unit_id'], 'quantity' => $line['quantity']], $exchange['items']);

        try {
            $replacementTotal = BigDecimal::of($this->saleService->quote($items)['total']);
            $refund = $credit->minus($this->creditUsed($credit, $replacementTotal));

            $this->assertRepricedExchangeCanBeSettled($saleReturn, $replacementTotal, $refund);

            $replacementSale = $this->saleService->create(
                CashShift::query()->findOrFail($saleReturn->cash_shift_id),
                User::query()->findOrFail($saleReturn->requested_by),
                [
                    'client_uuid' => (string) Str::uuid(),
                    'customer_name' => $returnedSale->customer_name,
                    'items' => $items,
                    'payments' => $this->exchangePayments($credit, $replacementTotal, $exchange['payments']),
                ],
            );
        } catch (SaleException $exception) {
            throw SaleReturnException::fromSale($exception);
        }

        $saleReturn->forceFill([
            'exchange_sale_id' => $replacementSale->id,
            'refund_amount' => (string) $refund->toScale(2),
        ]);
    }

    /**
     * Prices can move between the ask and the approval. Two things that were fine then can be wrong now, and the
     * admin has no field to fix them, so approving stops and says to reject and ask again: money has become payable
     * to a method that has no account, or an extra payment the customer made is no longer needed and would vanish.
     *
     * @throws SaleReturnException
     */
    private function assertRepricedExchangeCanBeSettled(SaleReturn $saleReturn, BigDecimal $replacementTotal, BigDecimal $refund): void
    {
        $exchange = $saleReturn->exchange_data;
        $credit = BigDecimal::of($saleReturn->credit_amount);

        $isExtraPaymentNowUnneeded = $exchange['payments'] !== []
            && BigDecimal::of($exchange['replacement_total'])->isGreaterThan($credit)
            && $replacementTotal->isLessThanOrEqualTo($credit);

        if ($isExtraPaymentNowUnneeded) {
            throw SaleReturnException::exchangePaymentsNoLongerNeeded();
        }

        if ($this->isRefundAccountNeeded($saleReturn->refund_method, $refund) && blank($saleReturn->refund_account)) {
            throw SaleReturnException::refundAccountMissingAfterRepricing();
        }
    }

    /**
     * The payments of the replacement receipt: the return's credit as far as the goods cost, and the customer's own
     * payments only when the goods cost more than the credit.
     *
     * @param  list<array{method: string, amount: string, reference: string|null}>  $extraPayments
     * @return list<array{method: string, amount: string, reference?: string|null}>
     */
    private function exchangePayments(BigDecimal $credit, BigDecimal $replacementTotal, array $extraPayments): array
    {
        $payments = [['method' => PaymentMethod::ReturnCredit->value, 'amount' => (string) $this->creditUsed($credit, $replacementTotal)->toScale(2)]];

        return $replacementTotal->isGreaterThan($credit) ? [...$payments, ...$extraPayments] : $payments;
    }

    private function creditUsed(BigDecimal $credit, BigDecimal $replacementTotal): BigDecimal
    {
        return $credit->isLessThan($replacementTotal) ? $credit : $replacementTotal;
    }

    /**
     * @param  list<array{method: string, amount: numeric-string|float|int, reference?: string|null}>  $payments
     * @return list<array{method: string, amount: string, reference: string|null}>
     */
    private function extraPaymentsOf(array $payments): array
    {
        return collect($payments)
            ->reject(fn (array $payment): bool => BigDecimal::of((string) $payment['amount'])->isZero())
            ->map(fn (array $payment): array => [
                'method' => $payment['method'],
                'amount' => (string) BigDecimal::of((string) $payment['amount'])->toScale(2),
                'reference' => filled($payment['reference'] ?? null) ? trim($payment['reference']) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @throws SaleReturnException
     */
    private function lockedPending(SaleReturn $saleReturn, string $action): SaleReturn
    {
        $saleReturn = SaleReturn::query()->with(['items', 'sale'])->lockForUpdate()->findOrFail($saleReturn->id);

        if ($saleReturn->status !== SaleReturnStatus::Pending) {
            throw SaleReturnException::wrongStatus($action, $saleReturn->status->label());
        }

        return $saleReturn;
    }

    /**
     * One row per receipt line to return: what comes back, its value, and the warehouse it goes to.
     *
     * @param  list<ReturnLine>  $lines
     * @return Collection<int, array<string, mixed>> the attributes of each return item
     *
     * @throws SaleReturnException
     */
    private function priceRows(Sale $sale, array $lines): Collection
    {
        $requestedLines = collect($lines)
            ->map(fn (array $line): array => [...$line, 'quantity' => BigDecimal::of((string) $line['quantity'])])
            ->reject(fn (array $line): bool => $line['quantity']->isZero());

        if ($requestedLines->isEmpty()) {
            throw SaleReturnException::noItems();
        }

        $saleItems = $sale->items->keyBy('id');
        $returnedQuantityBySaleItemId = $this->returnedQuantities($sale, self::GOODS_HOLDING_STATUSES);

        return $requestedLines->map(function (array $line) use ($saleItems, $returnedQuantityBySaleItemId, $sale): array {
            $saleItem = $saleItems->get($line['sale_item_id']) ?? throw SaleReturnException::lineNotOnSale();
            $remaining = $this->remainingQuantity($saleItem, $returnedQuantityBySaleItemId);
            $disposition = ReturnDisposition::from($line['disposition']);

            if ($line['quantity']->isGreaterThan($remaining)) {
                throw SaleReturnException::exceedsReturnable($saleItem->product_name, $this->quantityText($remaining, $saleItem->unit_name));
            }

            return [
                'sale_item_id' => $saleItem->id,
                'product_id' => $saleItem->product_id,
                'product_unit_id' => $saleItem->product_unit_id,
                'quantity' => (string) $line['quantity'],
                'base_quantity' => (string) $line['quantity']->multipliedBy($saleItem->conversion_snapshot)->toScale(3, RoundingMode::HalfUp),
                'unit_price' => $saleItem->unit_price,
                'cost_total' => (string) BigDecimal::of($saleItem->cost_total)->multipliedBy($line['quantity'])->dividedBy($saleItem->quantity, 2, RoundingMode::HalfUp),
                'refund_subtotal' => (string) $line['quantity']->multipliedBy($saleItem->unit_price)->toScale(2, RoundingMode::HalfUp),
                'disposition' => $disposition,
                'destination_warehouse_id' => $this->warehouseFor($disposition, $sale),
            ];
        })->values();
    }

    /**
     * Goods to be sold again go back where the receipt took them from; dead stock goes to the dead-stock warehouse.
     *
     * @throws SaleReturnException
     */
    private function warehouseFor(ReturnDisposition $disposition, Sale $sale): int
    {
        if ($disposition === ReturnDisposition::Resellable) {
            return $sale->warehouse_id;
        }

        return Warehouse::query()->where('type', $disposition->warehouseType())->where('is_active', true)->value('id')
            ?? throw SaleReturnException::noWarehouseFor($disposition->label());
    }

    /**
     * @param  Collection<int|string, mixed>  $returnedQuantityBySaleItemId
     */
    private function remainingQuantity(SaleItem $saleItem, Collection $returnedQuantityBySaleItemId): BigDecimal
    {
        return BigDecimal::of($saleItem->quantity)->minus((string) ($returnedQuantityBySaleItemId[$saleItem->id] ?? 0))->toScale(3);
    }

    /**
     * @param  list<SaleReturnStatus>  $statuses
     * @return Collection<int|string, mixed> quantity by receipt line id
     */
    private function returnedQuantities(Sale $sale, array $statuses): Collection
    {
        return SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->where('sale_returns.sale_id', $sale->id)
            ->whereIn('sale_returns.status', array_map(fn (SaleReturnStatus $status): string => $status->value, $statuses))
            ->groupBy('sale_return_items.sale_item_id')
            ->selectRaw('sale_return_items.sale_item_id as sale_item_id, sum(sale_return_items.quantity) as quantity')
            ->pluck('quantity', 'sale_item_id');
    }

    /**
     * `full` once every line is back, `partial` while some is, counting only returns that were approved. The caller
     * holds the sale's row lock, so a concurrent approval cannot be missed.
     */
    private function refreshReturnStatus(Sale $sale): void
    {
        $returnedQuantityBySaleItemId = $this->returnedQuantities($sale, [SaleReturnStatus::Completed]);
        $saleItems = $sale->items()->get();

        $isEverythingBack = $saleItems->every(fn (SaleItem $saleItem): bool => $this->remainingQuantity($saleItem, $returnedQuantityBySaleItemId)->isLessThanOrEqualTo(0));
        $isAnythingBack = $returnedQuantityBySaleItemId->isNotEmpty();

        $sale->forceFill(['return_status' => match (true) {
            $isEverythingBack => SaleReturnProgress::Full,
            $isAnythingBack => SaleReturnProgress::Partial,
            default => SaleReturnProgress::None,
        }])->save();
    }

    private function quantityText(BigDecimal $quantity, string $unitCode): string
    {
        $text = rtrim(rtrim((string) $quantity->toScale(3), '0'), '.');

        return "{$text} {$unitCode}";
    }
}
