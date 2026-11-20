<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\CodSettlementStatus;
use App\Enums\DeliveryNoteStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Exceptions\CodSaleException;
use App\Models\CashShift;
use App\Models\CodSettlement;
use App\Models\DailyClosing;
use App\Models\DeliveryNoteItem;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Receiving the money of a COD sale. Delivering the goods and receiving the money are two steps: the goods go out on a
 * delivery note, and the money is confirmed by someone who holds it, once, after the delivery.
 *
 * What is owed is the COD part minus what returns cut from it: a return of goods of an unpaid COD sale lowers the bill
 * instead of paying anything out ({@see offsetFor()}). What is received may differ from what is owed (a lost note, a
 * short payment); that takes a reason, and unless the one who received it may approve differences, it waits for
 * someone who may. The money counts in its shift at once either way, so the drawer stays true; what waiting blocks is
 * closing the day.
 *
 * @phpstan-type SettlementInput array{client_uuid: string, method: string, amount: numeric-string|float|int, reference?: string|null, notes?: string|null, difference_reason?: string|null}
 * @phpstan-type QueueRow array{sale: Sale, offset_amount: BigDecimal, amount_due: BigDecimal, has_unreturned_goods: bool}
 */
class CodSettlementService
{
    /** Returns whose goods are held back from the customer's bill: waiting ones reserve them, finished ones took them. */
    private const GOODS_HOLDING_STATUSES = [SaleReturnStatus::Pending, SaleReturnStatus::Completed];

    public function __construct(
        private readonly CodSaleService $codSales,
        private readonly ShiftService $shifts,
    ) {}

    /**
     * What the customer owes on delivery: the COD part minus what returns cut from it, never below zero. Needs
     * `payments` loaded. It does not look at whether the money was received, see {@see outstandingOf()}.
     */
    public function amountDueOf(Sale $sale): BigDecimal
    {
        $due = BigDecimal::of($sale->codAmount())->minus((string) $this->offsetsOf([$sale->id])->get($sale->id, '0'));

        return $due->isNegative() ? BigDecimal::zero() : $due;
    }

    /**
     * What is still to be received: what is owed, or nothing once the money was received. Needs `payments` loaded.
     */
    public function outstandingOf(Sale $sale): BigDecimal
    {
        if (! $sale->isCod() || $sale->codSettlement()->exists()) {
            return BigDecimal::zero();
        }

        return $this->amountDueOf($sale);
    }

    /**
     * How much of the money a return would pay out is instead cut from the COD bill that is not paid yet. Needs
     * `payments` loaded, and the sale locked so two returns cannot cut the same bill twice.
     */
    public function offsetFor(Sale $sale, BigDecimal $refund): BigDecimal
    {
        return BigDecimal::min($refund, $this->outstandingOf($sale));
    }

    /**
     * Records the money received for a delivered COD sale. A `client_uuid` that was already recorded returns that
     * settlement, so a double submit cannot receive the money twice.
     *
     * @param  SettlementInput  $input
     *
     * @throws CodSaleException
     */
    public function receive(Sale $sale, User $receiver, array $input): CodSettlement
    {
        try {
            return DB::transaction(fn (): CodSettlement => $this->receiveInTransaction($sale, $receiver, $input));
        } catch (UniqueConstraintViolationException) {
            // Two submits of the same sale got past the check; the other one won.
            throw CodSaleException::alreadySettled();
        }
    }

    /**
     * Approves a difference in what was received.
     *
     * @throws CodSaleException
     */
    public function approve(Sale $sale, User $approver): CodSettlement
    {
        return DB::transaction(function () use ($sale, $approver): CodSettlement {
            $settlement = CodSettlement::query()->where('sale_id', $sale->id)->lockForUpdate()->first();

            if ($settlement === null) {
                throw CodSaleException::noSettlementToApprove();
            }

            if ($settlement->status === CodSettlementStatus::Confirmed) {
                throw CodSaleException::alreadyApproved();
            }

            $this->markApproved($settlement, $approver)->save();

            return $settlement;
        });
    }

    /**
     * The COD sales whose money is yet to be received or yet to be approved, newest first, from any day. A sale shows up
     * once its goods were delivered.
     *
     * @return list<QueueRow>
     */
    public function settlementQueue(int $limit): array
    {
        $sales = $this->settlementQueueQuery()->with(['payments', 'codSettlement', 'deliveryNotes'])->orderByDesc('id')->limit($limit)->get();

        $offsets = $this->offsetsOf($sales->modelKeys());
        $saleIdsWithUnreturnedGoods = $this->saleIdsWithUnreturnedGoods($sales->modelKeys());

        return $sales->map(function (Sale $sale) use ($offsets, $saleIdsWithUnreturnedGoods): array {
            $offset = BigDecimal::of((string) $offsets->get($sale->id, '0'));
            $due = BigDecimal::of($sale->codAmount())->minus($offset);

            return [
                'sale' => $sale,
                'offset_amount' => $offset,
                'amount_due' => $sale->codSettlement !== null ? BigDecimal::of((string) $sale->codSettlement->expected_amount) : ($due->isNegative() ? BigDecimal::zero() : $due),
                'has_unreturned_goods' => in_array($sale->id, $saleIdsWithUnreturnedGoods, true),
            ];
        })->all();
    }

    public function settlementQueueCount(): int
    {
        return $this->settlementQueueQuery()->count();
    }

    /**
     * How many received amounts wait for an approval of their difference.
     */
    public function awaitingApprovalCount(): int
    {
        return CodSettlement::query()->where('status', CodSettlementStatus::AwaitingApproval)->count();
    }

    /**
     * @param  SettlementInput  $input
     *
     * @throws CodSaleException
     */
    private function receiveInTransaction(Sale $sale, User $receiver, array $input): CodSettlement
    {
        DailyClosing::lockAgainstClosing();

        $lockedSale = Sale::query()->with('payments')->lockForUpdate()->findOrFail($sale->id);

        $alreadyRecorded = CodSettlement::query()->where('client_uuid', $input['client_uuid'])->first();

        if ($alreadyRecorded !== null) {
            return $this->settlementOfResubmit($alreadyRecorded, $lockedSale, $receiver);
        }

        $expected = $this->requireReceivable($lockedSale);
        $method = PaymentMethod::tryFrom($input['method']);

        if ($method === null || ! in_array($method, PaymentMethod::forCashier(), true)) {
            throw CodSaleException::methodNotAccepted();
        }

        $amount = BigDecimal::of((string) $input['amount']);
        $isDifferent = ! $amount->isEqualTo($expected);
        $differenceReason = filled($input['difference_reason'] ?? null) ? trim($input['difference_reason']) : null;

        if ($isDifferent && $differenceReason === null) {
            throw CodSaleException::differenceNeedsReason();
        }

        $shift = $this->shiftToReceiveIn($receiver, $method);
        $isApprovedAtOnce = ! $isDifferent || $this->mayApprove($receiver);

        $settlement = CodSettlement::query()->create([
            'client_uuid' => $input['client_uuid'],
            'sale_id' => $lockedSale->id,
            'cash_shift_id' => $shift?->id,
            'business_date' => $shift?->business_date ?? today(),
            'received_at' => now(),
            'method' => $method,
            'amount' => (string) $amount->toScale(2),
            'expected_amount' => (string) $expected->toScale(2),
            'difference_reason' => $isDifferent ? $differenceReason : null,
            'reference' => filled($input['reference'] ?? null) ? trim($input['reference']) : null,
            'notes' => filled($input['notes'] ?? null) ? trim($input['notes']) : null,
            'status' => $isApprovedAtOnce ? CodSettlementStatus::Confirmed : CodSettlementStatus::AwaitingApproval,
            'received_by' => $receiver->id,
            'received_by_name' => $receiver->name,
        ]);

        if ($isDifferent && $isApprovedAtOnce) {
            $this->markApproved($settlement, $receiver)->save();
        }

        return $settlement;
    }

    /**
     * Whether the money of this sale can be received now, and how much is owed. Everything is read under the sale lock.
     *
     * @throws CodSaleException
     */
    private function requireReceivable(Sale $lockedSale): BigDecimal
    {
        if (DailyClosing::isClosedOn(today())) {
            throw CodSaleException::settlementDayClosed(today()->toDateString());
        }

        if ($lockedSale->status === SaleStatus::Voided) {
            throw CodSaleException::alreadyVoided();
        }

        if ($lockedSale->status !== SaleStatus::Completed) {
            throw CodSaleException::notCompleted($lockedSale->status->label());
        }

        if (! $lockedSale->isCod()) {
            throw CodSaleException::notCod();
        }

        if ($lockedSale->codSettlement()->exists()) {
            throw CodSaleException::alreadySettled();
        }

        if (! $this->codSales->isDelivered($lockedSale->load('deliveryNotes'))) {
            throw CodSaleException::notDeliveredYet();
        }

        if ($this->hasPendingReturn($lockedSale)) {
            throw CodSaleException::returnPending();
        }

        $due = $this->amountDueOf($lockedSale);

        if ($due->isZero()) {
            throw CodSaleException::nothingToReceive();
        }

        return $due;
    }

    /**
     * Cash lands in a drawer, so it needs the receiver's running shift. Other methods go to the account, and are tied to a
     * shift only when the receiver has one running, so the shift's report shows what it took in.
     *
     * @throws CodSaleException
     */
    private function shiftToReceiveIn(User $receiver, PaymentMethod $method): ?CashShift
    {
        $openShift = $this->shifts->openShiftOf($receiver);
        $runningShift = $openShift === null ? null : CashShift::query()->lockForUpdate()->findOrFail($openShift->id);

        if ($runningShift?->status !== CashShiftStatus::Open) {
            $runningShift = null;
        }

        if ($runningShift === null && $method === PaymentMethod::Cash) {
            throw CodSaleException::shiftNeededForCash();
        }

        return $runningShift;
    }

    private function mayApprove(User $user): bool
    {
        /** @var Store $store */
        $store = tenant();

        return $user->hasPermissionIn($store, Permission::CodApprove);
    }

    private function markApproved(CodSettlement $settlement, User $approver): CodSettlement
    {
        return $settlement->forceFill([
            'status' => CodSettlementStatus::Confirmed,
            'approved_by' => $approver->id,
            'approved_by_name' => $approver->name,
            'approved_at' => now(),
        ]);
    }

    /**
     * A `client_uuid` that belongs to someone else's settlement, or to another sale, is refused, so a settlement cannot
     * be read by sending its uuid.
     *
     * @throws CodSaleException
     */
    private function settlementOfResubmit(CodSettlement $alreadyRecorded, Sale $sale, User $receiver): CodSettlement
    {
        if ($alreadyRecorded->received_by !== $receiver->id || $alreadyRecorded->sale_id !== $sale->id) {
            throw CodSaleException::settlementIdInUse();
        }

        return $alreadyRecorded;
    }

    private function hasPendingReturn(Sale $sale): bool
    {
        return SaleReturn::query()->where('sale_id', $sale->id)->where('status', SaleReturnStatus::Pending)->exists();
    }

    /**
     * @return Builder<Sale>
     */
    private function settlementQueueQuery(): Builder
    {
        $holdingStatuses = array_map(fn (SaleReturnStatus $status): string => $status->value, self::GOODS_HOLDING_STATUSES);
        $statusPlaceholders = implode(', ', array_fill(0, count($holdingStatuses), '?'));

        return Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereHas('payments', fn (Builder $payments) => $payments->where('method', PaymentMethod::Cod))
            ->whereHas('deliveryNotes', fn (Builder $notes) => $notes->whereIn('delivery_notes.status', DeliveryNoteStatus::withDeliveredGoods()))
            ->where(fn (Builder $waiting) => $waiting
                ->where(fn (Builder $unsettled) => $unsettled
                    ->whereDoesntHave('codSettlement')
                    ->whereRaw(
                        "(select coalesce(sum(sale_payments.amount), 0) from sale_payments where sale_payments.sale_id = sales.id and sale_payments.method = ?)
                        - (select coalesce(sum(sale_returns.cod_offset_amount), 0) from sale_returns where sale_returns.sale_id = sales.id and sale_returns.status in ({$statusPlaceholders})) > 0",
                        [PaymentMethod::Cod->value, ...$holdingStatuses],
                    ))
                ->orWhereHas('codSettlement', fn (Builder $settlement) => $settlement->where('status', CodSettlementStatus::AwaitingApproval)));
    }

    /**
     * What returns cut from the COD bill, per sale.
     *
     * @param  list<int>  $saleIds
     * @return Collection<int, string>
     */
    private function offsetsOf(array $saleIds): Collection
    {
        return SaleReturn::query()
            ->whereIn('sale_id', $saleIds)
            ->whereIn('status', self::GOODS_HOLDING_STATUSES)
            ->groupBy('sale_id')
            ->selectRaw('sale_id, sum(cod_offset_amount) as offset_amount')
            ->pluck('offset_amount', 'sale_id');
    }

    /**
     * The sales whose delivery notes say goods came back that no return has taken yet, so the customer's bill still
     * counts goods the shop already has.
     *
     * @param  list<int>  $saleIds
     * @return list<int>
     */
    private function saleIdsWithUnreturnedGoods(array $saleIds): array
    {
        $returnedOnNotes = DeliveryNoteItem::query()
            ->join('sale_items', 'sale_items.id', '=', 'delivery_note_items.sale_item_id')
            ->whereIn('sale_items.sale_id', $saleIds)
            ->where('delivery_note_items.quantity_returned', '>', 0)
            ->groupBy('sale_items.sale_id', 'delivery_note_items.sale_item_id')
            ->selectRaw('sale_items.sale_id as sale_id, delivery_note_items.sale_item_id as sale_item_id, sum(delivery_note_items.quantity_returned) as quantity')
            ->get();

        if ($returnedOnNotes->isEmpty()) {
            return [];
        }

        $returnedByReturns = SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->whereIn('sale_returns.sale_id', $saleIds)
            ->whereIn('sale_returns.status', self::GOODS_HOLDING_STATUSES)
            ->groupBy('sale_return_items.sale_item_id')
            ->selectRaw('sale_return_items.sale_item_id as sale_item_id, sum(sale_return_items.quantity) as quantity')
            ->pluck('quantity', 'sale_item_id');

        return $returnedOnNotes
            ->filter(fn ($row): bool => BigDecimal::of((string) $row->quantity)->isGreaterThan((string) $returnedByReturns->get($row->sale_item_id, '0')))
            ->pluck('sale_id')
            ->unique()
            ->values()
            ->all();
    }
}
