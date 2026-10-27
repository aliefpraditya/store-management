<?php

namespace App\Services;

use App\Enums\StoreStatus;
use App\Exceptions\StoreProvisioningException;
use App\Models\Store;
use App\Models\StoreProvisioningLog;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Prepares the database of a store. The database itself is created by hand (hPanel on Hostinger);
 * this only tests the connection and migrates the tenant tables, and is safe to run again after a failure.
 */
class StoreProvisioner
{
    private const CHECK_CONNECTION = 'store_provisioning_check';

    private const STEP_LABELS = [
        'connection' => 'Uji koneksi database',
        'migration' => 'Migrasi tabel',
    ];

    /**
     * @throws StoreProvisioningException when a step fails; the store is then marked as failed
     */
    public function provision(Store $store): void
    {
        $store->forceFill(['status' => StoreStatus::Provisioning])->save();

        $this->runStep($store, 'connection', fn () => $this->assertCanConnect($store));
        $this->runStep($store, 'migration', fn () => $this->migrate($store));

        $store->forceFill([
            'status' => StoreStatus::Active,
            'schema_version' => $this->latestMigrationOf($store),
        ])->save();
    }

    private function runStep(Store $store, string $step, Closure $action): void
    {
        try {
            $action();
        } catch (Throwable $exception) {
            $this->recordFailure($store, $step, $exception);

            throw new StoreProvisioningException(
                self::STEP_LABELS[$step].' gagal: '.$this->shortMessage($exception),
                previous: $exception,
            );
        }

        $this->log($store, $step, 'ok');
    }

    private function recordFailure(Store $store, string $step, Throwable $exception): void
    {
        DB::transaction(function () use ($store, $step, $exception): void {
            $this->log($store, $step, 'failed', $this->shortMessage($exception));

            $store->forceFill(['status' => StoreStatus::Failed])->save();
        });
    }

    private function log(Store $store, string $step, string $result, ?string $message = null): void
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

        config(['database.connections.'.self::CHECK_CONNECTION => $store->database()->connection()]);
        DB::purge(self::CHECK_CONNECTION);

        try {
            DB::connection(self::CHECK_CONNECTION)->getPdo();
        } finally {
            DB::purge(self::CHECK_CONNECTION);
        }
    }

    private function migrate(Store $store): void
    {
        $exitCode = Artisan::call('tenants:migrate', ['--tenants' => [$store->getTenantKey()]]);

        if ($exitCode !== 0) {
            throw new RuntimeException(trim(Artisan::output()));
        }
    }

    private function latestMigrationOf(Store $store): ?string
    {
        return $store->run(fn () => DB::table('migrations')->orderByDesc('id')->value('migration'));
    }

    private function centralDatabaseName(): string
    {
        return config('database.connections.'.config('tenancy.database.central_connection').'.database');
    }

    private function shortMessage(Throwable $exception): string
    {
        return Str::limit($exception->getMessage(), 500);
    }
}
