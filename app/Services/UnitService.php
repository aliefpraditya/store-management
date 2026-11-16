<?php

namespace App\Services;

use App\Exceptions\UnitInUseException;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

    /** MySQL's error number for deleting a row that another table's foreign key still points at. */
    private const MYSQL_PARENT_ROW_IN_USE = 1451;

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
        $contains = '%'.addcslashes($filters['search'], '%_\\').'%';

        return Unit::query()
            ->withCount('productUnits')
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('code', 'like', $contains)
                ->orWhere('name', 'like', $contains)))
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
     * Changing the code is safe even while products use the unit: the references follow it. The audit trail cannot hold
     * a text key (it stores numeric ids), so a changed code is written to the log, which is the only trace of what the
     * rows of `product_units` and `products` were called before.
     *
     * @param  UnitInput  $unitInput
     */
    public function update(Unit $unit, array $unitInput): Unit
    {
        $code = Unit::normalizeCode($unitInput['code']);
        $previousCode = $unit->code;

        $unit->forceFill(['code' => $code, 'name' => $this->nameOrCode($unitInput, $code)])->save();

        if ($code !== $previousCode) {
            Log::info('Unit code changed.', ['store_code' => tenant('code'), 'previous_code' => $previousCode, 'new_code' => $code, 'user_id' => Auth::id()]);
        }

        return $unit;
    }

    /**
     * A product that takes the unit between the check and the delete is stopped by the database, and told the same way.
     *
     * @throws UnitInUseException
     */
    public function delete(Unit $unit): void
    {
        if ($this->isInUse($unit)) {
            throw UnitInUseException::forUnit($unit->code);
        }

        try {
            $unit->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === self::MYSQL_PARENT_ROW_IN_USE) {
                throw UnitInUseException::forUnit($unit->code);
            }

            throw $exception;
        }
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
