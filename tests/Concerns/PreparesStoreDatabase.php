<?php

namespace Tests\Concerns;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Services\StoreProvisioner;
use Database\Seeders\StoreDatabaseSeeder;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Gives a test class a real, fully migrated store database. The class defines `STORE_DATABASE`.
 *
 * The database is created and provisioned once per class and emptied (then re-seeded) before every
 * test, because migrating about thirty tables for each test would make the suite slow.
 */
trait PreparesStoreDatabase
{
    /** @var array<string, true> store databases already provisioned in this run */
    private static array $provisionedDatabases = [];

    public static function tearDownAfterClass(): void
    {
        $pdo = new PDO(
            'mysql:host='.(getenv('DB_HOST') ?: '127.0.0.1').';port='.(getenv('DB_PORT') ?: '3306'),
            getenv('DB_USERNAME') ?: 'root',
            getenv('DB_PASSWORD') ?: '',
        );
        $pdo->exec('DROP DATABASE IF EXISTS `'.static::STORE_DATABASE.'`');

        unset(self::$provisionedDatabases[static::STORE_DATABASE]);

        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function prepareStoreDatabase(array $attributes = []): Store
    {
        $database = static::STORE_DATABASE;

        DB::connection('mysql')->statement("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->emptyStoreTables($database);

        $store = Store::factory()->create(['db_name' => $database, ...$attributes]);

        if (isset(self::$provisionedDatabases[$database])) {
            $store->run(fn () => (new StoreDatabaseSeeder)->run());

            return $store;
        }

        $store->forceFill(['status' => StoreStatus::Provisioning])->save();
        app(StoreProvisioner::class)->provision($store);
        self::$provisionedDatabases[$database] = true;

        return $store->refresh();
    }

    private function emptyStoreTables(string $database): void
    {
        $central = DB::connection('mysql');

        $tables = $central->table('information_schema.tables')
            ->where('table_schema', $database)
            ->where('table_name', '!=', 'migrations')
            ->select('table_name as name')
            ->pluck('name');

        $central->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            $central->statement("TRUNCATE TABLE `{$database}`.`{$table}`");
        }

        $central->statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
