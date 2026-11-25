<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The suppliers a store buys from. A supplier is switched off, never deleted, so the purchases made from it keep their
 * supplier.
 *
 * @phpstan-type SupplierInput array{name: string, phone?: string|null, address?: string|null, province?: string|null, city?: string|null, email?: string|null, tax_number?: string|null, notes?: string|null, is_active?: bool, contacts?: list<array{id?: int|null, name: string, phone?: string|null}>}
 */
class SupplierService
{
    public function __construct(private readonly ContactSynchronizer $contactSynchronizer) {}

    /**
     * By name, narrowed by a search on the name or phone and by whether the supplier is active.
     *
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function paginate(?string $search, ?string $status, int $perPage): LengthAwarePaginator
    {
        return Supplier::query()
            ->with('contacts')
            ->withCount('purchases')
            ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")))
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The suppliers a new purchase can be made from.
     *
     * @return Collection<int, Supplier>
     */
    public function active(): Collection
    {
        return Supplier::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @param  SupplierInput  $supplierInput
     */
    public function create(array $supplierInput): Supplier
    {
        return DB::transaction(function () use ($supplierInput): Supplier {
            $supplier = Supplier::query()->create([
                ...$this->detailsOf($supplierInput),
                'is_active' => $supplierInput['is_active'] ?? true,
            ]);

            $this->contactSynchronizer->sync($supplier->contacts(), $supplierInput['contacts'] ?? []);

            return $supplier;
        });
    }

    /**
     * The contacts are only touched when the input carries a `contacts` key, so a request that just switches the supplier
     * on or off leaves its salespeople alone.
     *
     * @param  SupplierInput  $supplierInput
     */
    public function update(Supplier $supplier, array $supplierInput): Supplier
    {
        return DB::transaction(function () use ($supplier, $supplierInput): Supplier {
            $supplier->forceFill([
                ...$this->detailsOf($supplierInput),
                'is_active' => $supplierInput['is_active'] ?? $supplier->is_active,
            ])->save();

            if (array_key_exists('contacts', $supplierInput)) {
                $this->contactSynchronizer->sync($supplier->contacts(), $supplierInput['contacts']);
            }

            return $supplier;
        });
    }

    /**
     * @param  SupplierInput  $supplierInput
     * @return array{name: string, phone: ?string, address: ?string, province: ?string, city: ?string, email: ?string, tax_number: ?string, notes: ?string}
     */
    private function detailsOf(array $supplierInput): array
    {
        return [
            'name' => trim($supplierInput['name']),
            'phone' => $this->trimmedOrNull($supplierInput['phone'] ?? null),
            'address' => $this->trimmedOrNull($supplierInput['address'] ?? null),
            'province' => $this->trimmedOrNull($supplierInput['province'] ?? null),
            'city' => $this->trimmedOrNull($supplierInput['city'] ?? null),
            'email' => $this->trimmedOrNull($supplierInput['email'] ?? null),
            'tax_number' => $this->trimmedOrNull($supplierInput['tax_number'] ?? null),
            'notes' => $this->trimmedOrNull($supplierInput['notes'] ?? null),
        ];
    }

    private function trimmedOrNull(?string $text): ?string
    {
        return filled($text) ? trim($text) : null;
    }
}
