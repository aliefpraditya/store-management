<?php

namespace Tests\Feature\Central;

use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\AppSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('central.settings.index'))->assertRedirect(route('login'));
    }

    public function test_store_roles_cannot_see_or_change_settings(): void
    {
        $store = Store::factory()->create();

        foreach ([UserRole::StoreAdmin, UserRole::Cashier] as $role) {
            $user = User::factory()->create();
            $user->stores()->attach($store->id, ['role_id' => Role::idOf($role)]);

            $this->actingAs($user)->get(route('central.settings.index'))->assertForbidden();
            $this->actingAs($user)
                ->put(route('central.settings.update'), ['password_min_length' => 8])
                ->assertForbidden();
        }

        $this->assertSame(0, AppSetting::query()->count());
    }

    public function test_the_password_minimum_is_five_until_it_is_changed(): void
    {
        $this->assertSame(5, app(AppSettingService::class)->passwordMinLength());

        $this->actingAs($this->superAdmin())
            ->get(route('central.settings.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Central/Settings/Index')
                ->where('settings.password_min_length', 5)
                ->where('limits.password_min_length.lowest', 5)
                ->where('limits.password_min_length.highest', 64));
    }

    public function test_super_admin_changes_the_password_minimum_and_it_is_audited(): void
    {
        config(['audit.console' => true]);

        $this->actingAs($this->superAdmin())
            ->put(route('central.settings.update'), ['password_min_length' => 8])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(8, app(AppSettingService::class)->passwordMinLength());

        $this->actingAs($this->superAdmin())
            ->put(route('central.settings.update'), ['password_min_length' => 10]);

        $this->assertSame(1, AppSetting::query()->count());
        $this->assertSame(10, app(AppSettingService::class)->passwordMinLength());
        $this->assertSame(2, AppSetting::query()->firstOrFail()->audits()->count());
    }

    public function test_the_password_minimum_must_be_a_whole_number_within_the_allowed_range(): void
    {
        $admin = $this->superAdmin();

        foreach (['', 'abc', 4, 65, 6.5] as $invalidLength) {
            $this->actingAs($admin)
                ->put(route('central.settings.update'), ['password_min_length' => $invalidLength])
                ->assertSessionHasErrors('password_min_length');
        }

        $this->assertSame(5, app(AppSettingService::class)->passwordMinLength());
    }

    public function test_new_users_need_the_password_length_the_setting_asks_for(): void
    {
        $store = Store::factory()->create();
        app(AppSettingService::class)->setPasswordMinLength(8);

        $newUser = [
            'name' => 'Sari Kasir',
            'username' => 'sari',
            'role' => UserRole::Cashier->value,
            'store_id' => $store->id,
            'is_active' => true,
        ];

        $this->actingAs($this->superAdmin())
            ->post(route('central.users.store'), [...$newUser, 'password' => 'tujuhhh'])
            ->assertSessionHasErrors(['password' => 'Password minimal 8 karakter.']);

        $this->actingAs($this->superAdmin())
            ->post(route('central.users.store'), [...$newUser, 'password' => 'delapan8'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'sari']);
    }
}
