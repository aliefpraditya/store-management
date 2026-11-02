<?php

namespace Tests\Feature\Tenancy;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class StoreAccessTest extends TestCase
{
    use PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_access';

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function store(array $attributes = []): Store
    {
        $store = $this->prepareStoreDatabase(['code' => 'test_access']);

        $store->forceFill($attributes)->save();

        return $store;
    }

    public function test_guest_is_rejected(): void
    {
        $this->store();

        $this->getJson('/t/test_access/cashier')->assertUnauthorized();
    }

    public function test_user_attached_to_the_store_may_enter(): void
    {
        $store = $this->store();
        $user = User::factory()->create();
        $store->users()->attach($user->id, ['role' => 'cashier']);

        $this->actingAs($user)
            ->get('/t/test_access/cashier')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('store.code', 'test_access'));
    }

    public function test_super_admin_may_enter_any_store(): void
    {
        $this->store();

        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->get('/t/test_access/cashier')
            ->assertOk();
    }

    public function test_user_without_access_to_the_store_is_forbidden(): void
    {
        $this->store();

        $this->actingAs(User::factory()->create())
            ->get('/t/test_access/cashier')
            ->assertForbidden();
    }

    public function test_inactive_user_is_forbidden(): void
    {
        $store = $this->store();
        $user = User::factory()->create(['is_active' => false]);
        $store->users()->attach($user->id, ['role' => 'admin']);

        $this->actingAs($user)
            ->get('/t/test_access/cashier')
            ->assertForbidden();
    }

    public function test_store_that_is_not_active_is_forbidden(): void
    {
        $store = $this->store(['status' => 'provisioning']);
        $user = User::factory()->create();
        $store->users()->attach($user->id, ['role' => 'admin']);

        $this->actingAs($user)
            ->get('/t/test_access/cashier')
            ->assertForbidden();
    }

    public function test_old_api_path_is_gone(): void
    {
        $this->store();

        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->getJson('/api/t/test_access/cashier')
            ->assertNotFound();
    }
}
