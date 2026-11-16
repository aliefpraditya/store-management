<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\DeliveryNoteStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\CodSaleException;
use App\Models\CashShift;
use App\Models\DailyClosing;
use App\Models\DeliveryNote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Voiding a COD sale: the customer did not take the goods (or would not pay when they came), so the receipt is
 * cancelled and the goods go back on the shelf. Only COD sales can be voided, and only while none of the goods were
 * delivered; later returns go through Retur. A cashier or an admin may do it, always with a reason, and on any day:
 * the sale may have been made yesterday and delivered today. The void is booked on the business day of the voider's
 * running shift (the same day the cash it gives back is booked on, even when the shift runs past midnight), or on the
 * day it is done when the voider has no shift.
 *
 * A deposit paid in advance goes back to the customer. A cash deposit comes out of the drawer of the voider's running
 * shift (so it lowers the cash that should be there); a deposit by transfer, QRIS or EDC goes back to the account given.
 */
class SaleVoidService
{
    public function __construct(
        private readonly StockService $stockLedger,
        private readonly DeliveryNoteService $deliveryNotes,
        private readonly ShiftService $shifts,
    ) {}

    /**
     * @throws CodSaleException
     */
    public function void(Sale $sale, User $voider, string $reason, ?string $refundAccount): Sale
    {
        return DB::transaction(function () use ($sale, $voider, $reason, $refundAccount): Sale {
            DailyClosing::lockAgainstClosing();

            $lockedSale = Sale::query()->with(['items.productUnit', 'payments'])->lockForUpdate()->findOrFail($sale->id);
            $lockedSale->setRelation('deliveryNotes', $this->deliveryNotes->lockedNotesOf($lockedSale));

            $this->requireVoidable($lockedSale);

            $cashDeposit = $lockedSale->cashDeposit();
            $nonCashDeposit = $lockedSale->nonCashDeposit();

            $refundShift = $cashDeposit->isPositive() ? $this->runningShiftOf($voider) : null;
            $voidBusinessDate = ($refundShift ?? $this->shifts->openShiftOf($voider))?->business_date ?? today();

            if ($nonCashDeposit->isPositive() && blank($refundAccount)) {
                throw CodSaleException::refundAccountNeeded();
            }

            $this->putGoodsBack($lockedSale, $voider);

            foreach ($lockedSale->deliveryNotes->filter(fn (DeliveryNote $note): bool => $note->status->canBeCancelled()) as $note) {
                $this->deliveryNotes->cancel($note);
            }

            $lockedSale->forceFill([
                'status' => SaleStatus::Voided,
                'void_reason' => trim($reason),
                'voided_by' => $voider->id,
                'voided_by_name' => $voider->name,
                'voided_at' => now(),
                'void_business_date' => $voidBusinessDate->toDateString(),
                'void_cash_shift_id' => $refundShift?->id,
                'void_cash_refund' => (string) $cashDeposit->toScale(2),
                'void_refund_account' => $nonCashDeposit->isPositive() ? trim((string) $refundAccount) : null,
            ])->save();

            return $lockedSale;
        }, StockService::DEADLOCK_ATTEMPTS);
    }

    /**
     * Why the sale cannot be voided now, or null when it can. The one place this rule lives, for the button on the
     * receipt page and for the voiding itself. Needs `payments` and `deliveryNotes` loaded.
     */
    public function voidBlocker(Sale $sale): ?CodSaleException
    {
        return match (true) {
            $sale->status === SaleStatus::Voided => CodSaleException::alreadyVoided(),
            $sale->status !== SaleStatus::Completed => CodSaleException::notCompleted($sale->status->label()),
            ! $sale->isCod() => CodSaleException::notCod(),
            DailyClosing::isClosedOn(today()) => CodSaleException::dayClosed(today()->format('d/m/Y')),
            $sale->payments->contains('method', PaymentMethod::ReturnCredit) => CodSaleException::paidByReturnCredit(),
            $sale->payments->contains('method', PaymentMethod::OrderDeposit) => CodSaleException::paidByOrderDeposit(),
            $this->hasReturns($sale) => CodSaleException::hasReturns(),
            $sale->deliveryNotes->contains(fn (DeliveryNote $note): bool => $note->status->hasDeliveredGoods()) => CodSaleException::alreadyDelivered(),
            $sale->deliveryNotes->contains(fn (DeliveryNote $note): bool => $note->sale_id !== $sale->id && $note->status !== DeliveryNoteStatus::Cancelled) => CodSaleException::onCombinedDeliveryNote(),
            default => null,
        };
    }

    /**
     * @throws CodSaleException
     */
    private function requireVoidable(Sale $sale): void
    {
        $voidBlocker = $this->voidBlocker($sale);

        if ($voidBlocker !== null) {
            throw $voidBlocker;
        }
    }

    private function hasReturns(Sale $sale): bool
    {
        return SaleReturn::query()->where('sale_id', $sale->id)->whereIn('status', [SaleReturnStatus::Pending, SaleReturnStatus::Completed])->exists();
    }

    /**
     * The shift whose drawer the cash deposit comes out of. It is locked, so the shift cannot be closed while the
     * refund is being booked on it.
     *
     * @throws CodSaleException
     */
    private function runningShiftOf(User $voider): CashShift
    {
        $openShift = $this->shifts->openShiftOf($voider);

        if ($openShift === null) {
            throw CodSaleException::shiftNeededForRefund();
        }

        $lockedShift = CashShift::query()->lockForUpdate()->findOrFail($openShift->id);

        if ($lockedShift->status !== CashShiftStatus::Open) {
            throw CodSaleException::shiftNeededForRefund();
        }

        return $lockedShift;
    }

    /**
     * Every line goes back into the warehouse it was sold from, in product order like a sale takes it out. Sealed packs
     * sold whole come back as whole packs; a pack that was opened for a smaller unit stays opened, and its pieces come back loose.
     */
    private function putGoodsBack(Sale $sale, User $voider): void
    {
        foreach ($sale->items->sortBy('product_id') as $item) {
            /** @var SaleItem $item */
            $this->stockLedger->record($item->product_id, $sale->warehouse_id, StockMovementType::SaleVoid, '+'.$item->base_quantity, $sale, $voider->id, packChange: $item->wholePacksIn($item->quantity));
        }
    }
}
