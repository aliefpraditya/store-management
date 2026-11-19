<?php

namespace Tests\Feature\Central;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(array $attributes = []): User
    {
        return User::factory()->create(['is_super_admin' => true, ...$attributes]);
    }

    private function storeUser(UserRole $role, ?Store $store = null, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->stores()->attach(($store ?? Store::factory()->create())->id, ['role_id' => Role::idOf($role)]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Store $store, array $overrides = []): array
    {
        return [
            'name' => 'Sari Kasir',
            'username' => 'sari',
            'password' => 'rahasia',
            'role' => UserRole::Cashier->value,
            'store_id' => $store->id,
            'is_active' => true,
            ...$overrides,
        ];
    }

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('central.users.index'))->assertRedirect(route('login'));
    }

    public function test_store_roles_cannot_manage_users(): void
    {
        $store = Store::factory()->create();

        foreach ([UserRole::StoreAdmin, UserRole::Cashier] as $role) {
            $this->actingAs($this->storeUser($role, $store))
                ->get(route('central.users.index'))
                ->assertForbidden();
        }

        $this->actingAs($this->storeUser(UserRole::StoreAdmin, $store))
            ->post(route('central.users.store'), $this->payload($store))
            ->assertForbidden();
    }

    public function test_index_lists_super_admins_first_with_role_and_store(): void
    {
        $store = Store::factory()->create(['name' => 'Mitra Utama']);
        $this->storeUser(UserRole::Cashier, $store, ['name' => 'Amy', 'username' => 'amy']);
        $admin = $this->superAdmin(['name' => 'Zed', 'username' => 'zed']);

        $this->actingAs($admin)
            ->get(route('central.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Central/Users/Index')
                ->has('users', 2)
                ->where('users.0.username', 'zed')
                ->where('users.0.role', ['value' => 'super_admin', 'label' => 'Super Admin', 'is_store_role' => false])
                ->where('users.0.store', null)
                ->where('users.0.is_self', true)
                ->where('users.1.username', 'amy')
                ->where('users.1.role', ['value' => 'cashier', 'label' => 'Kasir', 'is_store_role' => true])
                ->where('users.1.store.name', 'Mitra Utama')
                ->where('users.1.is_self', false)
                ->missing('users.0.password')
                ->missing('users.0.email')
            );
    }

    public function test_form_offers_every_role_and_all_stores(): void
    {
        Store::factory()->create(['name' => 'Mitra Utama']);

        $this->actingAs($this->superAdmin())
            ->get(route('central.users.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Central/Users/Form')
                ->where('user', null)
                ->where('roles.0.value', 'super_admin')
                ->where('roles.1.value', 'admin')
                ->where('roles.2.value', 'cashier')
                ->where('roles.3.value', 'warehouse')
                ->has('roles', 4)
                ->has('stores', 1)
            );
    }

    public function test_cashier_is_created_in_exactly_one_store(): void
    {
        $store = Store::factory()->create();

        $this->actingAs($this->superAdmin())
            ->post(route('central.users.store'), $this->payload($store))
            ->assertRedirect(route('central.users.index'))
            ->assertSessionHas('success');

        $user = User::query()->where('username', 'sari')->firstOrFail();

        $this->assertFalse($user->is_super_admin);
        $this->assertTrue(Hash::check('rahasia', $user->password));
        $this->assertSame(UserRole::Cashier->value, $user->role?->name);
        $this->assertSame($store->id, $user->assignedStore()->id);
    }

    public function test_store_admin_is_created_with_the_admin_role(): void
    {
        $store = Store::factory()->create();

        $this->actingAs($this->superAdmin())
            ->post(route('central.users.store'), $this->payload($store, ['role' => 'admin', 'username' => 'andi']));

        $this->assertSame(UserRole::StoreAdmin->value, User::query()->where('username', 'andi')->firstOrFail()->role?->name);
    }

    public function test_super_admin_is_created_without_a_store(): void
    {
        $store = Store::factory()->create();

        $this->actingAs($this->superAdmin())
            ->post(route('central.users.store'), $this->payload($store, ['role' => 'super_admin', 'username' => 'bos']));

        $user = User::query()->where('username', 'bos')->firstOrFail();

        $this->assertTrue($user->is_super_admin);
        $this->assertSame(UserRole::SuperAdmin->value, $user->role?->name);
        $this->assertNull($user->assignedStore());
    }

    public function test_store_is_required_for_store_roles(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('central.users.store'), [...$this->payload(Store::factory()->create()), 'store_id' => null])
            ->assertSessionHasErrors(['store_id' => 'Pilih toko.']);

        $this->assertDatabaseMissing('users', ['username' => 'sari']);
    }

    public function test_unknown_role_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('central.users.store'), $this->payload(Store::factory()->create(), ['role' => 'accountant']))
            ->assertSessionHasErrors('role');
    }

    public function test_username_must_be_unique_and_well_formed(): void
    {
        $store = Store::factory()->create();
        User::factory()->create(['username' => 'sari']);
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('central.users.store'), $this->payload($store))
            ->assertSessionHasErrors(['username' => 'Username sudah dipakai.']);

        $this->actingAs($admin)
            ->post(route('central.users.store'), $this->payload($store, ['username' => 'Sari Kasir!']))
            ->assertSessionHasErrors('username');
    }

    public function test_password_is_required_and_has_a_minimum_length_on_create(): void
    {
        $store = Store::factory()->create();
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('central.users.store'), $this->payload($store, ['password' => '']))
            ->assertSessionHasErrors('password');

        $this->actingAs($admin)
            ->post(route('central.users.store'), $this->payload($store, ['password' => 'abcd']))
            ->assertSessionHasErrors('password');
    }

    public function test_edit_form_is_filled_with_the_user(): void
    {
        $store = Store::factory()->create();
        $cashier = $this->storeUser(UserRole::Cashier, $store, ['username' => 'sari']);

        $this->actingAs($this->superAdmin())
            ->get(route('central.users.edit', $cashier))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Central/Users/Form')
                ->where('user.username', 'sari')
                ->where('user.role.value', 'cashier')
                ->where('user.store.id', $store->id)
                ->where('form_action.method', 'put')
            );
    }

    public function test_role_and_store_can_be_changed(): void
    {
        $oldStore = Store::factory()->create();
        $newStore = Store::factory()->create();
        $user = $this->storeUser(UserRole::Cashier, $oldStore, ['username' => 'sari']);

        $this->actingAs($this->superAdmin())
            ->put(route('central.users.update', $user), $this->payload($newStore, ['role' => 'admin', 'password' => '']))
            ->assertRedirect(route('central.users.index'));

        $user = $user->fresh();

        $this->assertSame(UserRole::StoreAdmin->value, $user->role?->name);
        $this->assertSame([$newStore->id], $user->stores()->pluck('stores.id')->all());
    }

    public function test_blank_password_keeps_the_current_one_and_a_new_one_replaces_it(): void
    {
        $store = Store::factory()->create();
        $user = $this->storeUser(UserRole::Cashier, $store, ['username' => 'sari', 'password' => 'lama-lama']);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put(route('central.users.update', $user), $this->payload($store, ['password' => '']));
        $this->assertTrue(Hash::check('lama-lama', $user->fresh()->password));

        $this->actingAs($admin)->put(route('central.users.update', $user), $this->payload($store, ['password' => 'baru-baru']));
        $this->assertTrue(Hash::check('baru-baru', $user->fresh()->password));
    }

    public function test_promoting_to_super_admin_removes_the_store_assignment(): void
    {
        $store = Store::factory()->create();
        $user = $this->storeUser(UserRole::StoreAdmin, $store, ['username' => 'sari']);

        $this->actingAs($this->superAdmin())
            ->put(route('central.users.update', $user), $this->payload($store, ['role' => 'super_admin', 'store_id' => null, 'password' => '']));

        $user = $user->fresh();

        $this->assertTrue($user->is_super_admin);
        $this->assertSame(0, $user->stores()->count());
    }

    public function test_demoting_a_super_admin_needs_a_store(): void
    {
        $other = $this->superAdmin(['username' => 'bos']);

        $this->actingAs($this->superAdmin())
            ->put(route('central.users.update', $other), $this->payload(Store::factory()->create(), ['username' => 'bos', 'store_id' => null, 'password' => '']))
            ->assertSessionHasErrors('store_id');

        $this->assertTrue($other->fresh()->is_super_admin);
    }

    public function test_user_keeps_their_own_username_when_editing(): void
    {
        $store = Store::factory()->create();
        $user = $this->storeUser(UserRole::Cashier, $store, ['username' => 'sari']);

        $this->actingAs($this->superAdmin())
            ->put(route('central.users.update', $user), $this->payload($store, ['name' => 'Sari Baru', 'password' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Sari Baru', $user->fresh()->name);
    }

    public function test_super_admin_cannot_change_their_own_role_or_deactivate_themself(): void
    {
        $admin = $this->superAdmin(['username' => 'bos']);
        $store = Store::factory()->create();

        $this->actingAs($admin)
            ->put(route('central.users.update', $admin), $this->payload($store, ['username' => 'bos', 'role' => 'admin', 'password' => '']))
            ->assertSessionHasErrors(['role' => 'Anda tidak bisa mengubah peran akun sendiri.']);

        $this->actingAs($admin)
            ->put(route('central.users.update', $admin), $this->payload($store, ['username' => 'bos', 'role' => 'super_admin', 'store_id' => null, 'password' => '', 'is_active' => false]))
            ->assertSessionHasErrors(['is_active' => 'Anda tidak bisa menonaktifkan akun sendiri.']);

        $this->assertTrue($admin->fresh()->is_super_admin);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_user_can_be_deactivated_and_reactivated(): void
    {
        $user = $this->storeUser(UserRole::Cashier);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->patch(route('central.users.status', $user), ['is_active' => false]);
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)->patch(route('central.users.status', $user), ['is_active' => true]);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_own_account_cannot_be_deactivated_from_the_list(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->patch(route('central.users.status', $admin), ['is_active' => false])
            ->assertSessionHasErrors(['is_active' => 'Anda tidak bisa menonaktifkan akun sendiri.']);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_changing_a_users_store_role_leaves_an_audit_record(): void
    {
        config(['audit.console' => true]);
        $store = Store::factory()->create();
        $cashier = $this->storeUser(UserRole::Cashier, $store, ['username' => 'sari']);

        $this->actingAs($this->superAdmin())
            ->put(route('central.users.update', $cashier), $this->payload($store, ['role' => 'admin', 'password' => '']));

        $roleAudit = $cashier->audits()->where('event', 'store_assignment_changed')->sole();

        $this->assertSame('cashier', $roleAudit->old_values['store_assignment']['role']);
        $this->assertSame('admin', $roleAudit->new_values['store_assignment']['role']);
    }

    public function test_saving_a_user_without_changing_the_store_role_adds_no_assignment_audit(): void
    {
        config(['audit.console' => true]);
        $store = Store::factory()->create();
        $cashier = $this->storeUser(UserRole::Cashier, $store, ['username' => 'sari']);

        $this->actingAs($this->superAdmin())
            ->put(route('central.users.update', $cashier), $this->payload($store, ['name' => 'Sari Baru', 'password' => '']));

        $this->assertSame(0, $cashier->audits()->where('event', 'store_assignment_changed')->count());
    }

    public function test_user_without_a_store_has_no_role(): void
    {
        $this->assertNull(User::factory()->create()->role);
    }
}
