<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReturnDisposition;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
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

/**
 * Takes goods back from a receipt and gives the money back. A return is asked for in the asker's running shift and
 * waits for an admin; only when it is approved does the stock come back (to the shelf or to dead stock) and the
 * refund count against the shift's drawer. Goods waiting for approval already count as returned, so the same
 * goods cannot be returned twice. The value follows the price on the receipt.
 *
 * Exchanging goods is not part of this yet: every return is a refund.
 *
 * @phpstan-type ReturnLine array{sale_item_id: int, quantity: numeric-string|float|int, disposition: string}
 * @phpstan-type ReturnData array{refund_method: string, reason?: string|null, items: list<ReturnLine>}
 */
class SaleReturnService
{
    private const NUMBER_TYPE = 'RET';

    /** Returns that hold goods: waiting ones reserve them, finished ones took them. */
    private const GOODS_HOLDING_STATUSES = [SaleReturnStatus::Pending, SaleReturnStatus::Completed];

    public function __construct(
        private readonly StockService $stockLedger,
        private readonly DocumentNumberGenerator $numbers,
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

            $saleReturn = SaleReturn::query()->create([
                'number' => $this->numbers->next(self::NUMBER_TYPE, now()),
                'sale_id' => $sale->id,
                'register_id' => $shift->register_id,
                'cash_shift_id' => $shift->id,
                'returned_at' => now(),
                'resolution' => 'refund',
                'refund_method' => PaymentMethod::from($data['refund_method']),
                'credit_amount' => (string) $total->toScale(2),
                'refund_amount' => (string) $total->toScale(2),
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
