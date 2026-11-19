<?php

namespace Tests\Feature\Central;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The central area (users, and later the store master) belongs to super admins only.
 */
class CentralAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_central_route_requires_a_signed_in_super_admin(): void
    {
        $centralRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'central.'));

        $this->assertNotEmpty($centralRoutes);

        foreach ($centralRoutes as $route) {
            $middleware = app('router')->gatherRouteMiddleware($route);

            $this->assertContains(Authenticate::class, $middleware, "{$route->getName()} must require login");
            $this->assertContains(EnsureUserIsSuperAdmin::class, $middleware, "{$route->getName()} must be super admin only");
        }
    }

    public function test_store_roles_are_forbidden_on_every_user_endpoint(): void
    {
        $store = Store::factory()->create();
        $target = User::factory()->create(['name' => 'Target', 'username' => 'target']);
        $target->stores()->attach($store->id, ['role_id' => Role::idOf(UserRole::Cashier)]);

        $payload = [
            'name' => 'Diubah',
            'username' => 'diubah',
            'password' => 'diubah-diubah',
            'role' => UserRole::StoreAdmin->value,
            'store_id' => $store->id,
            'is_active' => false,
        ];

        foreach ([UserRole::StoreAdmin, UserRole::Cashier] as $role) {
            $actor = User::factory()->create();
            $actor->stores()->attach($store->id, ['role_id' => Role::idOf($role)]);

            $this->actingAs($actor);

            $this->get(route('central.users.index'))->assertForbidden();
            $this->get(route('central.users.create'))->assertForbidden();
            $this->post(route('central.users.store'), $payload)->assertForbidden();
            $this->get(route('central.users.edit', $target))->assertForbidden();
            $this->put(route('central.users.update', $target), $payload)->assertForbidden();
            $this->patch(route('central.users.status', $target), ['is_active' => false])->assertForbidden();
        }

        $target->refresh();

        $this->assertSame('Target', $target->name);
        $this->assertSame('target', $target->username);
        $this->assertTrue($target->is_active);
        $this->assertSame(UserRole::Cashier->value, $target->role?->name);
        $this->assertFalse(Hash::check('diubah-diubah', $target->password));
        $this->assertDatabaseMissing('users', ['username' => 'diubah']);
    }

    public function test_guests_cannot_reach_any_user_endpoint(): void
    {
        $target = User::factory()->create();

        $this->get(route('central.users.edit', $target))->assertRedirect(route('login'));
        $this->put(route('central.users.update', $target), [])->assertRedirect(route('login'));
        $this->patch(route('central.users.status', $target), ['is_active' => false])->assertRedirect(route('login'));
        $this->post(route('central.users.store'), [])->assertRedirect(route('login'));
    }
}
