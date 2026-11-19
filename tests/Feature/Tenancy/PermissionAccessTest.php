<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Permission as PermissionRow;
use App\Models\Role;
use App\Models\StockOpname;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

/**
 * What each role may open in a store, and that a change to a role or a user's own permissions takes effect at once.
 */
class PermissionAccessTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_permissions';

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_permissions']);
    }

    private function url(string $route, array $parameters = []): string
    {
        return route($route, ['tenant' => $this->store->code, ...$parameters]);
    }

    private function grantDirectly(User $user, Permission $permission): void
    {
        $user->permissions()->attach(PermissionRow::query()->where('name', $permission->value)->value('id'));
    }

    private function warehouseStaff(): User
    {
        return $this->storeUser(UserRole::WarehouseStaff);
    }

    public function test_warehouse_staff_may_count_stock_and_open_delivery_notes(): void
    {
        $staff = $this->warehouseStaff();

        $this->actingAs($staff)->get($this->url('tenant.stock-opnames.index'))->assertOk();
        $this->actingAs($staff)->get($this->url('tenant.stock-opnames.create'))->assertOk();
        $this->actingAs($staff)->get($this->url('tenant.delivery-notes.index'))->assertOk();
        $this->actingAs($staff)->getJson($this->url('tenant.products.search', ['q' => 'a']))->assertOk();
    }

    public function test_warehouse_staff_may_not_post_an_opname_or_manage_delivery_notes(): void
    {
        $staff = $this->warehouseStaff();
        $opname = $this->store->run(fn () => StockOpname::factory()->create(['warehouse_id' => $this->mainWarehouse()->id]));

        $this->actingAs($staff)->post($this->url('tenant.stock-opnames.post', ['stock_opname' => $opname->id]))->assertForbidden();
        $this->actingAs($staff)->put($this->url('tenant.stock-opnames.review', ['stock_opname' => $opname->id]), ['rows' => []])->assertForbidden();
        $this->actingAs($staff)->get($this->url('tenant.delivery-notes.create'))->assertForbidden();
        $this->actingAs($staff)->post($this->url('tenant.delivery-notes.store'))->assertForbidden();
    }

    public function test_warehouse_staff_is_kept_out_of_every_other_area(): void
    {
        $staff = $this->warehouseStaff();

        foreach ([
            'tenant.products.index', 'tenant.sales.index', 'tenant.returns.index', 'tenant.shifts.index', 'tenant.expenses.index',
            'tenant.daily-closings.index', 'tenant.reports.sales', 'tenant.settings.index', 'tenant.stock-history.index', 'tenant.cashier.index',
        ] as $routeName) {
            $this->actingAs($staff)->get($this->url($routeName))->assertForbidden();
        }
    }

    public function test_warehouse_staff_lands_on_the_stock_opnames(): void
    {
        $this->actingAs($this->warehouseStaff())
            ->get($this->url('tenant.dashboard'))
            ->assertRedirect($this->url('tenant.stock-opnames.index'));
    }

    public function test_the_pages_share_what_the_user_may_do_and_their_role(): void
    {
        $this->actingAs($this->warehouseStaff())
            ->get($this->url('tenant.stock-opnames.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.role_label', 'Petugas Gudang')
                ->where('auth.permissions', fn ($permissions): bool => collect($permissions)->sort()->values()->all() === ['delivery_notes.view', 'stock_opnames.count'])
                ->where('can_create', true)
            );
    }

    public function test_without_posting_rights_the_opname_page_offers_no_review_or_post(): void
    {
        $opname = $this->store->run(fn () => StockOpname::factory()->create(['warehouse_id' => $this->mainWarehouse()->id, 'status' => 'review']));

        $this->actingAs($this->warehouseStaff())
            ->get($this->url('tenant.stock-opnames.show', ['stock_opname' => $opname->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('opname.can_post', false)->where('opname.can_review', false));

        $this->actingAs($this->storeUser(UserRole::StoreAdmin))
            ->get($this->url('tenant.stock-opnames.show', ['stock_opname' => $opname->id]))
            ->assertInertia(fn (Assert $page) => $page->where('opname.can_post', true));
    }

    public function test_store_admin_may_open_every_store_area(): void
    {
        $admin = $this->storeUser(UserRole::StoreAdmin);

        foreach ([
            'tenant.dashboard', 'tenant.products.index', 'tenant.sales.index', 'tenant.returns.index', 'tenant.shifts.index', 'tenant.expenses.index',
            'tenant.daily-closings.index', 'tenant.settings.index', 'tenant.stock-history.index', 'tenant.stock-opnames.index', 'tenant.delivery-notes.index',
        ] as $routeName) {
            $this->assertNotSame(403, $this->actingAs($admin)->get($this->url($routeName))->getStatusCode(), "{$routeName} is forbidden to the Admin Toko");
        }
    }

    public function test_a_cashier_still_works_from_the_cashier_screen_alone(): void
    {
        $cashier = $this->storeUser(UserRole::Cashier);

        $this->actingAs($cashier)->get($this->url('tenant.cashier.index'))->assertOk();
        $this->actingAs($cashier)->get($this->url('tenant.stock-opnames.index'))->assertForbidden();
        $this->actingAs($cashier)->get($this->url('tenant.delivery-notes.index'))->assertForbidden();
    }

    public function test_a_permission_given_directly_adds_to_the_role(): void
    {
        $cashier = $this->storeUser(UserRole::Cashier);

        $this->actingAs($cashier)->get($this->url('tenant.expenses.index'))->assertForbidden();

        $this->grantDirectly($cashier, Permission::ExpensesManage);

        $this->actingAs($cashier)->get($this->url('tenant.expenses.index'))->assertOk();
    }

    public function test_changing_the_permissions_of_a_role_changes_what_its_users_may_open(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->create();
        $this->store->users()->attach($user->id, ['role_id' => $role->id]);

        $this->actingAs($user)->get($this->url('tenant.shifts.index'))->assertForbidden();

        $role->permissions()->attach(PermissionRow::query()->where('name', Permission::ShiftsReview->value)->value('id'));

        $this->actingAs($user)->get($this->url('tenant.shifts.index'))->assertOk();
    }

    public function test_a_role_with_no_permission_can_enter_the_store_but_open_nothing(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->create();
        $this->store->users()->attach($user->id, ['role_id' => $role->id]);

        $this->actingAs($user)->get($this->url('tenant.dashboard'))->assertForbidden();
        $this->actingAs($user)->get($this->url('tenant.cashier.index'))->assertForbidden();
    }

    public function test_a_super_admin_may_open_everything(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($superAdmin)->get($this->url('tenant.settings.index'))->assertOk();
        $this->actingAs($superAdmin)->get($this->url('tenant.stock-opnames.index'))->assertOk();
    }
}
