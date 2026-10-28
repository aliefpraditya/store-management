<?php

namespace App\Services;

use App\Exceptions\UnitInUseException;
use App\Models\Product;
use App\Models\Unit;

/**
 * Manages the units of one store. A unit is identified by its code, which is stored in upper case; a blank
 * name falls back to the code.
 *
 * @phpstan-type UnitData array{code: string, name?: string|null}
 */
class UnitService
{
    /**
     * @param  UnitData  $data
     */
    public function create(array $data): Unit
    {
        $code = Unit::normalizeCode($data['code']);

        return Unit::query()->create(['code' => $code, 'name' => $this->nameOrCode($data, $code)]);
    }

    /**
     * Changing the code is safe even while products use the unit: the references follow it.
     *
     * @param  UnitData  $data
     */
    public function update(Unit $unit, array $data): Unit
    {
        $code = Unit::normalizeCode($data['code']);

        $unit->forceFill(['code' => $code, 'name' => $this->nameOrCode($data, $code)])->save();

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
     * @param  UnitData  $data
     */
    private function nameOrCode(array $data, string $code): string
    {
        return filled($data['name'] ?? null) ? trim($data['name']) : $code;
    }
}
