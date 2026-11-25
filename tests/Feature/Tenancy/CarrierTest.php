<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Carrier;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class CarrierTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_carriers';

    private Store $store;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_carriers', 'receipt_prefix' => 'TEX']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.carriers.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

    private function carrier(string $name = 'Ekspedisi Laut Jaya', bool $isActive = true): Carrier
    {
        return $this->store->run(fn (): Carrier => Carrier::factory()->create(['name' => $name, 'is_active' => $isActive]));
    }

    public function test_only_users_who_may_manage_carriers_can_open_the_list(): void
    {
        $this->actingAs($this->cashier)->get($this->url('index'))->assertForbidden();
        $this->actingAs($this->admin)->get($this->url('index'))->assertOk();
    }

    public function test_a_carrier_can_be_added_with_optional_details(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => '  Ekspedisi Laut Jaya ', 'phone' => '0812345', 'address' => ' Pelabuhan Tanjung Perak '])->assertSessionHasNoErrors();

        $carrier = $this->store->run(fn (): Carrier => Carrier::query()->sole());

        $this->assertSame('Ekspedisi Laut Jaya', $carrier->name);
        $this->assertSame('0812345', $carrier->phone);
        $this->assertSame('Pelabuhan Tanjung Perak', $carrier->address);
        $this->assertTrue($carrier->is_active);
    }

    public function test_a_carrier_can_be_added_with_a_name_only(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'Ekspedisi Laut Jaya'])->assertSessionHasNoErrors();

        $carrier = $this->store->run(fn (): Carrier => Carrier::query()->sole());

        $this->assertNull($carrier->phone);
        $this->assertNull($carrier->address);
    }

    public function test_a_carrier_name_is_required_and_unique(): void
    {
        $this->carrier('Ekspedisi Laut Jaya');

        $this->actingAs($this->admin)->post($this->url('store'), ['name' => ''])->assertSessionHasErrors(['name' => 'Nama ekspedisi wajib diisi.']);
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'Ekspedisi Laut Jaya'])->assertSessionHasErrors(['name' => 'Nama ekspedisi sudah dipakai.']);
    }

    public function test_a_carrier_can_be_edited_and_switched_off_but_keeps_its_own_name_free(): void
    {
        $carrier = $this->carrier();

        $this->actingAs($this->admin)->put($this->url('update', ['carrier' => $carrier->id]), ['name' => 'Ekspedisi Laut Jaya', 'phone' => '0899', 'is_active' => false])->assertSessionHasNoErrors();

        $saved = $this->store->run(fn (): Carrier => Carrier::query()->findOrFail($carrier->id));
        $this->assertFalse($saved->is_active);
        $this->assertSame('0899', $saved->phone);
    }

    public function test_a_carrier_keeps_the_province_and_city_where_it_takes_goods_in_and_it_is_searchable(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'Trans Papua', 'province' => ' Jawa Timur ', 'city' => ' Surabaya '])->assertSessionHasNoErrors();
        $this->carrier('Cargo Udara');

        $carrier = $this->store->run(fn (): Carrier => Carrier::query()->where('name', 'Trans Papua')->sole());
        $this->assertSame(['Jawa Timur', 'Surabaya'], [$carrier->province, $carrier->city]);

        $this->actingAs($this->admin)->get($this->url('index', ['search' => 'surab']))->assertInertia(fn (Assert $page) => $page
            ->has('carriers.data', 1)
            ->where('carriers.data.0.name', 'Trans Papua'));
    }

    public function test_a_carrier_keeps_how_many_days_it_takes_and_rejects_a_silly_number(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'Ekspedisi Laut Jaya', 'transit_days' => '7'])->assertSessionHasNoErrors();
        $this->assertSame(7, $this->store->run(fn (): ?int => Carrier::query()->sole()->transit_days));

        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'Cargo Udara', 'transit_days' => 0])->assertSessionHasErrors(['transit_days' => 'Lama kirim minimal 1 hari.']);
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'Cargo Udara', 'transit_days' => 400])->assertSessionHasErrors(['transit_days' => 'Lama kirim paling lama 365 hari.']);
        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'Cargo Udara', 'transit_days' => 'seminggu'])->assertSessionHasErrors(['transit_days' => 'Lama kirim harus berupa angka hari.']);
    }

    public function test_a_carrier_can_have_several_contacts(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), [
            'name' => 'Ekspedisi Laut Jaya',
            'contacts' => [['name' => ' CS Pusat ', 'phone' => '0811'], ['name' => 'Pak Joko']],
        ])->assertSessionHasNoErrors();

        $contacts = $this->store->run(fn () => Carrier::query()->sole()->contacts->map->only(['name', 'phone'])->all());

        $this->assertSame([['name' => 'CS Pusat', 'phone' => '0811'], ['name' => 'Pak Joko', 'phone' => null]], $contacts);

        $this->actingAs($this->admin)->post($this->url('store'), ['name' => 'Cargo Udara', 'contacts' => [['name' => '', 'phone' => '0811']]])
            ->assertSessionHasErrors(['contacts.0.name' => 'Nama kontak wajib diisi.']);
    }

    public function test_saving_a_carrier_updates_adds_and_removes_its_contacts_to_match_the_list(): void
    {
        $carrier = $this->carrier();
        [$cs, $driver] = $this->store->run(fn (): array => [
            $carrier->contacts()->create(['name' => 'CS', 'phone' => '0811']),
            $carrier->contacts()->create(['name' => 'Sopir', 'phone' => '0822']),
        ]);

        $this->actingAs($this->admin)->put($this->url('update', ['carrier' => $carrier->id]), [
            'name' => $carrier->name,
            'contacts' => [['id' => $cs->id, 'name' => 'CS Pusat', 'phone' => '0899'], ['name' => 'Cabang Surabaya']],
        ])->assertSessionHasNoErrors();

        $contacts = $this->store->run(fn () => $carrier->contacts()->get());

        $this->assertSame(['CS Pusat', 'Cabang Surabaya'], $contacts->pluck('name')->all());
        $this->assertSame($cs->id, $contacts->first()->id);
        $this->assertNull($this->store->run(fn () => $carrier->contacts()->find($driver->id)));
    }

    public function test_an_update_without_a_contact_list_leaves_the_carriers_contacts_alone(): void
    {
        $carrier = $this->carrier();
        $this->store->run(fn () => $carrier->contacts()->create(['name' => 'CS']));

        $this->actingAs($this->admin)->put($this->url('update', ['carrier' => $carrier->id]), ['name' => $carrier->name, 'is_active' => false])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->store->run(fn (): int => $carrier->contacts()->count()));
    }

    public function test_the_list_shows_and_searches_the_contacts_and_links_to_the_purchases(): void
    {
        $carrier = $this->carrier('Ekspedisi Laut Jaya');
        $this->carrier('Cargo Udara');
        $this->store->run(fn () => $carrier->contacts()->create(['name' => 'Pak Joko', 'phone' => '0811']));

        $this->actingAs($this->admin)->get($this->url('index', ['search' => 'joko']))->assertInertia(fn (Assert $page) => $page
            ->has('carriers.data', 1)
            ->where('carriers.data.0.name', 'Ekspedisi Laut Jaya')
            ->where('carriers.data.0.contacts.0.name', 'Pak Joko')
            ->where('carriers.data.0.purchases_url', route('tenant.purchases.index', ['tenant' => $this->store->code, 'carrier' => $carrier->id])));
    }

    public function test_the_list_can_be_searched_and_narrowed_to_active_or_inactive(): void
    {
        $this->carrier('Ekspedisi Laut Jaya');
        $this->carrier('Cargo Udara Cepat');
        $this->carrier('Ekspedisi Lama', isActive: false);

        $names = fn (array $expected) => fn (Assert $page) => $page->component('Tenant/Carriers/Index')->where('carriers.data', fn ($rows) => collect($rows)->pluck('name')->all() === $expected);

        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia($names(['Cargo Udara Cepat', 'Ekspedisi Lama', 'Ekspedisi Laut Jaya']));
        $this->actingAs($this->admin)->get($this->url('index', ['search' => 'cargo']))->assertInertia($names(['Cargo Udara Cepat']));
        $this->actingAs($this->admin)->get($this->url('index', ['status' => 'inactive']))->assertInertia($names(['Ekspedisi Lama']));
        $this->actingAs($this->admin)->get($this->url('index', ['status' => 'active']))->assertInertia($names(['Cargo Udara Cepat', 'Ekspedisi Laut Jaya']));
    }
}
