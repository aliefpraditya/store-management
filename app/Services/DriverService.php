<?php

namespace App\Services;

use App\Models\Driver;
use Illuminate\Support\Collection;

/**
 * The drivers of a store: added, renamed and switched on or off from the store settings page, and offered (the active
 * ones) when a delivery note is shipped.
 */
class DriverService
{
    /**
     * @return Collection<int, Driver>
     */
    public function all(): Collection
    {
        return Driver::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Driver>
     */
    public function active(): Collection
    {
        return Driver::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function create(string $name): Driver
    {
        return Driver::query()->create(['name' => trim($name), 'is_active' => true]);
    }

    /**
     * @param  array{name: string, is_active?: bool|null}  $changes
     */
    public function update(Driver $driver, array $changes): Driver
    {
        $driver->forceFill([
            'name' => trim($changes['name']),
            'is_active' => $changes['is_active'] ?? $driver->is_active,
        ])->save();

        return $driver;
    }
}
