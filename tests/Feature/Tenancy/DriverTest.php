<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DeliveryNoteStatus;
use App\Enums\UserRole;
use App\Models\DeliveryNote;
use App\Models\Driver;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

/**
 * The drivers kept in the store settings, and choosing one when a delivery note is shipped.
 */
class DriverTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_driver';

    private Store $store;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_driver', 'receipt_prefix' => 'TDR']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
    }

    private function url(string $name, array $parameters = []): string
    {
        return route($name, ['tenant' => $this->store->code, ...$parameters]);
    }

    private function draftNote(): DeliveryNote
    {
        return $this->store->run(function (): DeliveryNote {
            $sale = Sale::factory()->create();

            return DeliveryNote::factory()->create(['sale_id' => $sale->id, 'status' => DeliveryNoteStatus::Draft]);
        });
    }

    private function driver(string $name, bool $isActive = true): Driver
    {
        return $this->store->run(fn (): Driver => Driver::factory()->create(['name' => $name, 'is_active' => $isActive]));
    }

    public function test_an_admin_adds_renames_and_switches_off_drivers_on_the_settings_page(): void
    {
        $this->actingAs($this->admin)->post($this->url('tenant.drivers.store'), ['name' => 'Pak Joko'])->assertSessionHasNoErrors();

        $joko = $this->store->run(fn (): Driver => Driver::query()->sole());
        $this->assertTrue($joko->is_active);

        $this->actingAs($this->admin)->put($this->url('tenant.drivers.update', ['driver' => $joko->id]), ['name' => 'Joko S.', 'is_active' => false])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get($this->url('tenant.settings.index'))->assertInertia(fn (Assert $page) => $page
            ->where('drivers.0.name', 'Joko S.')
            ->where('drivers.0.is_active', false));
    }

    public function test_a_driver_name_must_be_filled_and_cannot_be_used_twice(): void
    {
        $this->driver('Pak Joko');

        $this->actingAs($this->admin)->post($this->url('tenant.drivers.store'), ['name' => 'Pak Joko'])->assertSessionHasErrors(['name' => 'Nama sopir sudah ada.']);
        $this->actingAs($this->admin)->post($this->url('tenant.drivers.store'), ['name' => ''])->assertSessionHasErrors(['name' => 'Nama sopir wajib diisi.']);

        $this->assertSame(1, $this->store->run(fn (): int => Driver::query()->count()));
    }

    public function test_a_driver_can_keep_their_own_name_when_edited(): void
    {
        $joko = $this->driver('Pak Joko');

        $this->actingAs($this->admin)->put($this->url('tenant.drivers.update', ['driver' => $joko->id]), ['name' => 'Pak Joko', 'is_active' => true])->assertSessionHasNoErrors();
    }

    public function test_a_cashier_cannot_manage_drivers(): void
    {
        $this->actingAs($this->cashier)->post($this->url('tenant.drivers.store'), ['name' => 'Pak Joko'])->assertForbidden();
    }

    public function test_the_delivery_note_page_offers_only_active_drivers(): void
    {
        $this->driver('Pak Joko');
        $this->driver('Pak Budi', false);
        $note = $this->draftNote();

        $this->actingAs($this->admin)->get($this->url('tenant.delivery-notes.show', ['delivery_note' => $note->id]))
            ->assertInertia(fn (Assert $page) => $page->has('drivers', 1)->where('drivers.0.name', 'Pak Joko'));
    }

    public function test_shipping_a_note_with_a_driver_puts_the_drivers_name_on_it(): void
    {
        $joko = $this->driver('Pak Joko');
        $note = $this->draftNote();

        $this->actingAs($this->admin)->post($this->url('tenant.delivery-notes.ship', ['delivery_note' => $note->id]), ['driver_id' => $joko->id])->assertSessionHasNoErrors();

        $shipped = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->findOrFail($note->id));

        $this->assertSame(DeliveryNoteStatus::Shipped, $shipped->status);
        $this->assertSame('Pak Joko', $shipped->driver_name);
    }

    public function test_the_note_keeps_the_name_when_the_driver_is_renamed_later(): void
    {
        $joko = $this->driver('Pak Joko');
        $note = $this->draftNote();
        $this->actingAs($this->admin)->post($this->url('tenant.delivery-notes.ship', ['delivery_note' => $note->id]), ['driver_id' => $joko->id]);

        $this->actingAs($this->admin)->put($this->url('tenant.drivers.update', ['driver' => $joko->id]), ['name' => 'Joko Baru', 'is_active' => false]);

        $this->assertSame('Pak Joko', $this->store->run(fn (): ?string => DeliveryNote::query()->findOrFail($note->id)->driver_name));
    }

    public function test_a_note_can_be_shipped_without_a_driver(): void
    {
        $note = $this->draftNote();

        $this->actingAs($this->admin)->post($this->url('tenant.delivery-notes.ship', ['delivery_note' => $note->id]))->assertSessionHasNoErrors();

        $shipped = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->findOrFail($note->id));

        $this->assertSame(DeliveryNoteStatus::Shipped, $shipped->status);
        $this->assertNull($shipped->driver_name);
    }

    public function test_a_switched_off_driver_cannot_be_chosen(): void
    {
        $budi = $this->driver('Pak Budi', false);
        $note = $this->draftNote();

        $this->actingAs($this->admin)->post($this->url('tenant.delivery-notes.ship', ['delivery_note' => $note->id]), ['driver_id' => $budi->id])->assertSessionHasErrors('driver_id');

        $this->assertSame(DeliveryNoteStatus::Draft, $this->store->run(fn (): DeliveryNoteStatus => DeliveryNote::query()->findOrFail($note->id)->status));
    }
}
