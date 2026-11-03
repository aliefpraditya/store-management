<?php

namespace App\Services;

use App\Enums\DeliveryNoteStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\CodSaleException;
use App\Models\CashShift;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Sale;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * COD sales: the goods go out on a delivery note and the customer pays when they arrive. A COD sale counts as
 * delivered once some of its goods reached the customer (a delivery note that ended Terkirim or Sebagian terkirim); a
 * note that came back whole, or one that is still on its way, does not.
 *
 * The COD money is not in the drawer, so none of this touches the cash of a shift.
 *
 * @phpstan-type CodTotals array{delivered: array{count: int, amount: string}, undelivered: array{count: int, amount: string}}
 */
class CodSaleService
{
    public function __construct(private readonly DeliveryNoteService $deliveryNotes) {}

    /**
     * Whether goods of the sale were delivered. Needs `deliveryNotes` loaded.
     */
    public function isDelivered(Sale $sale): bool
    {
        return $sale->deliveryNotes->contains(fn (DeliveryNote $note): bool => $note->status->hasDeliveredGoods());
    }

    /**
     * What the shift sold on COD, split by whether the goods have been delivered yet. Counted by receipt, and by the
     * COD part of each receipt: what was paid in advance is not COD.
     *
     * @return CodTotals
     */
    public function totalsOfShift(CashShift $shift): array
    {
        $codSales = Sale::query()
            ->with(['payments', 'deliveryNotes'])
            ->where('cash_shift_id', $shift->id)
            ->where('status', SaleStatus::Completed)
            ->whereHas('payments', fn (Builder $payments) => $payments->where('method', PaymentMethod::Cod))
            ->get();

        [$deliveredSales, $undeliveredSales] = $codSales->partition(fn (Sale $sale): bool => $this->isDelivered($sale));

        return [
            'delivered' => $this->totalOf($deliveredSales),
            'undelivered' => $this->totalOf($undeliveredSales),
        ];
    }

    /**
     * The COD sales still waiting on their delivery, newest first, from any day: the ones to follow up or to void
     * when the customer does not take the goods.
     *
     * @return Collection<int, Sale>
     */
    public function undeliveredSales(int $limit): Collection
    {
        return Sale::query()
            ->with(['payments', 'deliveryNotes'])
            ->where('status', SaleStatus::Completed)
            ->whereHas('payments', fn (Builder $payments) => $payments->where('method', PaymentMethod::Cod))
            ->whereDoesntHave('deliveryNotes', fn (Builder $notes) => $notes->whereIn('status', DeliveryNoteStatus::withDeliveredGoods()))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The customer took the goods: every delivery note still on its way is sent and finished as delivered in full.
     * Anything that came back is for the admin's delivery note page, where the quantities can be entered.
     *
     * @throws CodSaleException
     */
    public function markDelivered(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale): Sale {
            $lockedSale = Sale::query()->with(['payments', 'deliveryNotes.items'])->lockForUpdate()->findOrFail($sale->id);

            if ($lockedSale->status !== SaleStatus::Completed) {
                throw CodSaleException::notCompleted($lockedSale->status->label());
            }

            if (! $lockedSale->isCod()) {
                throw CodSaleException::notCod();
            }

            $notesOnTheWay = $lockedSale->deliveryNotes->filter(fn (DeliveryNote $note): bool => $note->status->isUnfinished());

            if ($notesOnTheWay->isEmpty()) {
                throw CodSaleException::nothingToDeliver();
            }

            foreach ($notesOnTheWay as $note) {
                if ($note->status->canBeShipped()) {
                    $this->deliveryNotes->ship($note);
                }

                $this->deliveryNotes->complete($note, $note->items->map(fn (DeliveryNoteItem $item): array => [
                    'item_id' => $item->id,
                    'quantity_delivered' => $item->quantity,
                    'quantity_returned' => 0,
                ])->all());
            }

            return $lockedSale->load('deliveryNotes');
        });
    }

    /**
     * @param  Collection<int, Sale>  $codSales
     * @return array{count: int, amount: string}
     */
    private function totalOf(Collection $codSales): array
    {
        return [
            'count' => $codSales->count(),
            'amount' => (string) $codSales->reduce(fn (BigDecimal $sum, Sale $sale): BigDecimal => $sum->plus($sale->codAmount()), BigDecimal::zero())->toScale(2),
        ];
    }
}
