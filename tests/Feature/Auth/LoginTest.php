<?php

namespace Tests\Feature\Auth;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-password';

    private function userWithStore(StoreStatus $storeStatus = StoreStatus::Active): array
    {
        $store = Store::factory()->create(['status' => $storeStatus]);
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $store->users()->attach($user->id, ['role_id' => Role::idOf(UserRole::StoreAdmin)]);

        return [$user, $store];
    }

    private function attemptLogin(User $user, string $password = self::PASSWORD)
    {
        return $this->post(route('login'), ['username' => $user->username, 'password' => $password]);
    }

    public function test_login_page_renders_for_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_signed_in_user_is_sent_away_from_the_login_page(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }

    public function test_super_admin_lands_on_the_central_dashboard(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'password' => self::PASSWORD]);

        $this->attemptLogin($admin)->assertRedirect(route('central.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_store_user_lands_in_their_store(): void
    {
        [$user, $store] = $this->userWithStore();

        $this->attemptLogin($user)->assertRedirect(route('tenant.dashboard', ['tenant' => $store->code]));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        [$user] = $this->userWithStore();

        $this->attemptLogin($user, 'wrong-password')->assertSessionHasErrors(['username' => 'Username atau password salah.']);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'is_active' => false, 'password' => self::PASSWORD]);

        $this->attemptLogin($admin)->assertSessionHasErrors(['username' => 'Username atau password salah.']);

        $this->assertGuest();
    }

    public function test_user_without_a_store_cannot_sign_in(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->attemptLogin($user)->assertSessionHasErrors(['username' => 'Akun ini belum punya akses ke toko mana pun.']);

        $this->assertGuest();
    }

    public function test_user_whose_only_store_is_not_active_cannot_sign_in(): void
    {
        [$user] = $this->userWithStore(StoreStatus::Inactive);

        $this->attemptLogin($user)->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_username_and_password_are_required(): void
    {
        $this->post(route('login'), [])->assertSessionHasErrors(['username', 'password']);
    }

    public function test_repeated_failures_are_throttled(): void
    {
        [$user] = $this->userWithStore();
        RateLimiter::clear(strtolower($user->username).'|127.0.0.1');

        foreach (range(1, 5) as $ignored) {
            $this->attemptLogin($user, 'wrong-password');
        }

        $this->attemptLogin($user)->assertSessionHasErrors('username');

        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            session('errors')->first('username'),
        );
        $this->assertGuest();
    }

    public function test_failures_from_one_address_are_throttled_across_different_usernames(): void
    {
        [$user] = $this->userWithStore();
        RateLimiter::clear('login-ip|127.0.0.1');

        foreach (range(1, 20) as $attempt) {
            $this->post(route('login'), ['username' => "guess{$attempt}", 'password' => 'wrong-password']);
        }

        $this->attemptLogin($user)->assertSessionHasErrors('username');

        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('username'));
        $this->assertGuest();
    }

    public function test_user_can_sign_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
