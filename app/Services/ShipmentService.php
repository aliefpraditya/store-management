<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Enums\ShipmentStatus;
use App\Exceptions\ShipmentException;
use App\Models\Carrier;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Shipment;
use App\Models\ShipmentContainer;
use App\Models\ShipmentLine;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shipments: whole purchases sent with one carrier, and the carrier's packing list written down as containers and lines. A
 * line says how much of a purchase line is in which container and what the carrier charged for it, so one purchase line can be
 * split over several containers. A shipment is a draft while the packing list is being written; filling in the departure date
 * ships it, which turns every purchase in it into shipped. A purchase is in at most one shipment.
 *
 * `client_uuid` makes a double submit return the first shipment instead of making a second.
 *
 * @phpstan-type PackingListRow array{purchase_item_id: int, container_number: string, quantity: numeric-string|float|int, freight_cost: numeric-string|float|int}
 * @phpstan-type ShipmentInput array{client_uuid?: string, carrier_id: int, vessel_name?: string|null, departure_date?: string|null, notes?: string|null, purchase_ids: list<int>, allocations?: list<PackingListRow>}
 * @phpstan-type ShipmentFilters array{search: string|null, carrier: int|null, status: string|null}
 * @phpstan-type Allocation array{container: string, item: PurchaseItem, quantity: BigDecimal, freight_cost: BigDecimal}
 */
class ShipmentService
{
    private const NUMBER_TYPE = 'PG';

    public function __construct(private readonly DocumentNumberGenerator $documentNumbers) {}

    /**
     * Newest first, narrowed by a search on the number, the vessel or a container number, by carrier and by status. Each row
     * carries what the carrier charged in all (`total_freight_cost`).
     *
     * @param  ShipmentFilters  $filters
     * @return LengthAwarePaginator<int, Shipment>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return Shipment::query()
            ->with('carrier')
            ->withCount(['purchases', 'containers'])
            ->addSelect(['total_freight_cost' => ShipmentLine::query()
                ->selectRaw('coalesce(sum(shipment_lines.freight_cost), 0)')
                ->join('shipment_containers', 'shipment_containers.id', '=', 'shipment_lines.shipment_container_id')
                ->whereColumn('shipment_containers.shipment_id', 'shipments.id')])
            ->when(filled($filters['search']), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$filters['search']}%")
                ->orWhere('vessel_name', 'like', "%{$filters['search']}%")
                ->orWhereHas('containers', fn (Builder $container) => $container->where('number', 'like', "%{$filters['search']}%"))))
            ->when($filters['carrier'], fn (Builder $query, int $carrierId) => $query->where('carrier_id', $carrierId))
            ->when(ShipmentStatus::tryFrom((string) $filters['status']), fn (Builder $query, ShipmentStatus $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The purchases that can go into a shipment: ordered (so paid) and not in another shipment, plus the ones this shipment
     * already holds when it is being changed.
     *
     * @return Collection<int, Purchase>
     */
    public function availablePurchases(?Shipment $shipment = null): Collection
    {
        return Purchase::query()
            ->with(['supplier', 'items.product'])
            ->where('status', PurchaseStatus::Ordered)
            ->where(fn (Builder $query) => $query->whereNull('shipment_id')->when($shipment, fn (Builder $query, Shipment $shipment) => $query->orWhere('shipment_id', $shipment->id)))
            ->orderBy('id')
            ->get();
    }

    /**
     * Makes a draft shipment with its purchases and packing list, and ships it when it has a departure date.
     *
     * @param  ShipmentInput  $shipmentInput
     *
     * @throws ShipmentException
     */
    public function create(User $creator, array $shipmentInput): Shipment
    {
        $alreadyCreated = $this->alreadyCreated($shipmentInput['client_uuid'] ?? '', $creator);

        if ($alreadyCreated !== null) {
            return $alreadyCreated;
        }

        try {
            return DB::transaction(function () use ($creator, $shipmentInput): Shipment {
                $shipment = Shipment::query()->create([
                    'client_uuid' => $shipmentInput['client_uuid'],
                    'number' => $this->documentNumbers->next(self::NUMBER_TYPE, now()),
                    'carrier_id' => $shipmentInput['carrier_id'],
                    'status' => ShipmentStatus::Draft,
                    'created_by' => $creator->id,
                    'created_by_name' => $creator->name,
                ]);

                return $this->fill($shipment, $shipmentInput, currentCarrierId: null);
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->alreadyCreated($shipmentInput['client_uuid'] ?? '', $creator) ?? throw $exception;
        }
    }

    /**
     * Changes a draft: its header, its purchases and its whole packing list, which is replaced by the one sent. A shipment that
     * has departed is locked.
     *
     * @param  ShipmentInput  $shipmentInput
     *
     * @throws ShipmentException
     */
    public function update(Shipment $shipment, array $shipmentInput): Shipment
    {
        return DB::transaction(function () use ($shipment, $shipmentInput): Shipment {
            $lockedShipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if (! $lockedShipment->status->isEditable()) {
                throw ShipmentException::notEditable();
            }

            return $this->fill($lockedShipment, $shipmentInput, currentCarrierId: $lockedShipment->carrier_id);
        });
    }

    /**
     * Cancels a draft shipment, which frees its purchases for another one; it stays on the list as cancelled, with the reason.
     *
     * @throws ShipmentException
     */
    public function cancel(Shipment $shipment, string $reason): Shipment
    {
        return DB::transaction(function () use ($shipment, $reason): Shipment {
            $lockedShipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if (! $lockedShipment->status->isEditable()) {
                throw ShipmentException::notCancellable();
            }

            $this->releasePurchases($lockedShipment, keeping: []);
            $lockedShipment->forceFill(['status' => ShipmentStatus::Cancelled, 'cancel_reason' => trim($reason), 'cancelled_at' => now()])->save();

            return $lockedShipment;
        });
    }

    /**
     * Writes the header, the purchases and the packing list of a locked shipment, and ships it when it has a departure date. A
     * switched-off carrier is refused unless it is the one the shipment already has.
     *
     * @param  ShipmentInput  $shipmentInput
     * @param  int|null  $currentCarrierId  the carrier the shipment had before, none for a new one
     *
     * @throws ShipmentException
     */
    private function fill(Shipment $shipment, array $shipmentInput, ?int $currentCarrierId): Shipment
    {
        $carrier = Carrier::query()->findOrFail($shipmentInput['carrier_id']);

        if (! $carrier->is_active && $carrier->id !== $currentCarrierId) {
            throw ShipmentException::carrierInactive();
        }

        $purchases = $this->lockedPurchases($shipment, $shipmentInput['purchase_ids']);

        $shipment->forceFill([
            'carrier_id' => $carrier->id,
            'vessel_name' => filled($shipmentInput['vessel_name'] ?? null) ? trim($shipmentInput['vessel_name']) : null,
            'departure_date' => filled($shipmentInput['departure_date'] ?? null) ? $shipmentInput['departure_date'] : null,
            'notes' => filled($shipmentInput['notes'] ?? null) ? trim($shipmentInput['notes']) : null,
        ])->save();

        $this->releasePurchases($shipment, keeping: $purchases->modelKeys());
        $purchases->each(fn (Purchase $purchase) => $purchase->forceFill(['shipment_id' => $shipment->id])->save());

        $allocations = $this->allocationsOf($purchases, $shipmentInput['allocations'] ?? []);
        $this->writePackingList($shipment, $allocations);

        if ($shipment->departure_date !== null) {
            $this->ship($shipment, $purchases, $allocations);
        }

        return $shipment;
    }

    /**
     * The purchases to put in the shipment, locked, each ordered and either free or already in this shipment.
     *
     * @param  list<int>  $purchaseIds
     * @return Collection<int, Purchase>
     *
     * @throws ShipmentException
     */
    private function lockedPurchases(Shipment $shipment, array $purchaseIds): Collection
    {
        $purchases = Purchase::query()
            ->with('items.product')
            ->whereIn('id', array_values(array_unique($purchaseIds)))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($purchases as $purchase) {
            $isFree = $purchase->shipment_id === null || $purchase->shipment_id === $shipment->id;

            if ($purchase->status !== PurchaseStatus::Ordered || ! $isFree) {
                throw ShipmentException::purchaseNotAvailable($purchase->number);
            }
        }

        return $purchases;
    }

    /**
     * Takes the purchases that are no longer in the shipment out of it.
     *
     * @param  list<int>  $keeping  ids of the purchases that stay
     */
    private function releasePurchases(Shipment $shipment, array $keeping): void
    {
        Purchase::query()
            ->where('shipment_id', $shipment->id)
            ->whereNotIn('id', $keeping)
            ->get()
            ->each(fn (Purchase $purchase) => $purchase->forceFill(['shipment_id' => null])->save());
    }

    /**
     * The packing list rows as they are kept: rows for the same container and line are added together, every line must be one of
     * the shipment's purchases, and a line cannot have more in its containers than the nota says.
     *
     * @param  Collection<int, Purchase>  $purchases
     * @param  list<PackingListRow>  $rows
     * @return list<Allocation>
     *
     * @throws ShipmentException
     */
    private function allocationsOf(Collection $purchases, array $rows): array
    {
        $items = $purchases->flatMap(fn (Purchase $purchase) => $purchase->items)->keyBy('id');
        $merged = [];

        foreach ($rows as $row) {
            $item = $items[(int) $row['purchase_item_id']] ?? throw ShipmentException::itemNotInShipment();
            $container = Str::upper((string) preg_replace('/\s+/', ' ', trim($row['container_number'])));
            $quantity = BigDecimal::of((string) $row['quantity'])->toScale(3, RoundingMode::HalfUp);
            $freightCost = BigDecimal::of((string) $row['freight_cost'])->toScale(2, RoundingMode::HalfUp);
            $key = "{$container}|{$item->id}";

            $merged[$key] = isset($merged[$key])
                ? [...$merged[$key], 'quantity' => $merged[$key]['quantity']->plus($quantity), 'freight_cost' => $merged[$key]['freight_cost']->plus($freightCost)]
                : ['container' => $container, 'item' => $item, 'quantity' => $quantity, 'freight_cost' => $freightCost];
        }

        $allocations = array_values($merged);

        foreach ($this->allocatedQuantities($allocations) as $itemId => $allocatedQuantity) {
            $item = $items[$itemId];

            if ($allocatedQuantity->isGreaterThan(BigDecimal::of((string) $item->quantity))) {
                throw ShipmentException::overAllocated($item->product->name, (string) $item->quantity);
            }
        }

        return $allocations;
    }

    /**
     * How much of each purchase line the containers hold in all.
     *
     * @param  list<Allocation>  $allocations
     * @return array<int, BigDecimal> by purchase item id
     */
    private function allocatedQuantities(array $allocations): array
    {
        $quantities = [];

        foreach ($allocations as $allocation) {
            $quantities[$allocation['item']->id] = ($quantities[$allocation['item']->id] ?? BigDecimal::zero())->plus($allocation['quantity']);
        }

        return $quantities;
    }

    /**
     * Replaces the shipment's containers and lines with the given ones.
     *
     * @param  list<Allocation>  $allocations
     */
    private function writePackingList(Shipment $shipment, array $allocations): void
    {
        $containerIds = $shipment->containers()->pluck('id');
        ShipmentLine::query()->whereIn('shipment_container_id', $containerIds)->delete();
        ShipmentContainer::query()->whereIn('id', $containerIds)->delete();

        collect($allocations)->groupBy(fn (array $allocation): string => $allocation['container'])->each(function (Collection $containerAllocations) use ($shipment): void {
            $container = $shipment->containers()->create(['number' => $containerAllocations->first()['container']]);

            $containerAllocations->each(fn (array $allocation) => $container->lines()->create([
                'purchase_item_id' => $allocation['item']->id,
                'quantity' => (string) $allocation['quantity'],
                'base_quantity' => (string) $allocation['quantity']->multipliedBy($allocation['item']->conversion_factor)->toScale(3, RoundingMode::HalfUp),
                'freight_cost' => (string) $allocation['freight_cost'],
            ]));
        });
    }

    /**
     * Departs: the packing list must hold every bit of every purchase, then the shipment and its purchases become shipped,
     * and the purchases carry the shipment's carrier.
     *
     * @param  Collection<int, Purchase>  $purchases
     * @param  list<Allocation>  $allocations
     *
     * @throws ShipmentException
     */
    private function ship(Shipment $shipment, Collection $purchases, array $allocations): void
    {
        if ($allocations === []) {
            throw ShipmentException::withoutContainers();
        }

        $allocatedQuantities = $this->allocatedQuantities($allocations);

        foreach ($purchases as $purchase) {
            foreach ($purchase->items as $item) {
                $allocatedQuantity = $allocatedQuantities[$item->id] ?? BigDecimal::zero();

                if (! $allocatedQuantity->isEqualTo(BigDecimal::of((string) $item->quantity))) {
                    throw ShipmentException::notFullyAllocated($purchase->number, $item->product->name);
                }
            }
        }

        $shipment->forceFill(['status' => ShipmentStatus::Shipped, 'shipped_at' => now()])->save();
        $purchases->each(fn (Purchase $purchase) => $purchase->forceFill(['status' => PurchaseStatus::Shipped, 'carrier_id' => $shipment->carrier_id])->save());
    }

    /**
     * The shipment a retried request made the first time, so a double tap cannot make it twice. A marker that belongs to
     * someone else's shipment is refused: it is not a retry.
     *
     * @throws ShipmentException
     */
    private function alreadyCreated(string $clientUuid, User $creator): ?Shipment
    {
        if ($clientUuid === '') {
            return null;
        }

        $createdShipment = Shipment::query()->where('client_uuid', $clientUuid)->first();

        if ($createdShipment === null) {
            return null;
        }

        return $createdShipment->created_by === $creator->id ? $createdShipment : throw ShipmentException::shipmentIdInUse();
    }
}
