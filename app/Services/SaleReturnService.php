<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReturnDisposition;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
 * @phpstan-type ReturnLine array{sale_item_id: int, quantity: numeric-string|float|int, disposition: string}
 * @phpstan-type ExchangeLine array{product_unit_id: int, quantity: numeric-string|float|int}
 * @phpstan-type ExchangePayment array{method: string, amount: numeric-string|float|int, reference?: string|null}
 * @phpstan-type ReturnData array{resolution?: string, refund_method: string, refund_account?: string|null, reason?: string|null, items: list<ReturnLine>, exchange_items?: list<ExchangeLine>, exchange_payments?: list<ExchangePayment>}
 */
class SaleReturnService
{
    private const NUMBER_TYPE = 'RET';

    /** Returns that hold goods: waiting ones reserve them, finished ones took them. */
    private const GOODS_HOLDING_STATUSES = [SaleReturnStatus::Pending, SaleReturnStatus::Completed];

    public function __construct(
        private readonly StockService $stockLedger,
        private readonly DocumentNumberGenerator $numbers,
        private readonly SaleService $sales,
    ) {}

    /**
     * The receipt lines with how much of each can still be returned.
     *
     * @return list<array{id: int, name: string, unit: string, quantity: string, returned: string, remaining: string, unit_price: string}>
     */
    public function returnableLines(Sale $sale): array
    {
        $returned = $this->returnedQuantities($sale, self::GOODS_HOLDING_STATUSES);

        return $sale->items->map(fn (SaleItem $saleItem): array => [
            'id' => $saleItem->id,
            'name' => $saleItem->product_name,
            'unit' => $saleItem->unit_name,
            'quantity' => $saleItem->quantity,
            'returned' => (string) BigDecimal::of((string) ($returned[$saleItem->id] ?? 0))->toScale(3),
            'remaining' => (string) $this->remainingQuantity($saleItem, $returned),
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
     * Records a return that waits for an admin's decision.
     *
     * @param  ReturnData  $data
     *
     * @throws SaleReturnException
     */
    public function create(Sale $sale, CashShift $shift, User $requester, array $data): SaleReturn
    {
        return DB::transaction(function () use ($sale, $shift, $requester, $data): SaleReturn {
            $sale = Sale::query()->with('items')->lockForUpdate()->findOrFail($sale->id);
            $shift = CashShift::query()->lockForUpdate()->findOrFail($shift->id);

            if ($sale->status !== SaleStatus::Completed) {
                throw SaleReturnException::saleNotCompleted();
            }

            if ($shift->status !== CashShiftStatus::Open) {
                throw SaleReturnException::shiftNotOpen();
            }

            $rows = $this->priceRows($sale, $data['items']);
            $total = $rows->reduce(fn (BigDecimal $sum, array $row): BigDecimal => $sum->plus($row['attributes']['refund_subtotal']), BigDecimal::zero());

            $isExchange = ($data['resolution'] ?? 'refund') === 'exchange';
            $exchange = $isExchange ? $this->priceExchange($data, $total) : null;
            $refund = $exchange['refund'] ?? $total;
            $refundMethod = PaymentMethod::from($data['refund_method']);
            $refundAccount = $this->refundAccountOf($refundMethod, $refund, $data['refund_account'] ?? null);

            $saleReturn = SaleReturn::query()->create([
                'number' => $this->numbers->next(self::NUMBER_TYPE, now()),
                'sale_id' => $sale->id,
                'register_id' => $shift->register_id,
                'cash_shift_id' => $shift->id,
                'returned_at' => now(),
                'resolution' => $isExchange ? 'exchange' : 'refund',
                'refund_method' => $refundMethod,
                'refund_account' => $refundAccount,
                'credit_amount' => (string) $total->toScale(2),
                'refund_amount' => (string) $refund->toScale(2),
                'exchange_data' => $exchange['data'] ?? null,
                'status' => SaleReturnStatus::Pending,
                'reason' => filled($data['reason'] ?? null) ? trim($data['reason']) : null,
                'requested_by' => $requester->id,
            ]);

            foreach ($rows as $row) {
                $saleReturn->items()->create($row['attributes']);
            }

            return $saleReturn->load('items.saleItem');
        });
    }

    /**
     * An admin's own return needs no one else's approval, so it is asked for and approved in one go.
     *
     * @param  ReturnData  $data
     *
     * @throws SaleReturnException
     */
    public function createApproved(Sale $sale, CashShift $shift, User $admin, array $data): SaleReturn
    {
        return DB::transaction(fn (): SaleReturn => $this->approve($this->create($sale, $shift, $admin, $data), $admin), StockService::DEADLOCK_ATTEMPTS);
    }

    /**
     * Takes the goods back into their warehouses; from now on the refund counts against the shift's drawer.
     *
     * @throws SaleReturnException
     */
    public function approve(SaleReturn $saleReturn, User $approver): SaleReturn
    {
        return DB::transaction(function () use ($saleReturn, $approver): SaleReturn {
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
                $this->sellReplacement($saleReturn);
            }

            $saleReturn->forceFill(['status' => SaleReturnStatus::Completed, 'approved_by' => $approver->id])->save();
            $this->refreshReturnStatus($saleReturn->sale);

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
     * Money paid back by transfer, QRIS or EDC needs the account it goes to. Cash, or nothing to pay back, needs none.
     *
     * @throws SaleReturnException
     */
    private function refundAccountOf(PaymentMethod $method, BigDecimal $refund, ?string $account): ?string
    {
        if ($method === PaymentMethod::Cash || $refund->isZero()) {
            return null;
        }

        return filled($account) ? trim($account) : throw SaleReturnException::refundAccountMissing();
    }

    /**
     * Prices the replacement goods now and settles them against the value of what comes back: the customer pays
     * what is more, or gets the rest back. Checked when the exchange is asked for, so it is refused on the spot
     * rather than when an admin looks at it.
     *
     * @param  ReturnData  $data
     * @return array{data: array<string, mixed>, refund: BigDecimal}
     *
     * @throws SaleReturnException
     */
    private function priceExchange(array $data, BigDecimal $credit): array
    {
        $extraPayments = $this->extraPaymentsOf($data['exchange_payments'] ?? []);

        try {
            $quote = $this->sales->quote($data['exchange_items']);
            $replacementTotal = BigDecimal::of($quote['total']);

            $this->sales->settle($replacementTotal, $this->exchangePayments($credit, $replacementTotal, $extraPayments));
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
    private function sellReplacement(SaleReturn $saleReturn): void
    {
        $exchange = $saleReturn->exchange_data;
        $credit = BigDecimal::of($saleReturn->credit_amount);
        $items = array_map(fn (array $line): array => ['product_unit_id' => $line['product_unit_id'], 'quantity' => $line['quantity']], $exchange['items']);

        try {
            $replacementTotal = BigDecimal::of($this->sales->quote($items)['total']);

            $sale = $this->sales->create(
                CashShift::query()->findOrFail($saleReturn->cash_shift_id),
                User::query()->findOrFail($saleReturn->requested_by),
                [
                    'client_uuid' => (string) Str::uuid(),
                    'customer_name' => $saleReturn->sale->customer_name,
                    'items' => $items,
                    'payments' => $this->exchangePayments($credit, $replacementTotal, $exchange['payments']),
                ],
            );
        } catch (SaleException $exception) {
            throw SaleReturnException::fromSale($exception);
        }

        $saleReturn->forceFill([
            'exchange_sale_id' => $sale->id,
            'refund_amount' => (string) $credit->minus($this->creditUsed($credit, $replacementTotal))->toScale(2),
        ]);
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
     * @return Collection<int, array{attributes: array<string, mixed>}>
     *
     * @throws SaleReturnException
     */
    private function priceRows(Sale $sale, array $lines): Collection
    {
        $requested = collect($lines)
            ->map(fn (array $line): array => [...$line, 'quantity' => BigDecimal::of((string) $line['quantity'])])
            ->reject(fn (array $line): bool => $line['quantity']->isZero());

        if ($requested->isEmpty()) {
            throw SaleReturnException::noItems();
        }

        $saleItems = $sale->items->keyBy('id');
        $returned = $this->returnedQuantities($sale, self::GOODS_HOLDING_STATUSES);

        return $requested->map(function (array $line) use ($saleItems, $returned, $sale): array {
            $saleItem = $saleItems->get($line['sale_item_id']) ?? throw SaleReturnException::lineNotOnSale();
            $remaining = $this->remainingQuantity($saleItem, $returned);
            $disposition = ReturnDisposition::from($line['disposition']);

            if ($line['quantity']->isGreaterThan($remaining)) {
                throw SaleReturnException::exceedsReturnable($saleItem->product_name, $this->quantityText($remaining, $saleItem->unit_name));
            }

            return ['attributes' => [
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
            ]];
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
     * @param  Collection<int|string, mixed>  $returned  quantity already returned by receipt line id
     */
    private function remainingQuantity(SaleItem $saleItem, Collection $returned): BigDecimal
    {
        return BigDecimal::of($saleItem->quantity)->minus((string) ($returned[$saleItem->id] ?? 0))->toScale(3);
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
     * `full` once every line is back, `partial` while some is, counting only returns that were approved.
     */
    private function refreshReturnStatus(Sale $sale): void
    {
        $returned = $this->returnedQuantities($sale, [SaleReturnStatus::Completed]);
        $items = $sale->items()->get();

        $isEverythingBack = $items->every(fn (SaleItem $saleItem): bool => $this->remainingQuantity($saleItem, $returned)->isLessThanOrEqualTo(0));
        $isAnythingBack = $returned->isNotEmpty();

        $sale->forceFill(['return_status' => $isEverythingBack ? 'full' : ($isAnythingBack ? 'partial' : 'none')])->save();
    }

    private function quantityText(BigDecimal $quantity, string $unitCode): string
    {
        $text = rtrim(rtrim((string) $quantity->toScale(3), '0'), '.');

        return "{$text} {$unitCode}";
    }
}
