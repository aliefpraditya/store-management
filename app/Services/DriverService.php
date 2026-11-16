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

    public function create(string $name, bool $isActive = true): Driver
    {
        return Driver::query()->create(['name' => trim($name), 'is_active' => $isActive]);
    }

    /**
     * Brings the drivers to what the settings page sent: a row with an id is renamed or switched, a row without one is
     * added. A driver left out of the rows is left as it is, since a driver is never deleted.
     *
     * @param  list<array{id?: int|null, name: string, is_active: bool}>  $rows
     */
    public function sync(array $rows): void
    {
        foreach ($rows as $row) {
            if (filled($row['id'] ?? null)) {
                $this->update(Driver::query()->findOrFail($row['id']), ['name' => $row['name'], 'is_active' => (bool) $row['is_active']]);

                continue;
            }

            $this->create($row['name'], (bool) $row['is_active']);
        }
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
