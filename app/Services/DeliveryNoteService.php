<?php

namespace App\Services;

use App\Enums\DeliveryNoteStatus;
use App\Enums\SaleStatus;
use App\Exceptions\DeliveryNoteException;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Delivery notes for receipts whose goods are delivered to the customer. A note never touches the stock, which left
 * when the receipt was made. One receipt can have several notes (delivery in parts) as long as together they do not
 * carry more than a receipt line holds; cancelled notes do not count.
 *
 * @phpstan-type NoteData array{recipient_name: string, recipient_phone?: string|null, recipient_address?: string|null, driver_name?: string|null, vehicle_number?: string|null, notes?: string|null, items: list<array{sale_item_id: int, quantity: numeric-string|float|int}>}
 * @phpstan-type DeliveryResult array{item_id: int, quantity_delivered: numeric-string|float|int, quantity_returned: numeric-string|float|int}
 */
class DeliveryNoteService
{
    private const NUMBER_TYPE = 'SJ';

    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    /**
     * @param  NoteData  $data
     *
     * @throws DeliveryNoteException
     */
    public function create(Sale $sale, User $creator, array $data): DeliveryNote
    {
        return DB::transaction(function () use ($sale, $creator, $data): DeliveryNote {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($sale->status !== SaleStatus::Completed) {
                throw DeliveryNoteException::saleNotCompleted();
            }

            $quantities = $this->requestedQuantities($data['items']);

            if ($quantities === []) {
                throw DeliveryNoteException::noItems();
            }

            $saleItems = $sale->items()->whereIn('id', array_keys($quantities))->get()->keyBy('id');

            foreach ($quantities as $saleItemId => $quantity) {
                $saleItem = $saleItems->get($saleItemId) ?? throw DeliveryNoteException::lineNotOnSale();
                $remaining = $this->remainingQuantity($saleItem);

                if ($quantity->isGreaterThan($remaining)) {
                    throw DeliveryNoteException::exceedsRemaining($saleItem->product_name, $this->quantityText($remaining, $saleItem->unit_name));
                }
            }

            $note = DeliveryNote::query()->create([
                'number' => $this->numbers->next(self::NUMBER_TYPE, now()),
                'sale_id' => $sale->id,
                'business_date' => today(),
                'status' => DeliveryNoteStatus::Draft,
                'recipient_name' => trim($data['recipient_name']),
                'recipient_phone' => $this->nullIfBlank($data['recipient_phone'] ?? null),
                'recipient_address' => $this->nullIfBlank($data['recipient_address'] ?? null),
                'driver_name' => $this->nullIfBlank($data['driver_name'] ?? null),
                'vehicle_number' => $this->nullIfBlank($data['vehicle_number'] ?? null),
                'notes' => $this->nullIfBlank($data['notes'] ?? null),
                'created_by' => $creator->id,
                'created_by_name' => $creator->name,
            ]);

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
     * The receipt lines of a sale with how much of each is still not on an active delivery note.
     *
     * @return list<array{id: int, name: string, unit: string, quantity: string, remaining: string}>
     */
    public function deliverableLines(Sale $sale): array
    {
        return $sale->items->map(fn (SaleItem $saleItem): array => [
            'id' => $saleItem->id,
            'name' => $saleItem->product_name,
            'unit' => $saleItem->unit_name,
            'quantity' => $saleItem->quantity,
            'remaining' => (string) $this->remainingQuantity($saleItem),
        ])->values()->all();
    }

    /**
     * @throws DeliveryNoteException
     */
    public function ship(DeliveryNote $note): void
    {
        DB::transaction(function () use ($note): void {
            $note = $this->lockedInStatus($note, 'dikirim', DeliveryNoteStatus::Draft);

            $note->forceFill(['status' => DeliveryNoteStatus::Shipped, 'shipped_at' => now()])->save();
        });
    }

    /**
     * Ends the delivery with what arrived and what came back for every item; the two must add up to what was sent.
     * All delivered, all returned, or a mix gives Terkirim, Kembali or Sebagian terkirim.
     *
     * @param  list<DeliveryResult>  $results
     *
     * @throws DeliveryNoteException
     */
    public function complete(DeliveryNote $note, array $results): void
    {
        DB::transaction(function () use ($note, $results): void {
            $note = $this->lockedInStatus($note, 'diselesaikan', DeliveryNoteStatus::Shipped);
            $resultsByItem = collect($results)->keyBy('item_id');
            $items = $note->items()->get();

            foreach ($items as $item) {
                $result = $resultsByItem->get($item->id) ?? throw DeliveryNoteException::resultMissing();
                $delivered = BigDecimal::of((string) $result['quantity_delivered']);
                $returned = BigDecimal::of((string) $result['quantity_returned']);

                if (! $delivered->plus($returned)->isEqualTo($item->quantity)) {
                    throw DeliveryNoteException::resultDoesNotAddUp($item->product_name);
                }

                $item->forceFill(['quantity_delivered' => (string) $delivered, 'quantity_returned' => (string) $returned])->save();
            }

            $note->forceFill(['status' => $this->finalStatus($items->fresh()), 'completed_at' => now()])->save();
        });
    }

    /**
     * @throws DeliveryNoteException
     */
    public function cancel(DeliveryNote $note): void
    {
        DB::transaction(function () use ($note): void {
            $note = $this->lockedInStatus($note, 'dibatalkan', DeliveryNoteStatus::Draft, DeliveryNoteStatus::Shipped);

            $note->forceFill(['status' => DeliveryNoteStatus::Cancelled])->save();
        });
    }

    /**
     * @param  Collection<int, DeliveryNoteItem>  $items
     */
    private function finalStatus(Collection $items): DeliveryNoteStatus
    {
        $deliveredNothing = $items->every(fn (DeliveryNoteItem $item): bool => BigDecimal::of($item->quantity_delivered)->isZero());
        $returnedNothing = $items->every(fn (DeliveryNoteItem $item): bool => BigDecimal::of($item->quantity_returned)->isZero());

        return match (true) {
            $returnedNothing => DeliveryNoteStatus::Delivered,
            $deliveredNothing => DeliveryNoteStatus::Returned,
            default => DeliveryNoteStatus::PartiallyDelivered,
        };
    }

    /**
     * What is on active (not cancelled) notes for the line, taken from the sale item's quantity.
     */
    private function remainingQuantity(SaleItem $saleItem): BigDecimal
    {
        $onNotes = DeliveryNoteItem::query()
            ->join('delivery_notes', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
            ->where('delivery_note_items.sale_item_id', $saleItem->id)
            ->where('delivery_notes.status', '!=', DeliveryNoteStatus::Cancelled->value)
            ->sum('delivery_note_items.quantity');

        return BigDecimal::of($saleItem->quantity)->minus((string) $onNotes);
    }

    /**
     * The quantity per receipt line, with lines of zero dropped and a line sent twice added up.
     *
     * @param  list<array{sale_item_id: int, quantity: numeric-string|float|int}>  $items
     * @return array<int, BigDecimal>
     */
    private function requestedQuantities(array $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            $quantity = BigDecimal::of((string) $item['quantity']);

            if ($quantity->isPositive()) {
                $quantities[(int) $item['sale_item_id']] = ($quantities[(int) $item['sale_item_id']] ?? BigDecimal::zero())->plus($quantity);
            }
        }

        return $quantities;
    }

    /**
     * Re-reads the note under a row lock and checks its status, so two requests cannot both move it on.
     *
     * @throws DeliveryNoteException
     */
    private function lockedInStatus(DeliveryNote $note, string $action, DeliveryNoteStatus ...$allowed): DeliveryNote
    {
        $locked = DeliveryNote::query()->lockForUpdate()->findOrFail($note->id);

        if (! in_array($locked->status, $allowed, true)) {
            throw DeliveryNoteException::wrongStatus($action, $locked->status->label());
        }

        return $locked;
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
