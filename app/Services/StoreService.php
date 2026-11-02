<?php

namespace App\Services;

use App\Enums\StoreStatus;
use App\Exceptions\StoreStatusException;
use App\Models\Store;
use Illuminate\Support\Arr;

/**
 * Creates and changes store records. Preparing the store database is {@see StoreProvisioner}'s job.
 *
 * @phpstan-type StoreAttributes array{name: string, address?: string|null, phone?: string|null, receipt_prefix: string, code?: string, db_name?: string, db_username?: string, db_password?: string|null, db_host?: string|null}
 */
class StoreService
{
    private const INFO_FIELDS = ['name', 'address', 'phone', 'receipt_prefix'];

    private const DATABASE_FIELDS = ['db_name', 'db_username', 'db_host'];

    /**
     * The store waits in `provisioning` until its database is prepared.
     *
     * @param  StoreAttributes  $storeAttributes
     */
    public function create(array $storeAttributes): Store
    {
        return Store::query()->create([
            ...Arr::only($storeAttributes, ['code', ...self::INFO_FIELDS, ...self::DATABASE_FIELDS]),
            'db_password' => $storeAttributes['db_password'] ?? null,
            'status' => StoreStatus::Provisioning,
        ]);
    }

    /**
     * The code never changes. Database credentials only change while setup has not succeeded, and a
     * blank password keeps the current one.
     *
     * @param  StoreAttributes  $storeAttributes
     */
    public function update(Store $store, array $storeAttributes): Store
    {
        $attributes = Arr::only($storeAttributes, self::INFO_FIELDS);

        if ($store->status->isAwaitingProvisioning()) {
            $attributes += Arr::only($storeAttributes, self::DATABASE_FIELDS);

            if (filled($storeAttributes['db_password'] ?? null)) {
                $attributes['db_password'] = $storeAttributes['db_password'];
            }
        }

        $store->forceFill($attributes)->save();

        return $store;
    }

    /**
     * @throws StoreStatusException when the store has not finished setup
     */
    public function setActive(Store $store, bool $isActive): void
    {
        if (! $store->status->canBeToggled()) {
            throw StoreStatusException::notReadyToToggle();
        }

        $store->forceFill(['status' => $isActive ? StoreStatus::Active : StoreStatus::Inactive])->save();
    }
}
