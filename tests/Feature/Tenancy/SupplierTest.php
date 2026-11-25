<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Carrier;
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
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => '  PT Semen Kuat ', 'phone' => '0812345', 'address' => ' Jl. Industri No. 5, Surabaya ', 'notes' => 'Kirim tiap Senin'])->assertSessionHasNoErrors();

        $supplier = $this->store->run(fn (): Supplier => Supplier::query()->sole());

        $this->assertSame('PT Semen Kuat', $supplier->name);
        $this->assertSame('0812345', $supplier->phone);
        $this->assertSame('Jl. Industri No. 5, Surabaya', $supplier->address);
        $this->assertTrue($supplier->is_active);
    }

    public function test_a_supplier_can_be_added_without_an_address(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'PT Semen Kuat'])->assertSessionHasNoErrors();

        $this->assertNull($this->store->run(fn (): ?string => Supplier::query()->sole()->address));
    }

    public function test_a_supplier_address_can_be_changed_and_cleared(): void
    {
        $supplier = $this->supplier();

        $this->actingAs($this->admin)->put($this->url('update', ['supplier' => $supplier->id]), ['name' => $supplier->name, 'address' => 'Jl. Baru 1'])->assertSessionHasNoErrors();
        $this->assertSame('Jl. Baru 1', $this->store->run(fn (): ?string => Supplier::query()->findOrFail($supplier->id)->address));

        $this->actingAs($this->admin)->put($this->url('update', ['supplier' => $supplier->id]), ['name' => $supplier->name, 'address' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->store->run(fn (): ?string => Supplier::query()->findOrFail($supplier->id)->address));
    }

    public function test_a_supplier_keeps_its_email_and_tax_number_and_rejects_a_malformed_email(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'PT Semen Kuat', 'email' => ' sales@semenkuat.test ', 'tax_number' => ' 01.234.567.8-901.000 '])->assertSessionHasNoErrors();

        $supplier = $this->store->run(fn (): Supplier => Supplier::query()->sole());
        $this->assertSame('sales@semenkuat.test', $supplier->email);
        $this->assertSame('01.234.567.8-901.000', $supplier->tax_number);

        $this->actingAs($this->admin)->put($this->url('update', ['supplier' => $supplier->id]), ['name' => 'PT Semen Kuat', 'email' => 'not-an-email'])->assertSessionHasErrors(['email' => 'Format email tidak valid.']);
    }

    public function test_a_supplier_can_be_added_with_several_salespeople(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), [
            'name' => 'PT Semen Kuat',
            'contacts' => [['name' => ' Budi ', 'phone' => '0811'], ['name' => 'Sari']],
        ])->assertSessionHasNoErrors();

        $contacts = $this->store->run(fn () => Supplier::query()->sole()->contacts->map->only(['name', 'phone'])->all());

        $this->assertSame([['name' => 'Budi', 'phone' => '0811'], ['name' => 'Sari', 'phone' => null]], $contacts);
    }

    public function test_a_salesperson_needs_a_name(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'PT Semen Kuat', 'contacts' => [['name' => '', 'phone' => '0811']]])
            ->assertSessionHasErrors(['contacts.0.name' => 'Nama kontak wajib diisi.']);
    }

    public function test_saving_a_supplier_updates_adds_and_removes_its_salespeople_to_match_the_list(): void
    {
        $supplier = $this->supplier();
        [$budi, $sari] = $this->store->run(fn (): array => [
            $supplier->contacts()->create(['name' => 'Budi', 'phone' => '0811']),
            $supplier->contacts()->create(['name' => 'Sari', 'phone' => '0822']),
        ]);

        $this->actingAs($this->admin)->put($this->url('update', ['supplier' => $supplier->id]), [
            'name' => $supplier->name,
            'contacts' => [['id' => $budi->id, 'name' => 'Budi Santoso', 'phone' => '0899'], ['name' => 'Joko']],
        ])->assertSessionHasNoErrors();

        $contacts = $this->store->run(fn () => $supplier->contacts()->get());

        $this->assertSame(['Budi Santoso', 'Joko'], $contacts->pluck('name')->all());
        $this->assertSame($budi->id, $contacts->first()->id);
        $this->assertSame('0899', $contacts->first()->phone);
        $this->assertNull($this->store->run(fn () => $supplier->contacts()->find($sari->id)));
    }

    public function test_an_update_without_a_contact_list_leaves_the_salespeople_alone(): void
    {
        $supplier = $this->supplier();
        $this->store->run(fn () => $supplier->contacts()->create(['name' => 'Budi']));

        $this->actingAs($this->admin)->put($this->url('update', ['supplier' => $supplier->id]), ['name' => $supplier->name, 'is_active' => false])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->store->run(fn (): int => $supplier->contacts()->count()));
    }

    public function test_a_contact_id_of_another_supplier_is_not_taken_over(): void
    {
        $supplier = $this->supplier('PT Cat Jaya');
        $otherSupplier = $this->supplier('CV Semen Kuat');
        $othersContact = $this->store->run(fn () => $otherSupplier->contacts()->create(['name' => 'Budi']));

        $this->actingAs($this->admin)->put($this->url('update', ['supplier' => $supplier->id]), [
            'name' => $supplier->name,
            'contacts' => [['id' => $othersContact->id, 'name' => 'Intruder']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Budi', $this->store->run(fn (): string => $othersContact->fresh()->name));
        $this->assertSame(['Intruder'], $this->store->run(fn (): array => $supplier->contacts()->pluck('name')->all()));
    }

    public function test_the_list_shows_the_salespeople_of_each_supplier(): void
    {
        $supplier = $this->supplier();
        $this->store->run(fn () => $supplier->contacts()->create(['name' => 'Budi', 'phone' => '0811']));

        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('suppliers.data.0.contacts.0.name', 'Budi')
            ->where('suppliers.data.0.contacts.0.phone', '0811'));
    }

    public function test_a_supplier_keeps_its_province_and_city_and_they_are_searchable(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'PT Semen Kuat', 'province' => ' Jawa Timur ', 'city' => ' Surabaya '])->assertSessionHasNoErrors();
        $this->supplier('CV Cat Jaya');

        $supplier = $this->store->run(fn (): Supplier => Supplier::query()->where('name', 'PT Semen Kuat')->sole());
        $this->assertSame(['Jawa Timur', 'Surabaya'], [$supplier->province, $supplier->city]);

        $this->actingAs($this->admin)->get($this->url('index', ['search' => 'surab']))->assertInertia(fn (Assert $page) => $page
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.name', 'PT Semen Kuat')
            ->where('suppliers.data.0.city', 'Surabaya'));
    }

    public function test_the_list_links_each_supplier_to_its_purchases(): void
    {
        $supplier = $this->supplier();

        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('suppliers.data.0.purchases_url', route('tenant.purchases.index', ['tenant' => $this->store->code, 'supplier' => $supplier->id])));
    }

    public function test_the_provinces_and_cities_already_typed_on_suppliers_and_carriers_are_offered_once_each(): void
    {
        $this->store->run(function (): void {
            Supplier::factory()->create(['name' => 'PT Semen Kuat', 'province' => 'Jawa Timur', 'city' => 'Surabaya']);
            Supplier::factory()->create(['name' => 'CV Cat Jaya', 'province' => 'Jawa Barat', 'city' => 'Bandung']);
            Carrier::factory()->create(['name' => 'Laut Jaya', 'province' => 'jawa timur', 'city' => 'surabaya']);
            Carrier::factory()->create(['name' => 'Cargo Udara', 'province' => 'Jawa Timur', 'city' => 'Malang']);
        });

        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('regions.provinces', ['Jawa Barat', 'Jawa Timur'])
            ->where('regions.cities', ['Bandung', 'Malang', 'Surabaya']));
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
