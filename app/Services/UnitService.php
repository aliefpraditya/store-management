<?php

namespace App\Services;

use App\Exceptions\UnitInUseException;
use App\Models\Product;
use App\Models\Unit;

/**
 * Manages the units of one store. A unit is identified by its code, which is stored in upper case; a blank
 * name falls back to the code.
 *
 * @phpstan-type UnitInput array{code: string, name?: string|null}
 */
class UnitService
{
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
