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

    /**
     * The settings page as the one Save sends it: the required settings, and the drivers as the whole list.
     *
     * @param  list<array{id?: int|null, name: string, is_active: bool}>  $drivers
     * @return array<string, mixed>
     */
    private function pageWith(array $drivers): array
    {
        return ['paper_width' => 80, 'lock_sales_during_opname' => false, 'auto_print_receipt' => false, 'drivers' => $drivers];
    }

    private function save(array $drivers)
    {
        return $this->actingAs($this->admin)->put($this->url('tenant.settings.update'), $this->pageWith($drivers));
    }

    public function test_an_admin_adds_renames_and_switches_off_drivers_with_the_one_save_of_the_settings_page(): void
    {
        $this->save([['id' => null, 'name' => '  Pak Joko ', 'is_active' => true]])->assertSessionHasNoErrors();

        $joko = $this->store->run(fn (): Driver => Driver::query()->sole());
        $this->assertSame(['Pak Joko', true], [$joko->name, $joko->is_active]);

        $this->save([['id' => $joko->id, 'name' => 'Joko S.', 'is_active' => false]])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get($this->url('tenant.settings.index'))->assertInertia(fn (Assert $page) => $page
            ->where('drivers.0.name', 'Joko S.')
            ->where('drivers.0.is_active', false)
            ->missing('drivers.0.update_url')
            ->missing('urls.driver_create'));
    }

    public function test_a_driver_that_is_left_out_of_the_list_stays(): void
    {
        $this->driver('Pak Joko');
        $this->driver('Pak Budi');

        $this->save([['id' => null, 'name' => 'Pak Adi', 'is_active' => true]])->assertSessionHasNoErrors();

        $this->assertSame(3, $this->store->run(fn (): int => Driver::query()->count()));
    }

    public function test_a_driver_name_must_be_filled_and_cannot_be_used_twice(): void
    {
        $joko = $this->driver('Pak Joko');

        $this->save([['id' => $joko->id, 'name' => 'Pak Joko', 'is_active' => true], ['id' => null, 'name' => 'pak joko', 'is_active' => true]])->assertSessionHasErrors(['drivers.1.name' => 'Nama sopir sudah ada.']);
        $this->save([['id' => null, 'name' => 'Pak Joko', 'is_active' => true]])->assertSessionHasErrors(['drivers.0.name' => 'Nama sopir sudah ada.']);
        $this->save([['id' => null, 'name' => '', 'is_active' => true]])->assertSessionHasErrors(['drivers.0.name' => 'Nama sopir wajib diisi.']);
        $this->save([['id' => null, 'name' => str_repeat('a', 101), 'is_active' => true]])->assertSessionHasErrors('drivers.0.name');

        $this->assertSame(1, $this->store->run(fn (): int => Driver::query()->count()));
    }

    public function test_a_driver_can_keep_their_own_name_when_the_page_is_saved(): void
    {
        $joko = $this->driver('Pak Joko');

        $this->save([['id' => $joko->id, 'name' => 'Pak Joko', 'is_active' => true]])->assertSessionHasNoErrors();
    }

    public function test_a_driver_that_does_not_exist_cannot_be_changed(): void
    {
        $this->save([['id' => 999, 'name' => 'Hantu', 'is_active' => true]])->assertSessionHasErrors('drivers.0.id');
    }

    public function test_a_cashier_cannot_manage_drivers(): void
    {
        $this->actingAs($this->cashier)->put($this->url('tenant.settings.update'), $this->pageWith([['id' => null, 'name' => 'Pak Joko', 'is_active' => true]]))->assertForbidden();

        $this->assertSame(0, $this->store->run(fn (): int => Driver::query()->count()));
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

        $this->save([['id' => $joko->id, 'name' => 'Joko Baru', 'is_active' => false]]);

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

    public function test_a_driver_must_say_whether_they_are_active_and_a_blank_name_is_refused(): void
    {
        $this->save([['id' => null, 'name' => 'Pak Joko', 'is_active' => null]])->assertSessionHasErrors('drivers.0.is_active');
        $this->save([['id' => null, 'name' => 'Pak Joko', 'is_active' => 'mungkin']])->assertSessionHasErrors('drivers.0.is_active');
        $this->save([['id' => null, 'name' => '   ', 'is_active' => true]])->assertSessionHasErrors(['drivers.0.name' => 'Nama sopir wajib diisi.']);

        $this->assertSame(0, $this->store->run(fn (): int => Driver::query()->count()));
    }

    public function test_the_driver_to_ship_with_must_be_an_existing_whole_number(): void
    {
        $note = $this->draftNote();
        $ship = fn (mixed $driverId) => $this->actingAs($this->admin)->post($this->url('tenant.delivery-notes.ship', ['delivery_note' => $note->id]), ['driver_id' => $driverId]);

        $ship('bukan-angka')->assertSessionHasErrors('driver_id');
        $ship(999)->assertSessionHasErrors('driver_id');

        $this->assertSame(DeliveryNoteStatus::Draft, $this->store->run(fn (): DeliveryNoteStatus => DeliveryNote::query()->findOrFail($note->id)->status));
    }

    public function test_a_note_that_is_no_longer_a_draft_cannot_be_shipped_again_with_another_driver(): void
    {
        $joko = $this->driver('Pak Joko');
        $note = $this->store->run(function (): DeliveryNote {
            $sale = Sale::factory()->create();

            return DeliveryNote::factory()->create(['sale_id' => $sale->id, 'status' => DeliveryNoteStatus::Delivered, 'driver_name' => 'Pak Lama']);
        });

        $this->actingAs($this->admin)->post($this->url('tenant.delivery-notes.ship', ['delivery_note' => $note->id]), ['driver_id' => $joko->id])->assertSessionHas('error');

        $unchanged = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->findOrFail($note->id));

        $this->assertSame([DeliveryNoteStatus::Delivered, 'Pak Lama'], [$unchanged->status, $unchanged->driver_name]);
    }

    public function test_a_switched_off_driver_cannot_be_chosen(): void
    {
        $budi = $this->driver('Pak Budi', false);
        $note = $this->draftNote();

        $this->actingAs($this->admin)->post($this->url('tenant.delivery-notes.ship', ['delivery_note' => $note->id]), ['driver_id' => $budi->id])->assertSessionHasErrors('driver_id');

        $this->assertSame(DeliveryNoteStatus::Draft, $this->store->run(fn (): DeliveryNoteStatus => DeliveryNote::query()->findOrFail($note->id)->status));
    }
}
