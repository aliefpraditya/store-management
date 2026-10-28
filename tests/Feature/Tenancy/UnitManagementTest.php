<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class UnitManagementTest extends TestCase
{
    use PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_units';

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_units']);
        $this->admin = $this->userWithRole(UserRole::StoreAdmin);
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->store->users()->attach($user->id, ['role' => $role->value]);

        return $user;
    }

    private function url(string $route, array $parameters = []): string
    {
        return route("tenant.units.{$route}", ['tenant' => $this->store->code, ...$parameters]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inStore(callable $callback): mixed
    {
        return $this->store->run($callback);
    }

    private function makeUnit(string $code, string $name): Unit
    {
        return $this->inStore(fn () => Unit::factory()->create(['code' => $code, 'name' => $name]));
    }

    /**
     * A product sold in the unit: one unit row, as the item form makes.
     */
    private function sellProductIn(Unit $unit): Product
    {
        return $this->inStore(function () use ($unit): Product {
            $product = Product::factory()->create(['base_unit_code' => $unit->code]);
            ProductUnit::factory()->create(['product_id' => $product->id, 'unit_code' => $unit->code]);

            return $product;
        });
    }

    private function createUnit(array $data): TestResponse
    {
        return $this->actingAs($this->admin)->from($this->url('index'))->post($this->url('store'), $data);
    }

    public function test_only_admins_may_manage_units(): void
    {
        $unit = $this->makeUnit('PCS', 'Pieces');

        $this->actingAs($this->userWithRole(UserRole::Cashier));
        $this->get($this->url('index'))->assertForbidden();
        $this->post($this->url('store'), ['code' => 'DUS'])->assertForbidden();
        $this->put($this->url('update', ['unit' => $unit->code]), ['code' => 'PCS2'])->assertForbidden();
        $this->delete($this->url('destroy', ['unit' => $unit->code]))->assertForbidden();

        $this->assertSame(['PCS'], $this->inStore(fn () => Unit::query()->pluck('code')->all()));
    }

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get($this->url('index'))->assertRedirect(route('login'));
    }

    public function test_the_list_shows_every_unit_with_how_many_products_use_it(): void
    {
        $this->makeUnit('PCS', 'Pieces');
        $dus = $this->makeUnit('DUS', 'Karton');
        $this->sellProductIn($dus);
        $this->sellProductIn($dus);

        $this->actingAs($this->admin)->get($this->url('index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Settings/Units/Index')
            ->has('units', 2)
            ->where('units.0.code', 'DUS')
            ->where('units.0.products_count', 2)
            ->where('units.0.can_delete', false)
            ->where('units.0.update_url', $this->url('update', ['unit' => 'DUS']))
            ->where('units.1.code', 'PCS')
            ->where('units.1.name', 'Pieces')
            ->where('units.1.products_count', 0)
            ->where('units.1.can_delete', true)
            ->where('create_url', $this->url('store'))
        );
    }

    public function test_a_unit_is_added_with_an_upper_case_code_and_goes_back_to_where_the_request_came_from(): void
    {
        $this->createUnit(['code' => ' pcs ', 'name' => 'Pieces'])
            ->assertRedirect($this->url('index'))
            ->assertSessionHas('success');

        $unit = $this->inStore(fn () => Unit::query()->firstOrFail());

        $this->assertSame(['PCS', 'Pieces'], [$unit->code, $unit->name]);
    }

    public function test_a_blank_name_follows_the_code(): void
    {
        $this->createUnit(['code' => 'kg 2.5', 'name' => ''])->assertSessionHasNoErrors();

        $unit = $this->inStore(fn () => Unit::query()->firstOrFail());

        $this->assertSame(['KG 2.5', 'KG 2.5'], [$unit->code, $unit->name]);
    }

    public function test_code_and_name_rules(): void
    {
        $this->makeUnit('PCS', 'Pieces');

        $this->createUnit(['code' => ''])->assertSessionHasErrors(['code' => 'Kode satuan wajib diisi.']);
        $this->createUnit(['code' => str_repeat('K', 21)])->assertSessionHasErrors('code');
        $this->createUnit(['code' => 'KG@'])->assertSessionHasErrors('code');
        $this->createUnit(['code' => '-KG'])->assertSessionHasErrors('code');
        $this->createUnit(['code' => 'pcs'])->assertSessionHasErrors(['code' => 'Kode satuan sudah dipakai.']);
        $this->createUnit(['code' => 'PIECE', 'name' => 'pieces'])->assertSessionHasErrors(['name' => 'Nama satuan sudah dipakai.']);

        $this->assertSame(1, $this->inStore(fn () => Unit::query()->count()));
    }

    public function test_a_blank_name_cannot_collide_with_another_units_name(): void
    {
        $this->makeUnit('PCS', 'BOX');

        $this->createUnit(['code' => 'box', 'name' => ''])->assertSessionHasErrors('name');
    }

    public function test_the_name_can_be_changed_and_a_unit_keeps_its_own_code_and_name_when_saved(): void
    {
        $unit = $this->makeUnit('PCS', 'Pieces');

        $this->actingAs($this->admin)
            ->put($this->url('update', ['unit' => $unit->code]), ['code' => 'PCS', 'name' => 'Buah'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Buah', $this->inStore(fn () => Unit::query()->findOrFail('PCS')->name));

        $this->actingAs($this->admin)
            ->put($this->url('update', ['unit' => 'PCS']), ['code' => 'pcs', 'name' => 'Buah'])
            ->assertSessionHasNoErrors();
    }

    public function test_renaming_a_code_follows_through_to_the_products_that_use_it(): void
    {
        $dus = $this->makeUnit('DUS', 'Karton');
        $product = $this->sellProductIn($dus);

        $this->actingAs($this->admin)
            ->put($this->url('update', ['unit' => 'DUS']), ['code' => 'BOX', 'name' => 'Karton'])
            ->assertSessionHasNoErrors();

        $this->inStore(function () use ($product): void {
            $this->assertSame(['BOX'], Unit::query()->pluck('code')->all());
            $this->assertSame('BOX', $product->fresh()->base_unit_code);
            $this->assertSame(['BOX'], ProductUnit::query()->pluck('unit_code')->all());
        });
    }

    public function test_a_code_cannot_be_renamed_to_one_that_exists(): void
    {
        $this->makeUnit('PCS', 'Pieces');
        $dus = $this->makeUnit('DUS', 'Karton');

        $this->actingAs($this->admin)
            ->put($this->url('update', ['unit' => $dus->code]), ['code' => 'pcs', 'name' => 'Karton'])
            ->assertSessionHasErrors('code');

        $this->assertSame(['DUS', 'PCS'], $this->inStore(fn () => Unit::query()->orderBy('code')->pluck('code')->all()));
    }

    public function test_a_code_with_spaces_and_dots_can_be_edited_through_its_url(): void
    {
        $this->makeUnit('KG 2.5', 'Kilogram');

        $this->actingAs($this->admin)
            ->put($this->url('update', ['unit' => 'KG 2.5']), ['code' => 'KG 2.5', 'name' => 'Kilo'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Kilo', $this->inStore(fn () => Unit::query()->findOrFail('KG 2.5')->name));
    }

    public function test_an_unused_unit_can_be_deleted(): void
    {
        $unit = $this->makeUnit('PCS', 'Pieces');

        $this->actingAs($this->admin)
            ->delete($this->url('destroy', ['unit' => $unit->code]))
            ->assertSessionHas('success', 'Satuan dihapus.');

        $this->assertSame(0, $this->inStore(fn () => Unit::query()->count()));
    }

    public function test_a_unit_that_products_use_cannot_be_deleted(): void
    {
        $dus = $this->makeUnit('DUS', 'Karton');
        $this->sellProductIn($dus);

        $this->actingAs($this->admin)
            ->delete($this->url('destroy', ['unit' => 'DUS']))
            ->assertSessionHas('error');

        $this->assertSame(1, $this->inStore(fn () => Unit::query()->count()));
    }

    public function test_a_unit_that_is_only_the_base_unit_of_a_product_cannot_be_deleted(): void
    {
        $pail = $this->makeUnit('PAIL', 'Pail');

        $this->inStore(fn () => Product::factory()->create(['base_unit_code' => $pail->code]));

        $this->actingAs($this->admin)
            ->delete($this->url('destroy', ['unit' => 'PAIL']))
            ->assertSessionHas('error');

        $this->assertSame(1, $this->inStore(fn () => Unit::query()->count()));
    }
}
