<?php

namespace App\Services;

use App\Exceptions\UnitInUseException;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Manages the units of one store. A unit is identified by its code, which is stored in upper case; a blank
 * name falls back to the code.
 *
 * @phpstan-type UnitInput array{code: string, name?: string|null}
 * @phpstan-type UnitFilters array{search: string, sort: string, direction: string, per_page: int}
 */
class UnitService
{
    /** The columns the unit list can be sorted by. */
    public const SORTABLE_COLUMNS = ['code', 'name', 'products_count'];

    /** How many units a page of the list can hold, as the settings page offers them. */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * The list as asked for, made safe: a sort column or page size that is not on offer, or a direction that is not
     * asc or desc, falls back to the default (by code, ascending, ten per page).
     *
     * @param  array<string, mixed>  $requested
     * @return UnitFilters
     */
    public function filtersFrom(array $requested): array
    {
        $perPage = (int) ($requested['per_page'] ?? 0);

        return [
            'search' => trim((string) ($requested['search'] ?? '')),
            'sort' => in_array($requested['sort'] ?? null, self::SORTABLE_COLUMNS, true) ? $requested['sort'] : 'code',
            'direction' => ($requested['direction'] ?? null) === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0],
        ];
    }

    /**
     * One page of units with how many products use each, narrowed by a search on the code or name. The page number
     * has its own name (unit_page) because the list sits on a page that has other things on it.
     *
     * @param  UnitFilters  $filters
     * @return LengthAwarePaginator<int, Unit>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Unit::query()
            ->withCount('productUnits')
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('code', 'like', "%{$filters['search']}%")
                ->orWhere('name', 'like', "%{$filters['search']}%")))
            ->orderBy($filters['sort'] === 'products_count' ? 'product_units_count' : $filters['sort'], $filters['direction'])
            ->orderBy('code')
            ->paginate($filters['per_page'], pageName: 'unit_page')
            ->withQueryString();
    }

    /**
     * @param  UnitInput  $unitInput
     */
    public function create(array $unitInput): Unit
    {
        $code = Unit::normalizeCode($unitInput['code']);

        return Unit::query()->create(['code' => $code, 'name' => $this->nameOrCode($unitInput, $code)]);
    }

    /**
     * Changing the code is safe even while products use the unit: the references follow it.
     *
     * @param  UnitInput  $unitInput
     */
    public function update(Unit $unit, array $unitInput): Unit
    {
        $code = Unit::normalizeCode($unitInput['code']);

        $unit->forceFill(['code' => $code, 'name' => $this->nameOrCode($unitInput, $code)])->save();

        return $unit;
    }

    /**
     * @throws UnitInUseException
     */
    public function delete(Unit $unit): void
    {
        if ($this->isInUse($unit)) {
            throw UnitInUseException::forUnit($unit->code);
        }

        $unit->delete();
    }

    public function isInUse(Unit $unit): bool
    {
        return $unit->productUnits()->exists()
            || Product::withTrashed()->where('base_unit_code', $unit->code)->exists();
    }

    /**
     * @param  UnitInput  $unitInput
     */
    private function nameOrCode(array $unitInput, string $code): string
    {
        return filled($unitInput['name'] ?? null) ? trim($unitInput['name']) : $code;
    }
}
