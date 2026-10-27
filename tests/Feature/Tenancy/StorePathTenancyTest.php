<?php

namespace Tests\Feature\Tenancy;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorePathTenancyTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_DATABASE = 'store_test_alpha';

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('mysql')->statement('CREATE DATABASE IF NOT EXISTS `'.self::TEST_DATABASE.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        DB::connection('mysql')->statement('DROP DATABASE IF EXISTS `'.self::TEST_DATABASE.'`');

        parent::tearDown();
    }

    public function test_store_code_in_url_switches_to_that_stores_database(): void
    {
        Store::factory()->create([
            'code' => 'test_alpha',
            'db_name' => self::TEST_DATABASE,
        ]);

        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->getJson('/t/test_alpha/ping')
            ->assertOk()
            ->assertJson([
                'store' => 'test_alpha',
                'database' => self::TEST_DATABASE,
            ]);
    }

    public function test_session_cache_and_queue_stay_on_the_central_connection(): void
    {
        $central = config('tenancy.database.central_connection');

        $this->assertSame($central, config('session.connection'));
        $this->assertSame($central, config('cache.stores.database.connection'));
        $this->assertSame($central, config('queue.connections.database.connection'));
    }

    public function test_database_password_is_stored_encrypted(): void
    {
        $store = Store::factory()->create(['db_password' => 'rahasia-hpanel']);

        $this->assertNotSame('rahasia-hpanel', DB::table('stores')->where('id', $store->id)->value('db_password'));
        $this->assertSame('rahasia-hpanel', $store->fresh()->db_password);
    }
}
