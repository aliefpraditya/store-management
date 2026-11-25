<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The suppliers a store buys from. A supplier is switched off, never deleted, so the purchases made from it keep their
 * supplier.
 *
 * @phpstan-type SupplierInput array{name: string, phone?: string|null, notes?: string|null, is_active?: bool}
 */
class SupplierService
{
    /**
     * By name, narrowed by a search on the name or phone and by whether the supplier is active.
     *
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function paginate(?string $search, ?string $status, int $perPage): LengthAwarePaginator
    {
        return Supplier::query()
            ->withCount('purchases')
            ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
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
        return Supplier::query()->create([
            'name' => trim($supplierInput['name']),
            'phone' => $this->trimmedOrNull($supplierInput['phone'] ?? null),
            'notes' => $this->trimmedOrNull($supplierInput['notes'] ?? null),
            'is_active' => $supplierInput['is_active'] ?? true,
        ]);
    }

    /**
     * @param  SupplierInput  $supplierInput
     */
    public function update(Supplier $supplier, array $supplierInput): Supplier
    {
        $supplier->forceFill([
            'name' => trim($supplierInput['name']),
            'phone' => $this->trimmedOrNull($supplierInput['phone'] ?? null),
            'notes' => $this->trimmedOrNull($supplierInput['notes'] ?? null),
            'is_active' => $supplierInput['is_active'] ?? $supplier->is_active,
        ])->save();

        return $supplier;
    }

    private function trimmedOrNull(?string $text): ?string
    {
        return filled($text) ? trim($text) : null;
    }
}
