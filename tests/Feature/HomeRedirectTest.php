<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\LandingRouteResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_super_admin_is_sent_to_the_central_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->get(route('home'))
            ->assertRedirect(route('central.dashboard'));
    }

    public function test_user_without_a_store_is_signed_out_and_sent_to_the_login_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username' => LandingRouteResolver::NO_ACCESS_MESSAGE]);

        $this->assertGuest();
    }

    public function test_user_whose_account_was_deactivated_mid_session_can_reach_the_login_page(): void
    {
        $deactivatedAdmin = User::factory()->create(['is_super_admin' => true, 'is_active' => false]);

        $this->actingAs($deactivatedAdmin)->get(route('home'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('login'))->assertOk();
    }

    public function test_user_whose_only_store_was_deactivated_mid_session_is_signed_out(): void
    {
        $store = Store::factory()->create(['status' => StoreStatus::Inactive]);
        $cashier = User::factory()->create();
        $store->users()->attach($cashier->id, ['role_id' => Role::idOf(UserRole::Cashier)]);

        $this->actingAs($cashier)->get(route('home'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
