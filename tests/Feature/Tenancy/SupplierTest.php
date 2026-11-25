<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_suppliers';

    private Store $store;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_suppliers', 'receipt_prefix' => 'TSU']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.suppliers.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

    private function supplier(string $name = 'PT Cat Jaya', bool $isActive = true): Supplier
    {
        return $this->store->run(fn (): Supplier => Supplier::factory()->create(['name' => $name, 'is_active' => $isActive]));
    }

    public function test_only_users_who_may_manage_suppliers_can_open_the_list(): void
    {
        $this->actingAs($this->cashier)->get($this->url('index'))->assertForbidden();
        $this->actingAs($this->admin)->get($this->url('index'))->assertOk();
    }

    public function test_a_supplier_can_be_added(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => '  PT Semen Kuat ', 'phone' => '0812345', 'notes' => 'Kirim tiap Senin'])->assertSessionHasNoErrors();

        $supplier = $this->store->run(fn (): Supplier => Supplier::query()->sole());

        $this->assertSame('PT Semen Kuat', $supplier->name);
        $this->assertSame('0812345', $supplier->phone);
        $this->assertTrue($supplier->is_active);
    }

    public function test_a_supplier_name_is_required_and_unique(): void
    {
        $this->supplier('PT Cat Jaya');

        $this->actingAs($this->admin)->post($this->url('store'), ['name' => ''])->assertSessionHasErrors(['name' => 'Nama supplier wajib diisi.']);
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'PT Cat Jaya'])->assertSessionHasErrors(['name' => 'Nama supplier sudah dipakai.']);
    }

    public function test_a_supplier_can_be_renamed_and_switched_off_but_keeps_its_own_name_free(): void
    {
        $supplier = $this->supplier();

        $this->actingAs($this->admin)->put($this->url('update', ['supplier' => $supplier->id]), ['name' => 'PT Cat Jaya', 'phone' => '0899', 'is_active' => false])->assertSessionHasNoErrors();

        $supplier = $supplier->fresh();
        $this->assertFalse($this->store->run(fn (): bool => Supplier::query()->findOrFail($supplier->id)->is_active));
        $this->assertSame('0899', $this->store->run(fn (): ?string => Supplier::query()->findOrFail($supplier->id)->phone));
    }

    public function test_the_list_can_be_searched_and_narrowed_to_active_or_inactive(): void
    {
        $this->supplier('PT Cat Jaya');
        $this->supplier('CV Semen Kuat');
        $this->supplier('Toko Lama', isActive: false);

        $names = fn (array $query) => fn (Assert $page) => $page->component('Tenant/Suppliers/Index')->where('suppliers.data', fn ($rows) => collect($rows)->pluck('name')->all() === $query);

        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia($names(['CV Semen Kuat', 'PT Cat Jaya', 'Toko Lama']));
        $this->actingAs($this->admin)->get($this->url('index', ['search' => 'semen']))->assertInertia($names(['CV Semen Kuat']));
        $this->actingAs($this->admin)->get($this->url('index', ['status' => 'inactive']))->assertInertia($names(['Toko Lama']));
        $this->actingAs($this->admin)->get($this->url('index', ['status' => 'active']))->assertInertia($names(['CV Semen Kuat', 'PT Cat Jaya']));
    }
}
