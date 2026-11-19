<?php

namespace Tests\Feature\Central;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Permission as PermissionRow;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    private function roleNamed(UserRole $role): Role
    {
        return Role::query()->where('name', $role->value)->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function permissionNamesOf(Role $role): array
    {
        return $role->permissions()->orderBy('name')->pluck('name')->all();
    }

    public function test_permission_rows_match_the_permission_enum(): void
    {
        $this->assertEqualsCanonicalizing(Permission::values(), PermissionRow::query()->pluck('name')->all());
    }

    public function test_system_roles_ship_with_their_permissions(): void
    {
        $this->assertSame(['cashier.use'], $this->permissionNamesOf($this->roleNamed(UserRole::Cashier)));
        $this->assertSame(['delivery_notes.view', 'stock_opnames.count'], $this->permissionNamesOf($this->roleNamed(UserRole::WarehouseStaff)));
        $this->assertCount(count(Permission::cases()), $this->roleNamed(UserRole::StoreAdmin)->permissions);
        $this->assertSame('Petugas Gudang', $this->roleNamed(UserRole::WarehouseStaff)->label);
    }

    public function test_index_lists_roles_with_their_permission_and_user_counts(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create();
        $user->stores()->attach($store->id, ['role_id' => Role::idOf(UserRole::Cashier)]);

        $this->actingAs($this->superAdmin())
            ->get(route('central.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Central/Roles/Index')
                ->has('roles', 3)
                ->where('roles.1.label', 'Kasir')
                ->where('roles.1.is_system', true)
                ->where('roles.1.users_count', 1)
                ->where('roles.2.permission_names', fn ($names): bool => collect($names)->sort()->values()->all() === ['delivery_notes.view', 'stock_opnames.count'])
                ->where('permission_count', count(Permission::cases()))
            );
    }

    public function test_form_offers_every_permission_grouped(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('central.roles.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Central/Roles/Form')
                ->where('role', null)
                ->has('permission_groups', 5)
                ->where('permission_groups.0.group', 'Umum')
                ->where('permission_groups.0.permissions.0.name', 'dashboard.view')
            );
    }

    public function test_super_admin_creates_a_role_with_permissions(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('central.roles.store'), ['label' => 'Supervisor Gudang', 'permissions' => ['stock_opnames.count', 'stock_opnames.post']])
            ->assertRedirect(route('central.roles.index'))
            ->assertSessionHas('success');

        $role = Role::query()->where('label', 'Supervisor Gudang')->firstOrFail();

        $this->assertSame('supervisor_gudang', $role->name);
        $this->assertFalse($role->is_system);
        $this->assertSame(['stock_opnames.count', 'stock_opnames.post'], $this->permissionNamesOf($role));
    }

    public function test_label_must_be_new_and_not_the_reserved_super_admin(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('central.roles.store'), ['label' => 'Kasir', 'permissions' => []])
            ->assertSessionHasErrors(['label' => 'Nama peran sudah dipakai.']);

        $this->actingAs($admin)
            ->post(route('central.roles.store'), ['label' => 'Super Admin', 'permissions' => []])
            ->assertSessionHasErrors('label');
    }

    public function test_unknown_permission_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('central.roles.store'), ['label' => 'Aneh', 'permissions' => ['everything.do']])
            ->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('roles', ['label' => 'Aneh']);
    }

    public function test_label_made_into_an_existing_name_gets_a_number(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('central.roles.store'), ['label' => 'KASIR!', 'permissions' => []])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roles', ['label' => 'KASIR!', 'name' => 'kasir']);

        $this->actingAs($this->superAdmin())
            ->post(route('central.roles.store'), ['label' => 'kasir?', 'permissions' => []])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roles', ['label' => 'kasir?', 'name' => 'kasir_2']);
    }

    public function test_editing_replaces_the_permissions_and_renames_a_custom_role(): void
    {
        $role = Role::factory()->create(['label' => 'Lama', 'name' => 'lama']);
        $role->permissions()->attach(PermissionRow::query()->where('name', 'reports.view')->value('id'));

        $this->actingAs($this->superAdmin())
            ->put(route('central.roles.update', $role), ['label' => 'Baru', 'permissions' => ['expenses.manage']])
            ->assertRedirect(route('central.roles.index'));

        $role->refresh();
        $this->assertSame('Baru', $role->label);
        $this->assertSame('lama', $role->name);
        $this->assertSame(['expenses.manage'], $this->permissionNamesOf($role));
    }

    public function test_a_system_role_keeps_its_label_but_its_permissions_can_change(): void
    {
        $warehouse = $this->roleNamed(UserRole::WarehouseStaff);

        $this->actingAs($this->superAdmin())
            ->put(route('central.roles.update', $warehouse), ['label' => 'Diganti', 'permissions' => ['stock_opnames.count', 'stock_opnames.post', 'delivery_notes.view']])
            ->assertSessionHasNoErrors();

        $warehouse->refresh();
        $this->assertSame('Petugas Gudang', $warehouse->label);
        $this->assertSame(['delivery_notes.view', 'stock_opnames.count', 'stock_opnames.post'], $this->permissionNamesOf($warehouse));
    }

    public function test_changing_permissions_is_audited(): void
    {
        $role = Role::factory()->create();

        $this->actingAs($this->superAdmin())
            ->put(route('central.roles.update', $role), ['label' => $role->label, 'permissions' => ['reports.view']]);

        $this->assertTrue($role->audits()->where('event', 'sync')->exists());
    }

    public function test_an_unused_custom_role_can_be_deleted(): void
    {
        $role = Role::factory()->create();

        $this->actingAs($this->superAdmin())
            ->delete(route('central.roles.destroy', $role))
            ->assertRedirect(route('central.roles.index'))
            ->assertSessionHas('success');

        $this->assertModelMissing($role);
    }

    public function test_a_role_that_users_hold_cannot_be_deleted(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->create();
        $user->stores()->attach(Store::factory()->create()->id, ['role_id' => $role->id]);

        $this->actingAs($this->superAdmin())
            ->delete(route('central.roles.destroy', $role))
            ->assertSessionHas('error');

        $this->assertModelExists($role);
    }

    public function test_a_system_role_cannot_be_deleted(): void
    {
        $cashier = $this->roleNamed(UserRole::Cashier);

        $this->actingAs($this->superAdmin())
            ->delete(route('central.roles.destroy', $cashier))
            ->assertSessionHas('error');

        $this->assertModelExists($cashier);
    }

    public function test_a_user_can_be_given_a_custom_role_and_extra_permissions(): void
    {
        $store = Store::factory()->create();
        $role = Role::factory()->create(['label' => 'Supervisor']);

        $this->actingAs($this->superAdmin())
            ->post(route('central.users.store'), [
                'name' => 'Budi',
                'username' => 'budi',
                'password' => 'rahasia',
                'role' => $role->name,
                'store_id' => $store->id,
                'permissions' => ['reports.view'],
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $user = User::query()->where('username', 'budi')->firstOrFail();

        $this->assertSame($role->id, $user->role->id);
        $this->assertSame(['reports.view'], $user->permissions()->pluck('name')->all());
        $this->assertSame(['reports.view'], $user->permissionNamesIn($store));
    }

    public function test_extra_permissions_are_added_to_those_of_the_role(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create();
        $user->stores()->attach($store->id, ['role_id' => Role::idOf(UserRole::Cashier)]);
        $user->permissions()->attach(PermissionRow::query()->where('name', 'reports.view')->value('id'));

        $this->assertEqualsCanonicalizing(['cashier.use', 'reports.view'], $user->permissionNamesIn($store));
        $this->assertTrue($user->hasPermissionIn($store, Permission::ReportsView));
        $this->assertFalse($user->hasPermissionIn($store, Permission::SettingsManage));
    }

    public function test_a_super_admin_holds_every_permission_and_a_stranger_none(): void
    {
        $store = Store::factory()->create();

        $this->assertEqualsCanonicalizing(Permission::values(), $this->superAdmin()->permissionNamesIn($store));
        $this->assertSame([], User::factory()->create()->permissionNamesIn($store));
    }
}
