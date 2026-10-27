<?php

namespace Tests\Feature\Tenancy;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_DATABASE = 'store_test_dashboard';

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

    public function test_guest_is_sent_to_the_login_page(): void
    {
        Store::factory()->create(['code' => 'test_dashboard', 'db_name' => self::TEST_DATABASE]);

        $this->get('/t/test_dashboard')->assertRedirect(route('login'));
    }

    public function test_store_user_sees_their_store_dashboard(): void
    {
        $store = Store::factory()->create([
            'code' => 'test_dashboard',
            'name' => 'Toko Uji',
            'db_name' => self::TEST_DATABASE,
        ]);
        $user = User::factory()->create();
        $store->users()->attach($user->id, ['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('tenant.dashboard', ['tenant' => 'test_dashboard']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Dashboard')
                ->where('store', ['code' => 'test_dashboard', 'name' => 'Toko Uji'])
            );
    }
}
