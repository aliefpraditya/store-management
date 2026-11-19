<?php

namespace Tests\Feature\Central;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Enums\WarehouseType;
use App\Exceptions\StoreProvisioningException;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreProvisioningLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StoreProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreManagementTest extends TestCase
{
    use RefreshDatabase;

    private const READY_DATABASE = 'store_test_master_ready';

    private const LATE_DATABASE = 'store_test_master_late';

    private const MISSING_DATABASE = 'store_test_master_missing';

    private const TEST_DATABASES = [self::READY_DATABASE, self::LATE_DATABASE, self::MISSING_DATABASE];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropTestDatabases();
        $this->createDatabase(self::READY_DATABASE);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->dropTestDatabases();

        parent::tearDown();
    }

    private function createDatabase(string $name): void
    {
        DB::connection('mysql')->statement("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private function dropTestDatabases(): void
    {
        foreach (self::TEST_DATABASES as $name) {
            DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$name}`");
        }
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Cabang Barat',
            'code' => 'cabang_barat',
            'address' => 'Jl. Barat No. 1',
            'phone' => '08123456789',
            'receipt_prefix' => 'CBB',
            'db_name' => self::READY_DATABASE,
            'db_username' => config('database.connections.mysql.username'),
            'db_password' => config('database.connections.mysql.password') ?: null,
            'db_host' => null,
            ...$overrides,
        ];
    }

    public function test_index_lists_stores_by_name_without_database_credentials(): void
    {
        Store::factory()->create(['name' => 'Zeta Jaya', 'code' => 'zeta_jaya', 'db_name' => 'store_zeta']);
        $failed = Store::factory()->create([
            'name' => 'Alfa Mandiri', 'code' => 'alfa_mandiri', 'db_name' => 'store_alfa', 'status' => StoreStatus::Failed,
        ]);
        StoreProvisioningLog::query()->create([
            'store_id' => $failed->id, 'step' => 'connection', 'result' => 'failed', 'message' => 'Access denied',
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('central.stores.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Central/Stores/Index')
                ->has('stores', 2)
                ->where('stores.0.name', 'Alfa Mandiri')
                ->where('stores.0.status', ['value' => 'failed', 'label' => 'Gagal'])
                ->where('stores.0.can_retry', true)
                ->where('stores.0.can_toggle_active', false)
                ->where('stores.0.failure_message', 'Access denied')
                ->where('stores.1.name', 'Zeta Jaya')
                ->where('stores.1.can_toggle_active', true)
                ->where('stores.1.failure_message', null)
                ->missing('stores.0.db_username')
                ->missing('stores.0.db_password')
            );
    }

    public function test_create_form_renders(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('central.stores.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Central/Stores/Form')
                ->where('store', null)
                ->where('form_action.method', 'post')
            );
    }

    public function test_store_is_created_and_its_database_is_prepared(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('central.stores.store'), $this->payload())
            ->assertRedirect(route('central.stores.index'))
            ->assertSessionHas('success');

        $store = Store::query()->where('code', 'cabang_barat')->firstOrFail();

        $this->assertSame(StoreStatus::Active, $store->status);
        $this->assertSame('CBB', $store->receipt_prefix);
        $this->assertNotNull($store->schema_version);
        $this->assertSame(
            ['connection' => 'ok', 'migration' => 'ok', 'seed' => 'ok'],
            $store->provisioningLogs()->orderBy('id')->pluck('result', 'step')->map->value->all(),
        );
    }

    public function test_provisioning_creates_the_r1_tables_and_the_two_default_warehouses_once(): void
    {
        $this->actingAs($this->superAdmin())->post(route('central.stores.store'), $this->payload());

        $store = Store::query()->where('code', 'cabang_barat')->firstOrFail();

        $tables = DB::connection('mysql')->table('information_schema.tables')
            ->where('table_schema', self::READY_DATABASE)
            ->select('table_name as name')
            ->pluck('name')
            ->map(fn (string $name): string => strtolower($name))
            ->all();

        $this->assertEqualsCanonicalizing([], array_diff([
            'audits', 'units', 'categories', 'brands', 'products', 'product_units',
            'warehouses', 'stocks', 'stock_movements', 'stock_opnames', 'stock_opname_items',
            'registers', 'cash_shifts', 'sales', 'sale_items', 'sale_payments', 'sale_cancellations',
            'sale_returns', 'sale_return_items', 'delivery_notes', 'delivery_note_items',
            'expense_categories', 'expenses', 'daily_closings', 'settings', 'document_sequences',
        ], $tables));

        $store->forceFill(['status' => StoreStatus::Failed])->save();
        app(StoreProvisioner::class)->provision($store);

        $warehouses = $store->run(fn () => Warehouse::query()->orderBy('code')->get(['code', 'type', 'is_default']));

        $this->assertSame(['DEAD', 'MAIN', 'RETUR'], $warehouses->pluck('code')->all());
        $this->assertSame(WarehouseType::DeadStock, $warehouses[0]->type);
        $this->assertSame(WarehouseType::Sellable, $warehouses[1]->type);
        $this->assertTrue($warehouses[1]->is_default);
        $this->assertSame(WarehouseType::Returns, $warehouses[2]->type);
    }

    public function test_code_and_prefix_are_normalized(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('central.stores.store'), $this->payload(['code' => 'Cabang_Barat', 'receipt_prefix' => 'cbb']));

        $store = Store::query()->where('code', 'cabang_barat')->firstOrFail();

        $this->assertSame('CBB', $store->receipt_prefix);
    }

    public function test_store_whose_database_is_unreachable_is_kept_as_failed(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('central.stores.store'), $this->payload(['db_name' => self::MISSING_DATABASE]))
            ->assertRedirect(route('central.stores.index'))
            ->assertSessionHas('error');

        $this->assertStringContainsString('Database belum dibuat di hPanel.', session('error'));

        $store = Store::query()->where('code', 'cabang_barat')->firstOrFail();

        $this->assertSame(StoreStatus::Failed, $store->status);
        $this->assertNull($store->schema_version);
        $this->assertDatabaseHas('store_provisioning_logs', [
            'store_id' => $store->id, 'step' => 'connection', 'result' => 'failed',
        ]);
        $this->assertDatabaseMissing('store_provisioning_logs', ['store_id' => $store->id, 'step' => 'migration']);
    }

    public function test_failed_store_can_be_retried_once_the_database_exists(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('central.stores.store'), $this->payload(['db_name' => self::MISSING_DATABASE]));
        $store = Store::query()->where('code', 'cabang_barat')->firstOrFail();

        $this->createDatabase(self::MISSING_DATABASE);

        $this->actingAs($admin)
            ->post(route('central.stores.provision', $store))
            ->assertRedirect()
            ->assertSessionHas('success');

        $store->refresh();

        $this->assertSame(StoreStatus::Active, $store->status);
        $this->assertNotNull($store->schema_version);
    }

    public function test_retry_that_fails_again_reports_the_error(): void
    {
        $store = Store::factory()->create(['db_name' => self::MISSING_DATABASE, 'status' => StoreStatus::Failed]);

        $this->actingAs($this->superAdmin())
            ->post(route('central.stores.provision', $store))
            ->assertSessionHas('error');

        $this->assertSame(StoreStatus::Failed, $store->fresh()->status);
    }

    public function test_active_store_cannot_be_provisioned_again(): void
    {
        $store = Store::factory()->create();

        $this->actingAs($this->superAdmin())
            ->post(route('central.stores.provision', $store))
            ->assertSessionHas('error', 'Database toko ini sudah siap.');

        $this->assertSame(StoreStatus::Active, $store->fresh()->status);
    }

    public function test_a_failing_migration_leaves_the_store_failed_with_a_log_instead_of_an_error_page(): void
    {
        $this->createDatabase(self::LATE_DATABASE);
        DB::connection('mysql')->statement('CREATE TABLE `'.self::LATE_DATABASE.'`.`units` (id INT)');

        $this->actingAs($this->superAdmin())
            ->post(route('central.stores.store'), $this->payload(['db_name' => self::LATE_DATABASE]))
            ->assertRedirect(route('central.stores.index'))
            ->assertSessionHas('error');

        $store = Store::query()->where('code', 'cabang_barat')->firstOrFail();

        $this->assertFalse(tenancy()->initialized);
        $this->assertSame(StoreStatus::Failed, $store->status);
        $this->assertSame(
            ['connection' => 'ok', 'migration' => 'failed'],
            $store->provisioningLogs()->orderBy('id')->pluck('result', 'step')->map->value->all(),
        );
    }

    public function test_a_second_retry_while_setup_is_running_is_refused(): void
    {
        $store = Store::factory()->create(['db_name' => self::MISSING_DATABASE, 'status' => StoreStatus::Failed]);
        $runningSetup = Cache::lock('store-provisioning:'.$store->id, 60);
        $runningSetup->get();

        $this->actingAs($this->superAdmin())
            ->post(route('central.stores.provision', $store))
            ->assertSessionHas('error', 'Persiapan database toko ini sedang berjalan. Tunggu sampai selesai.');

        $runningSetup->release();

        $this->assertSame(StoreStatus::Failed, $store->fresh()->status);
        $this->assertSame(0, $store->provisioningLogs()->count());
    }

    public function test_schema_version_follows_every_tenant_migration(): void
    {
        $this->actingAs($this->superAdmin())->post(route('central.stores.store'), $this->payload());
        $store = Store::query()->where('code', 'cabang_barat')->firstOrFail();
        $store->forceFill(['schema_version' => 'stale'])->save();

        Artisan::call('tenants:migrate', ['--tenants' => [$store->code]]);

        $this->assertNotSame('stale', $store->fresh()->schema_version);
        $this->assertNotNull($store->fresh()->schema_version);
    }

    public function test_the_database_password_is_left_out_of_a_serialized_store(): void
    {
        $store = Store::factory()->create(['db_password' => 'rahasia-toko']);

        $this->assertArrayNotHasKey('db_password', $store->toArray());
        $this->assertSame('rahasia-toko', $store->db_password);
    }

    public function test_central_database_can_never_be_used_for_a_store(): void
    {
        $centralDatabase = config('database.connections.mysql.database');

        $this->actingAs($this->superAdmin())
            ->post(route('central.stores.store'), $this->payload(['db_name' => $centralDatabase]))
            ->assertSessionHasErrors(['db_name' => 'Database pusat tidak boleh dipakai sebagai database toko.']);

        $store = Store::factory()->create(['db_name' => $centralDatabase, 'status' => StoreStatus::Provisioning]);

        $this->expectException(StoreProvisioningException::class);

        try {
            app(StoreProvisioner::class)->provision($store);
        } finally {
            $this->assertSame(StoreStatus::Failed, $store->fresh()->status);
        }
    }

    public function test_code_must_be_well_formed_and_unique(): void
    {
        Store::factory()->create(['code' => 'cabang_barat', 'db_name' => 'store_other']);
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('central.stores.store'), $this->payload())
            ->assertSessionHasErrors(['code' => 'Kode toko sudah dipakai.']);

        foreach (['ab', 'Cabang Barat', '1cabang', 'cabang-barat', str_repeat('a', 41)] as $badCode) {
            $this->actingAs($admin)
                ->post(route('central.stores.store'), $this->payload(['code' => $badCode]))
                ->assertSessionHasErrors('code');
        }
    }

    public function test_database_name_must_be_unique_and_all_required_fields_are_checked(): void
    {
        Store::factory()->create(['db_name' => self::READY_DATABASE]);
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('central.stores.store'), $this->payload())
            ->assertSessionHasErrors(['db_name' => 'Database ini sudah dipakai toko lain.']);

        $this->actingAs($admin)
            ->post(route('central.stores.store'), [])
            ->assertSessionHasErrors(['name', 'code', 'receipt_prefix', 'db_name', 'db_username']);

        $this->actingAs($admin)
            ->post(route('central.stores.store'), $this->payload(['receipt_prefix' => 'A', 'code' => 'toko_baru', 'db_name' => 'x']))
            ->assertSessionHasErrors('receipt_prefix');
    }

    public function test_edit_form_hides_the_database_password_and_locks_the_database_name_of_an_active_store(): void
    {
        $store = Store::factory()->create(['db_password' => 'rahasia-hpanel']);

        $this->actingAs($this->superAdmin())
            ->get(route('central.stores.edit', $store))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Central/Stores/Form')
                ->where('store.code', $store->code)
                ->where('store.has_db_password', true)
                ->where('store.is_database_name_editable', false)
                ->where('form_action.method', 'put')
                ->missing('store.db_password')
            );
    }

    public function test_active_store_info_can_change_but_code_and_database_name_cannot(): void
    {
        $store = Store::factory()->create(['code' => 'mitra_utama', 'db_name' => self::READY_DATABASE]);

        $this->actingAs($this->superAdmin())
            ->put(route('central.stores.update', $store), $this->payload([
                'name' => 'Mitra Utama Baru',
                'code' => 'diganti',
                'receipt_prefix' => 'mub',
                'db_name' => 'store_dibelokkan',
            ]))
            ->assertRedirect(route('central.stores.index'));

        $store->refresh();

        $this->assertSame('Mitra Utama Baru', $store->name);
        $this->assertSame('MUB', $store->receipt_prefix);
        $this->assertSame('mitra_utama', $store->code);
        $this->assertSame(self::READY_DATABASE, $store->db_name);
    }

    public function test_active_store_database_user_and_host_can_be_changed_when_they_connect(): void
    {
        $store = Store::factory()->create(['db_name' => self::READY_DATABASE, 'db_username' => 'u1_lama']);

        $this->actingAs($this->superAdmin())->put(route('central.stores.update', $store), $this->payload([
            'db_host' => '127.0.0.1',
        ]))->assertRedirect(route('central.stores.index'));

        $store->refresh();
        $this->assertSame(config('database.connections.mysql.username'), $store->db_username);
        $this->assertSame('127.0.0.1', $store->db_host);
        $this->assertSame(StoreStatus::Active, $store->status);
    }

    public function test_active_store_credentials_that_cannot_connect_are_not_saved(): void
    {
        $store = Store::factory()->create(['db_name' => self::READY_DATABASE, 'db_password' => null]);

        $this->actingAs($this->superAdmin())
            ->from(route('central.stores.edit', $store))
            ->put(route('central.stores.update', $store), $this->payload(['name' => 'Nama Baru', 'db_password' => 'sandi-salah']))
            ->assertRedirect(route('central.stores.edit', $store))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Toko tidak disimpan') && str_contains($message, 'Username atau password database salah.'));

        $store->refresh();
        $this->assertNotSame('Nama Baru', $store->name);
        $this->assertNotSame('sandi-salah', $store->db_password);
    }

    public function test_the_connection_is_not_tested_when_only_store_info_changes(): void
    {
        $store = Store::factory()->create(['db_name' => self::READY_DATABASE, 'db_username' => 'u1_putus', 'db_password' => 'sandi-putus']);

        $this->actingAs($this->superAdmin())->put(route('central.stores.update', $store), $this->payload([
            'name' => 'Nama Baru', 'db_username' => 'u1_putus', 'db_password' => '',
        ]))->assertRedirect(route('central.stores.index'));

        $store->refresh();
        $this->assertSame('Nama Baru', $store->name);
        $this->assertSame('sandi-putus', $store->db_password);
    }

    public function test_failed_store_credentials_can_be_fixed_and_a_blank_password_is_kept(): void
    {
        $store = Store::factory()->create([
            'db_name' => self::MISSING_DATABASE, 'db_password' => 'lama-lama', 'status' => StoreStatus::Failed,
        ]);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put(route('central.stores.update', $store), $this->payload([
            'db_name' => self::LATE_DATABASE, 'db_username' => 'u1_baru', 'db_password' => '',
        ]));

        $store->refresh();
        $this->assertSame(self::LATE_DATABASE, $store->db_name);
        $this->assertSame('u1_baru', $store->db_username);
        $this->assertSame('lama-lama', $store->db_password);

        $this->actingAs($admin)->put(route('central.stores.update', $store), $this->payload([
            'db_name' => self::LATE_DATABASE, 'db_username' => 'u1_baru', 'db_password' => 'baru-baru',
        ]));

        $store->refresh();
        $this->assertSame('baru-baru', $store->db_password);
        $this->assertNotSame('baru-baru', DB::table('stores')->where('id', $store->id)->value('db_password'));
    }

    public function test_store_can_be_deactivated_and_reactivated(): void
    {
        $store = Store::factory()->create();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->patch(route('central.stores.status', $store), ['is_active' => false]);
        $this->assertSame(StoreStatus::Inactive, $store->fresh()->status);

        $this->actingAs($admin)->patch(route('central.stores.status', $store), ['is_active' => true]);
        $this->assertSame(StoreStatus::Active, $store->fresh()->status);
    }

    public function test_store_that_is_not_set_up_cannot_be_switched_on(): void
    {
        $admin = $this->superAdmin();

        foreach ([StoreStatus::Failed, StoreStatus::Provisioning] as $status) {
            $store = Store::factory()->create(['status' => $status]);

            $this->actingAs($admin)
                ->patch(route('central.stores.status', $store), ['is_active' => true])
                ->assertSessionHas('error', 'Toko ini belum selesai disiapkan.');

            $this->assertSame($status, $store->fresh()->status);
        }
    }

    public function test_switching_a_store_needs_an_active_flag(): void
    {
        $store = Store::factory()->create();

        $this->actingAs($this->superAdmin())
            ->patch(route('central.stores.status', $store), [])
            ->assertSessionHasErrors('is_active');

        $this->assertSame(StoreStatus::Active, $store->fresh()->status);
    }

    public function test_deactivated_store_cannot_be_entered(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create();
        $store->users()->attach($user->id, ['role_id' => Role::idOf(UserRole::StoreAdmin)]);

        $this->actingAs($this->superAdmin())->patch(route('central.stores.status', $store), ['is_active' => false]);

        $this->actingAs($user)->get(route('tenant.dashboard', ['tenant' => $store->code]))->assertForbidden();
    }

    public function test_stores_have_no_delete_route(): void
    {
        $store = Store::factory()->create();

        $this->actingAs($this->superAdmin())
            ->delete('/pusat/stores/'.$store->id)
            ->assertStatus(405);

        $this->assertModelExists($store);
    }
}
