<?php

namespace App\Services;

use App\Enums\DeliveryNoteStatus;
use App\Enums\SaleStatus;
use App\Exceptions\DeliveryNoteException;
use App\Models\CustomerOrder;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Delivery notes for receipts whose goods are delivered to the customer. A note never touches the stock, which left
 * when the receipt was made. One receipt can have several notes (delivery in parts) as long as together they do not
 * carry more than a receipt line holds; cancelled notes do not count.
 *
 * `client_uuid` makes a double submit return the first note instead of making a second.
 *
 * @phpstan-type NoteInput array{client_uuid: string, recipient_name: string, recipient_phone?: string|null, recipient_address?: string|null, driver_name?: string|null, vehicle_number?: string|null, notes?: string|null, items: list<array{sale_item_id: int, quantity: numeric-string|float|int}>}
 * @phpstan-type DeliveryOutcome array{item_id: int, quantity_delivered: numeric-string|float|int, quantity_returned: numeric-string|float|int}
 */
class DeliveryNoteService
{
    private const NUMBER_TYPE = 'SJ';

    public function __construct(private readonly DocumentNumberGenerator $documentNumbers) {}

    /**
     * The notes list, newest first, narrowed by status, a search on the note number, recipient or receipt number,
     * and the day the note was made.
     *
     * @return LengthAwarePaginator<int, DeliveryNote>
     */
    public function paginateNotes(string $statusValue, string $search, ?CarbonInterface $from, ?CarbonInterface $to, int $perPage): LengthAwarePaginator
    {
        return DeliveryNote::query()
            ->with('sale')
            ->withCount('items')
            ->when(DeliveryNoteStatus::tryFrom($statusValue), fn (Builder $query, DeliveryNoteStatus $status) => $query->where('status', $status))
            ->when($from, fn (Builder $query, CarbonInterface $from) => $query->where('business_date', '>=', $from->toDateString()))
            ->when($to, fn (Builder $query, CarbonInterface $to) => $query->where('business_date', '<=', $to->toDateString()))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('recipient_name', 'like', "%{$search}%")
                ->orWhereHas('sale', fn (Builder $sale) => $sale->where('number', 'like', "%{$search}%"))))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, DeliveryNote>
     */
    public function notesOfSale(Sale $sale): Collection
    {
        return DeliveryNote::query()->with('items')->whereRelation('sales', 'sales.id', $sale->id)->orderBy('id')->get();
    }

    /**
     * Completed receipts that still have goods left to put on a note, found by receipt number or customer name,
     * newest first. The "something left" test runs in the query, so receipts that are used up cannot crowd out the
     * ones that are not.
     *
     * @return Collection<int, Sale>
     */
    public function deliverableSales(string $term, int $limit): Collection
    {
        return Sale::query()
            ->with('items')
            ->where('status', SaleStatus::Completed)
            ->whereRaw(
                'exists (select 1 from sale_items where sale_items.sale_id = sales.id and sale_items.quantity > coalesce((
                    select sum(delivery_note_items.quantity) from delivery_note_items
                    join delivery_notes on delivery_notes.id = delivery_note_items.delivery_note_id
                    where delivery_note_items.sale_item_id = sale_items.id and delivery_notes.status != ?
                ), 0))',
                [DeliveryNoteStatus::Cancelled->value],
            )
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")))
            ->orderByDesc('sold_at')
            ->limit($limit)
            ->get();
    }

    /**
     * A `client_uuid` that was already recorded returns that note, so a retried request cannot make a second one.
     *
     * @param  NoteInput  $noteInput
     *
     * @throws DeliveryNoteException
     */
    public function create(Sale $sale, User $creator, array $noteInput): DeliveryNote
    {
        return DB::transaction(function () use ($sale, $creator, $noteInput): DeliveryNote {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            // Checked under the sale lock so two submits of the same note cannot both get past it.
            $alreadyRecordedNote = DeliveryNote::query()->where('client_uuid', $noteInput['client_uuid'])->first();

            if ($alreadyRecordedNote !== null) {
                return $this->noteOfResubmit($alreadyRecordedNote, $sale, $creator);
            }

            if ($sale->status !== SaleStatus::Completed) {
                throw DeliveryNoteException::saleNotCompleted();
            }

            $quantities = $this->requestedQuantities($noteInput['items']);

            if ($quantities === []) {
                throw DeliveryNoteException::noItems();
            }

            $linkedSale = $this->linkedSaleOf($sale, isLocked: true);
            $saleItems = SaleItem::query()
                ->whereIn('sale_id', array_filter([$sale->id, $linkedSale?->id]))
                ->whereIn('id', array_keys($quantities))
                ->get()
                ->keyBy('id');
            $remainingBySaleItemId = $this->remainingQuantities($saleItems);

            foreach ($quantities as $saleItemId => $quantity) {
                $saleItem = $saleItems->get($saleItemId) ?? throw DeliveryNoteException::lineNotOnSale();
                $remaining = $remainingBySaleItemId[$saleItemId];

                if ($quantity->isGreaterThan($remaining)) {
                    throw DeliveryNoteException::exceedsRemaining($saleItem->product_name, $this->quantityText($remaining, $saleItem->unit_name));
                }
            }

            $note = DeliveryNote::query()->create([
                'client_uuid' => $noteInput['client_uuid'],
                'number' => $this->documentNumbers->next(self::NUMBER_TYPE, now()),
                'sale_id' => $sale->id,
                'business_date' => today(),
                'status' => DeliveryNoteStatus::Draft,
                'recipient_name' => trim($noteInput['recipient_name']),
                'recipient_phone' => $this->nullIfBlank($noteInput['recipient_phone'] ?? null),
                'recipient_address' => $this->nullIfBlank($noteInput['recipient_address'] ?? null),
                'driver_name' => $this->nullIfBlank($noteInput['driver_name'] ?? null),
                'vehicle_number' => $this->nullIfBlank($noteInput['vehicle_number'] ?? null),
                'notes' => $this->nullIfBlank($noteInput['notes'] ?? null),
                'created_by' => $creator->id,
                'created_by_name' => $creator->name,
            ]);

            $note->sales()->syncWithoutDetaching($saleItems->pluck('sale_id')->unique()->all());

            foreach ($quantities as $saleItemId => $quantity) {
                $saleItem = $saleItems[$saleItemId];

                $note->items()->create([
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'product_unit_id' => $saleItem->product_unit_id,
                    'product_name' => $saleItem->product_name,
                    'unit_name' => $saleItem->unit_name,
                    'quantity' => (string) $quantity,
                ]);
            }

            return $note->load('items');
        });
    }

    /**
     * The receipt lines of a sale with how much of each is still not on an active delivery note, then the same for the
     * receipt it is linked to (see {@see self::linkedSaleOf()}), so one note can take both.
     *
     * @return list<array{id: int, name: string, unit: string, quantity: string, remaining: string, sale_number: string}>
     */
    public function deliverableLines(Sale $sale): array
    {
        $linkedSale = $this->linkedSaleOf($sale);
        $sales = array_filter([$sale->loadMissing('items'), $linkedSale?->loadMissing('items')]);
        $remainingBySaleItemId = $this->remainingQuantities(collect($sales)->flatMap(fn (Sale $deliverableSale) => $deliverableSale->items));

        return collect($sales)
            ->flatMap(fn (Sale $deliverableSale) => $deliverableSale->items->map(fn (SaleItem $saleItem): array => [
                'id' => $saleItem->id,
                'name' => $saleItem->product_name,
                'unit' => $saleItem->unit_name,
                'quantity' => $saleItem->quantity,
                'remaining' => (string) $remainingBySaleItemId[$saleItem->id],
                'sale_number' => $deliverableSale->number,
            ]))
            ->values()
            ->all();
    }

    /**
     * The receipt that was made together with a customer order this receipt collected, when it is still standing. Its
     * goods can go out on the same delivery note, so a customer who ordered part of a cart is delivered once, not twice.
     */
    public function linkedSaleOf(Sale $sale, bool $isLocked = false): ?Sale
    {
        $originSaleId = CustomerOrder::query()->where('sale_id', $sale->id)->value('origin_sale_id');

        if ($originSaleId === null) {
            return null;
        }

        return Sale::query()
            ->where('status', SaleStatus::Completed)
            ->when($isLocked, fn (Builder $query) => $query->lockForUpdate())
            ->find($originSaleId);
    }

    /**
     * The notes of a receipt with their items, locked until the transaction ends, so an admin finishing or cancelling
     * one of them at the same moment waits instead of changing it under a void or a delivery mark of the till. In note
     * order, like every other lock on notes. Call it inside a transaction.
     *
     * @return Collection<int, DeliveryNote>
     */
    public function lockedNotesOf(Sale $sale): Collection
    {
        return DeliveryNote::query()->with('items')->whereRelation('sales', 'sales.id', $sale->id)->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * Marks the goods as on their way. Who drives and in what vehicle is only known now, so it is given here; a driver or
     * vehicle already on the note stays unless a new one is given.
     *
     * @throws DeliveryNoteException
     */
    public function ship(DeliveryNote $note, ?string $driverName = null, ?string $vehicleNumber = null): void
    {
        DB::transaction(function () use ($note, $driverName, $vehicleNumber): void {
            $note = $this->lockedWhere($note, 'dikirim', fn (DeliveryNoteStatus $status): bool => $status->canBeShipped());

            $note->forceFill([
                'status' => DeliveryNoteStatus::Shipped,
                'shipped_at' => now(),
                'driver_name' => $this->nullIfBlank($driverName) ?? $note->driver_name,
                'vehicle_number' => $this->nullIfBlank($vehicleNumber) ?? $note->vehicle_number,
            ])->save();
        });
    }

    /**
     * Ends the delivery with what arrived and what came back for every item; the two must add up to what was sent.
     * All delivered, all returned, or a mix gives Terkirim, Kembali or Sebagian terkirim. Every item of the note needs
     * exactly one outcome, and an outcome for an item that is not on the note is refused.
     *
     * @param  list<DeliveryOutcome>  $deliveryOutcomes
     *
     * @throws DeliveryNoteException
     */
    public function complete(DeliveryNote $note, array $deliveryOutcomes): void
    {
        DB::transaction(function () use ($note, $deliveryOutcomes): void {
            $note = $this->lockedWhere($note, 'diselesaikan', fn (DeliveryNoteStatus $status): bool => $status->canBeCompleted());
            $noteItems = $note->items()->get();

            $outcomeByItemId = $this->outcomesByItemId($deliveryOutcomes, $noteItems->pluck('id')->all());

            foreach ($noteItems as $noteItem) {
                $outcome = $outcomeByItemId[$noteItem->id] ?? throw DeliveryNoteException::resultMissing();
                $delivered = BigDecimal::of((string) $outcome['quantity_delivered']);
                $returned = BigDecimal::of((string) $outcome['quantity_returned']);

                if (! $delivered->plus($returned)->isEqualTo($noteItem->quantity)) {
                    throw DeliveryNoteException::resultDoesNotAddUp($noteItem->product_name);
                }

                $noteItem->forceFill(['quantity_delivered' => (string) $delivered, 'quantity_returned' => (string) $returned])->save();
            }

            $note->forceFill(['status' => $this->finalStatus($noteItems->fresh()), 'completed_at' => now()])->save();
        });
    }

    /**
     * @throws DeliveryNoteException
     */
    public function cancel(DeliveryNote $note): void
    {
        DB::transaction(function () use ($note): void {
            $note = $this->lockedWhere($note, 'dibatalkan', fn (DeliveryNoteStatus $status): bool => $status->canBeCancelled());

            $note->forceFill(['status' => DeliveryNoteStatus::Cancelled])->save();
        });
    }

    /**
     * A `client_uuid` that belongs to someone else's note, or to another receipt, is refused, so a note cannot be
     * read by sending its uuid.
     *
     * @throws DeliveryNoteException
     */
    private function noteOfResubmit(DeliveryNote $alreadyRecordedNote, Sale $sale, User $creator): DeliveryNote
    {
        if ($alreadyRecordedNote->created_by !== $creator->id || $alreadyRecordedNote->sale_id !== $sale->id) {
            throw DeliveryNoteException::noteIdInUse();
        }

        return $alreadyRecordedNote->load('items');
    }

    /**
     * @param  list<DeliveryOutcome>  $deliveryOutcomes
     * @param  list<int>  $noteItemIds
     * @return array<int, DeliveryOutcome>
     *
     * @throws DeliveryNoteException
     */
    private function outcomesByItemId(array $deliveryOutcomes, array $noteItemIds): array
    {
        $outcomeItemIds = array_map(fn (array $outcome): int => (int) $outcome['item_id'], $deliveryOutcomes);

        $isEveryItemOnTheNoteOnce = count($outcomeItemIds) === count(array_unique($outcomeItemIds))
            && array_diff($outcomeItemIds, $noteItemIds) === [];

        if (! $isEveryItemOnTheNoteOnce) {
            throw DeliveryNoteException::resultNotOnNote();
        }

        return array_combine($outcomeItemIds, $deliveryOutcomes) ?: [];
    }

    /**
     * @param  Collection<int, DeliveryNoteItem>  $noteItems
     */
    private function finalStatus(Collection $noteItems): DeliveryNoteStatus
    {
        $deliveredNothing = $noteItems->every(fn (DeliveryNoteItem $noteItem): bool => BigDecimal::of($noteItem->quantity_delivered)->isZero());
        $returnedNothing = $noteItems->every(fn (DeliveryNoteItem $noteItem): bool => BigDecimal::of($noteItem->quantity_returned)->isZero());

        return match (true) {
            $returnedNothing => DeliveryNoteStatus::Delivered,
            $deliveredNothing => DeliveryNoteStatus::Returned,
            default => DeliveryNoteStatus::PartiallyDelivered,
        };
    }

    /**
     * What is left of each receipt line after what is on active (not cancelled) notes, in one query for all the lines.
     *
     * @param  Collection<int, SaleItem>  $saleItems
     * @return array<int, BigDecimal> remaining quantity by receipt line id
     */
    private function remainingQuantities(Collection $saleItems): array
    {
        $quantityOnActiveNotesBySaleItemId = DeliveryNoteItem::query()
            ->join('delivery_notes', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
            ->whereIn('delivery_note_items.sale_item_id', $saleItems->pluck('id'))
            ->where('delivery_notes.status', '!=', DeliveryNoteStatus::Cancelled->value)
            ->groupBy('delivery_note_items.sale_item_id')
            ->selectRaw('delivery_note_items.sale_item_id as sale_item_id, sum(delivery_note_items.quantity) as quantity')
            ->pluck('quantity', 'sale_item_id');

        return $saleItems
            ->mapWithKeys(fn (SaleItem $saleItem): array => [
                $saleItem->id => BigDecimal::of($saleItem->quantity)->minus((string) ($quantityOnActiveNotesBySaleItemId[$saleItem->id] ?? 0)),
            ])
            ->all();
    }

    /**
     * The quantity per receipt line, with lines of zero dropped and a line sent twice added up.
     *
     * @param  list<array{sale_item_id: int, quantity: numeric-string|float|int}>  $lines
     * @return array<int, BigDecimal>
     */
    private function requestedQuantities(array $lines): array
    {
        $quantities = [];

        foreach ($lines as $line) {
            $quantity = BigDecimal::of((string) $line['quantity']);

            if ($quantity->isPositive()) {
                $quantities[(int) $line['sale_item_id']] = ($quantities[(int) $line['sale_item_id']] ?? BigDecimal::zero())->plus($quantity);
            }
        }

        return $quantities;
    }

    /**
     * Re-reads the note under a row lock and checks its status, so two requests cannot both move it on.
     *
     * @param  Closure(DeliveryNoteStatus): bool  $isStatusAllowed
     *
     * @throws DeliveryNoteException
     */
    private function lockedWhere(DeliveryNote $note, string $attemptedAction, Closure $isStatusAllowed): DeliveryNote
    {
        $lockedNote = DeliveryNote::query()->lockForUpdate()->findOrFail($note->id);

        if (! $isStatusAllowed($lockedNote->status)) {
            throw DeliveryNoteException::wrongStatus($attemptedAction, $lockedNote->status->label());
        }

        return $lockedNote;
    }

    private function nullIfBlank(?string $value): ?string
    {
        return filled($value) ? trim($value) : null;
    }

    private function quantityText(BigDecimal $quantity, string $unitCode): string
    {
        return rtrim(rtrim((string) $quantity->toScale(3), '0'), '.').' '.$unitCode;
    }
}
