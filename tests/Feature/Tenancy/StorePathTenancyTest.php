<?php

namespace Tests\Feature\Tenancy;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class StorePathTenancyTest extends TestCase
{
    use PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_alpha';

    public function test_store_code_in_url_switches_to_that_stores_database(): void
    {
        $this->prepareStoreDatabase(['code' => 'test_alpha']);

        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->get('/t/test_alpha/cashier')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('store.code', 'test_alpha'));

        // Tenancy stays initialized after the request, so the default connection is still that store's database.
        $this->assertSame(self::STORE_DATABASE, DB::connection()->getDatabaseName());
    }

    public function test_session_cache_and_queue_stay_on_the_central_connection(): void
    {
        $central = config('tenancy.database.central_connection');

        $this->assertSame($central, config('session.connection'));
        $this->assertSame($central, config('cache.stores.database.connection'));
        $this->assertSame($central, config('queue.connections.database.connection'));
    }

    public function test_each_store_connects_with_its_own_database_user_and_password(): void
    {
        $alpha = Store::factory()->create([
            'db_name' => 'u111_store_alpha', 'db_username' => 'u111_alpha', 'db_password' => 'sandi-alpha', 'db_host' => '127.0.0.1',
        ]);
        $beta = Store::factory()->create([
            'db_name' => 'u111_store_beta', 'db_username' => 'u111_beta', 'db_password' => 'sandi-beta', 'db_host' => '127.0.0.1',
        ]);

        $alphaConnection = $alpha->fresh()->database()->connection();
        $betaConnection = $beta->fresh()->database()->connection();

        $this->assertSame(['u111_store_alpha', 'u111_alpha', 'sandi-alpha'], [$alphaConnection['database'], $alphaConnection['username'], $alphaConnection['password']]);
        $this->assertSame(['u111_store_beta', 'u111_beta', 'sandi-beta'], [$betaConnection['database'], $betaConnection['username'], $betaConnection['password']]);
    }

    public function test_database_password_is_stored_encrypted(): void
    {
        $store = Store::factory()->create(['db_password' => 'rahasia-hpanel']);

        $this->assertNotSame('rahasia-hpanel', DB::table('stores')->where('id', $store->id)->value('db_password'));
        $this->assertSame('rahasia-hpanel', $store->fresh()->db_password);
    }
}
