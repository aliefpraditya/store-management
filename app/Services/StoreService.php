<?php

namespace App\Services;

use App\Enums\StoreStatus;
use App\Exceptions\StoreConnectionException;
use App\Exceptions\StoreStatusException;
use App\Models\Store;
use Illuminate\Support\Arr;
use PDOException;

/**
 * Creates and changes store records. Preparing the store database is {@see StoreProvisioner}'s job.
 *
 * @phpstan-type StoreAttributes array{name: string, address?: string|null, phone?: string|null, receipt_prefix: string, code?: string, db_name?: string, db_username?: string, db_password?: string|null, db_host?: string|null}
 */
class StoreService
{
    private const INFO_FIELDS = ['name', 'address', 'phone', 'receipt_prefix'];

    private const CONNECTION_FIELDS = ['db_username', 'db_host'];

    public function __construct(private readonly StoreConnectionChecker $connectionChecker) {}

    /**
     * The store waits in `provisioning` until its database is prepared.
     *
     * @param  StoreAttributes  $storeAttributes
     */
    public function create(array $storeAttributes): Store
    {
        return Store::query()->create([
            ...Arr::only($storeAttributes, ['code', 'db_name', ...self::INFO_FIELDS, ...self::CONNECTION_FIELDS]),
            'db_password' => $storeAttributes['db_password'] ?? null,
            'status' => StoreStatus::Provisioning,
        ]);
    }

    /**
     * The code never changes, and the database name only changes while setup has not succeeded (afterwards it
     * would point at an empty database). The user, host and password can change at any time, since they get
     * rotated in hPanel; a blank password keeps the current one. On a store that is already set up, changed
     * credentials are tested first, because a wrong one would take the running store down.
     *
     * @param  StoreAttributes  $storeAttributes
     *
     * @throws StoreConnectionException when the changed credentials of a set-up store cannot connect
     */
    public function update(Store $store, array $storeAttributes): Store
    {
        $attributes = Arr::only($storeAttributes, [...self::INFO_FIELDS, ...self::CONNECTION_FIELDS]);

        if ($store->status->isAwaitingProvisioning()) {
            $attributes += Arr::only($storeAttributes, ['db_name']);
        }

        if (filled($storeAttributes['db_password'] ?? null)) {
            $attributes['db_password'] = $storeAttributes['db_password'];
        }

        $store->forceFill($attributes);

        if (! $store->status->isAwaitingProvisioning() && $store->isDirty([...self::CONNECTION_FIELDS, 'db_password'])) {
            $this->assertCanConnect($store);
        }

        $store->save();

        return $store;
    }

    /**
     * @throws StoreConnectionException
     */
    private function assertCanConnect(Store $store): void
    {
        try {
            $this->connectionChecker->assertCanConnect($store);
        } catch (PDOException $exception) {
            throw StoreConnectionException::withMessage($this->connectionChecker->describeFailure($exception), $exception);
        }
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
