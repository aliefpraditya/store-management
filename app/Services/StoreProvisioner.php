<?php

namespace App\Services;

use App\Enums\ProvisioningResult;
use App\Enums\ProvisioningStep;
use App\Enums\StoreStatus;
use App\Exceptions\StoreProvisioningException;
use App\Listeners\RecordStoreSchemaVersion;
use App\Models\Store;
use App\Models\StoreProvisioningLog;
use Closure;
use Database\Seeders\StoreDatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Prepares the database of a store. The database itself is created by hand (hPanel on Hostinger);
 * this only tests the connection and migrates the tenant tables, and is safe to run again after a failure.
 * `stores.schema_version` is kept by {@see RecordStoreSchemaVersion} when the tables are migrated.
 */
class StoreProvisioner
{
    private const LOCK_KEY_PREFIX = 'store-provisioning:';

    /** Longer than any single setup should take, so a crashed run cannot block retries for long. */
    private const LOCK_SECONDS = 600;

    public function __construct(private readonly StoreConnectionChecker $connectionChecker) {}

    /**
     * @throws StoreProvisioningException when a step fails (the store is then marked as failed), the store is
     *                                    already set up, or another run for the same store is in progress
     */
    public function provision(Store $store): void
    {
        $lock = Cache::lock(self::LOCK_KEY_PREFIX.$store->getKey(), self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw StoreProvisioningException::alreadyRunning();
        }

        try {
            $this->provisionWhileLocked($store);
        } finally {
            $lock->release();
        }
    }

    private function provisionWhileLocked(Store $store): void
    {
        $store->refresh();

        if (! $store->status->isAwaitingProvisioning()) {
            throw StoreProvisioningException::alreadyReady();
        }

        $store->forceFill(['status' => StoreStatus::Provisioning])->save();

        $this->runStep($store, ProvisioningStep::Connection, fn () => $this->assertCanConnect($store));
        $this->runStep($store, ProvisioningStep::Migration, fn () => $this->migrate($store));
        $this->runStep($store, ProvisioningStep::Seed, fn () => $this->seed($store));

        $store->forceFill(['status' => StoreStatus::Active])->save();
    }

    private function runStep(Store $store, ProvisioningStep $step, Closure $stepAction): void
    {
        try {
            $stepAction();
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($store, $step, $exception);

            throw new StoreProvisioningException(
                $step->label().' gagal: '.$this->describeFailure($exception),
                previous: $exception,
            );
        }

        $this->log($store, $step, ProvisioningResult::Ok);
    }

    /**
     * Ends tenancy first: a migration or seed that throws leaves the store's database as the default
     * connection (stancl's `run()` has no `finally`), and the failure must be recorded centrally.
     */
    private function recordFailure(Store $store, ProvisioningStep $step, Throwable $exception): void
    {
        tenancy()->end();

        DB::transaction(function () use ($store, $step, $exception): void {
            $this->log($store, $step, ProvisioningResult::Failed, $this->describeFailure($exception));

            $store->forceFill(['status' => StoreStatus::Failed])->save();
        });
    }

    private function log(Store $store, ProvisioningStep $step, ProvisioningResult $result, ?string $message = null): void
    {
        StoreProvisioningLog::query()->create([
            'store_id' => $store->getKey(),
            'step' => $step,
            'result' => $result,
            'message' => $message,
        ]);
    }

    private function assertCanConnect(Store $store): void
    {
        if ($store->db_name === $this->centralDatabaseName()) {
            throw new RuntimeException('Database toko tidak boleh sama dengan database pusat.');
        }

        $this->connectionChecker->assertCanConnect($store);
    }

    private function migrate(Store $store): void
    {
        $exitCode = Artisan::call('tenants:migrate', ['--tenants' => [$store->getTenantKey()]]);

        if ($exitCode !== 0) {
            throw new RuntimeException(trim(Artisan::output()));
        }
    }

    private function seed(Store $store): void
    {
        $exitCode = Artisan::call('tenants:seed', [
            '--tenants' => [$store->getTenantKey()],
            '--class' => StoreDatabaseSeeder::class,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException(trim(Artisan::output()));
        }
    }

    private function centralDatabaseName(): string
    {
        return config('database.connections.'.config('tenancy.database.central_connection').'.database');
    }

    private function describeFailure(Throwable $exception): string
    {
        return $this->connectionChecker->describeFailure($exception);
    }
}
