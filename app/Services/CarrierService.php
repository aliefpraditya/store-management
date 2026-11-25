<?php

namespace App\Services;

use App\Models\Carrier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The carriers (jasa logistik) a store's purchases are sent with. A carrier is switched off, never deleted, so the purchases
 * sent with it keep their carrier.
 *
 * @phpstan-type CarrierInput array{name: string, phone?: string|null, address?: string|null, province?: string|null, city?: string|null, transit_days?: int|null, notes?: string|null, is_active?: bool, contacts?: list<array{id?: int|null, name: string, phone?: string|null}>}
 */
class CarrierService
{
    public function __construct(private readonly ContactSynchronizer $contactSynchronizer) {}

    /**
     * By name, narrowed by a search on the name, phone, city or the name of a contact and by whether the carrier is active.
     *
     * @return LengthAwarePaginator<int, Carrier>
     */
    public function paginate(?string $search, ?string $status, int $perPage): LengthAwarePaginator
    {
        return Carrier::query()
            ->with('contacts')
            ->withCount('purchases')
            ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhereHas('contacts', fn (Builder $contact) => $contact->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))))
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The carriers a purchase can be sent with, plus the one the purchase already has even if it was switched off since,
     * so editing that purchase does not lose it.
     *
     * @return Collection<int, Carrier>
     */
    public function selectable(?int $currentCarrierId = null): Collection
    {
        return Carrier::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $currentCarrierId))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  CarrierInput  $carrierInput
     */
    public function create(array $carrierInput): Carrier
    {
        return DB::transaction(function () use ($carrierInput): Carrier {
            $carrier = Carrier::query()->create([
                ...$this->detailsOf($carrierInput),
                'is_active' => $carrierInput['is_active'] ?? true,
            ]);

            $this->contactSynchronizer->sync($carrier->contacts(), $carrierInput['contacts'] ?? []);

            return $carrier;
        });
    }

    /**
     * The contacts are only touched when the input carries a `contacts` key, so a request that just switches the carrier on
     * or off leaves them alone.
     *
     * @param  CarrierInput  $carrierInput
     */
    public function update(Carrier $carrier, array $carrierInput): Carrier
    {
        return DB::transaction(function () use ($carrier, $carrierInput): Carrier {
            $carrier->forceFill([
                ...$this->detailsOf($carrierInput),
                'is_active' => $carrierInput['is_active'] ?? $carrier->is_active,
            ])->save();

            if (array_key_exists('contacts', $carrierInput)) {
                $this->contactSynchronizer->sync($carrier->contacts(), $carrierInput['contacts']);
            }

            return $carrier;
        });
    }

    /**
     * @param  CarrierInput  $carrierInput
     * @return array{name: string, phone: ?string, address: ?string, province: ?string, city: ?string, transit_days: ?int, notes: ?string}
     */
    private function detailsOf(array $carrierInput): array
    {
        return [
            'name' => trim($carrierInput['name']),
            'phone' => $this->trimmedOrNull($carrierInput['phone'] ?? null),
            'address' => $this->trimmedOrNull($carrierInput['address'] ?? null),
            'province' => $this->trimmedOrNull($carrierInput['province'] ?? null),
            'city' => $this->trimmedOrNull($carrierInput['city'] ?? null),
            'transit_days' => filled($carrierInput['transit_days'] ?? null) ? (int) $carrierInput['transit_days'] : null,
            'notes' => $this->trimmedOrNull($carrierInput['notes'] ?? null),
        ];
    }

    private function trimmedOrNull(?string $text): ?string
    {
        return filled($text) ? trim($text) : null;
    }
}
