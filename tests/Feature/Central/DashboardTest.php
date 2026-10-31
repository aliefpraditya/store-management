<?php

namespace Tests\Feature\Central;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('central.dashboard'))->assertRedirect(route('login'));
    }

    public function test_store_user_cannot_open_the_central_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('central.dashboard'))
            ->assertForbidden();
    }

    public function test_inactive_super_admin_cannot_open_the_central_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => false]))
            ->get(route('central.dashboard'))
            ->assertForbidden();
    }

    public function test_super_admin_sees_every_store_ordered_by_name(): void
    {
        Store::factory()->create(['name' => 'Zeta Jaya', 'code' => 'zeta_jaya', 'db_name' => 'store_zeta_jaya']);
        Store::factory()->create([
            'name' => 'Alfa Mandiri',
            'code' => 'alfa_mandiri',
            'db_name' => 'store_alfa_mandiri',
            'status' => StoreStatus::Provisioning,
        ]);

        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->get(route('central.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Central/Dashboard')
                ->has('stores', 2)
                ->where('stores.0.name', 'Alfa Mandiri')
                ->where('stores.0.status', ['value' => 'provisioning', 'label' => 'Disiapkan'])
                ->where('stores.0.is_enterable', false)
                ->where('stores.1.name', 'Zeta Jaya')
                ->where('stores.1.is_enterable', true)
                ->where('stores.1.url', route('tenant.dashboard', ['tenant' => 'zeta_jaya']))
                ->missing('stores.0.db_name')
                ->missing('stores.0.db_password')
            );
    }
}
