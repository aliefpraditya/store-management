<?php

namespace Tests\Feature\Tenancy;

use App\Enums\PurchaseStatus;
use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Models\Carrier;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class ShipmentTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_shipments';

    private Store $store;

    private User $admin;

    private User $cashier;

    private Supplier $supplier;

    private Carrier $carrier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_shipments', 'receipt_prefix' => 'TSH']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
        $this->supplier = $this->store->run(fn (): Supplier => Supplier::factory()->create(['name' => 'PT Semen Kuat']));
        $this->carrier = $this->store->run(fn (): Carrier => Carrier::factory()->create(['name' => 'PT Lintas Papua Jaya', 'transit_days' => 10]));
        $this->product = $this->sellableProduct('Semen');
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.shipments.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

    /** A purchase that is confirmed and paid, so it can be loaded: one line of `$quantity` pieces at 6.000. */
    private function orderedPurchase(string $quantity = '1000', string $documentNumber = 'INV-001'): Purchase
    {
        $purchasesUrl = fn (string $name, array $parameters = []) => route("tenant.purchases.{$name}", ['tenant' => $this->store->code, ...$parameters]);

        $this->actingAs($this->admin)->post($purchasesUrl('store'), [
            'client_uuid' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'supplier_document_number' => $documentNumber,
            'purchase_date' => today()->toDateString(),
            'warehouse_id' => $this->mainWarehouse()->id,
            'items' => [['product_id' => $this->product->id, 'unit_code' => 'PCS', 'quantity' => $quantity, 'unit_cost' => 6000]],
        ])->assertSessionHasNoErrors();

        $purchase = $this->store->run(fn (): Purchase => Purchase::query()->latest('id')->firstOrFail());

        $this->actingAs($this->admin)->post($purchasesUrl('pay', ['purchase' => $purchase->id]), ['payment_method' => 'transfer', 'paid_date' => today()->toDateString()])
            ->assertSessionHasNoErrors();

        return $purchase->fresh();
    }

    private function itemIdOf(Purchase $purchase): int
    {
        return $this->store->run(fn (): int => $purchase->items()->sole()->id);
    }

    /**
     * One packing list row.
     *
     * @return array<string, mixed>
     */
    private function row(Purchase $purchase, string $container, string $quantity = '1000', string $freightCost = '500000'): array
    {
        return ['purchase_item_id' => $this->itemIdOf($purchase), 'container_number' => $container, 'quantity' => $quantity, 'freight_cost' => $freightCost];
    }

    /**
     * A shipment as the form sends it; `$overrides` replaces any field.
     *
     * @param  list<Purchase>  $purchases
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function body(array $purchases, array $rows = [], array $overrides = []): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'carrier_id' => $this->carrier->id,
            'purchase_ids' => array_map(fn (Purchase $purchase): int => $purchase->id, $purchases),
            'allocations' => $rows,
            ...$overrides,
        ];
    }

    private function draft(array $purchases, array $rows = [], array $overrides = []): Shipment
    {
        $this->actingAs($this->admin)->post($this->url('store'), $this->body($purchases, $rows, $overrides))->assertSessionHasNoErrors();

        return $this->store->run(fn (): Shipment => Shipment::query()->latest('id')->firstOrFail());
    }

    private function statusOf(Purchase $purchase): PurchaseStatus
    {
        return $this->store->run(fn (): PurchaseStatus => Purchase::query()->findOrFail($purchase->id)->status);
    }

    public function test_only_users_who_may_manage_shipments_can_open_them(): void
    {
        foreach (['index', 'create'] as $name) {
            $this->actingAs($this->cashier)->get($this->url($name))->assertForbidden();
            $this->actingAs($this->admin)->get($this->url($name))->assertOk();
        }
    }

    public function test_a_draft_keeps_its_notas_and_its_packing_list_without_shipping_them(): void
    {
        $purchase = $this->orderedPurchase();

        $shipment = $this->draft([$purchase], [$this->row($purchase, ' spnu  271.737-2 ', '1000', '650000')], ['vessel_name' => ' KM. Spil Hapsri ']);

        $this->assertSame(ShipmentStatus::Draft, $shipment->status);
        $this->assertMatchesRegularExpression('#^PG/TSH/\d{4}/0001$#', $shipment->number);
        $this->assertSame('KM. Spil Hapsri', $shipment->vessel_name);
        $this->assertSame($shipment->id, $purchase->fresh()->shipment_id);
        $this->assertSame(PurchaseStatus::Ordered, $this->statusOf($purchase));

        $container = $this->store->run(fn () => $shipment->containers()->with('lines')->sole());
        $this->assertSame('SPNU 271.737-2', $container->number);
        $this->assertSame(['1000.000', '1000.000', '650000.00'], [$container->lines[0]->quantity, $container->lines[0]->base_quantity, $container->lines[0]->freight_cost]);
    }

    public function test_one_purchase_line_can_be_split_over_several_containers_and_departing_ships_the_notas(): void
    {
        $purchase = $this->orderedPurchase('1000');
        $rows = array_map(fn (int $number): array => $this->row($purchase, "SPNU 000.00{$number}-1", '100', '100000'), range(1, 10));

        $shipment = $this->draft([$purchase], $rows, ['departure_date' => today()->toDateString()]);

        $this->assertSame(ShipmentStatus::Shipped, $shipment->status);
        $this->assertNotNull($shipment->shipped_at);
        $this->assertSame(10, $this->store->run(fn (): int => $shipment->containers()->count()));
        $this->assertSame('1000000.00', $this->store->run(fn (): string => (string) ShipmentLine::query()->sum('freight_cost')));

        $purchase = $purchase->fresh();
        $this->assertSame(PurchaseStatus::Shipped, $purchase->status);
        $this->assertSame($this->carrier->id, $purchase->carrier_id);
    }

    public function test_rows_for_the_same_container_and_line_are_added_together(): void
    {
        $purchase = $this->orderedPurchase('1000');

        $shipment = $this->draft([$purchase], [$this->row($purchase, 'SPNU 1', '400', '100000'), $this->row($purchase, 'spnu 1', '600', '200000')]);

        $line = $this->store->run(fn () => ShipmentLine::query()->where('shipment_container_id', $shipment->containers()->sole()->id)->sole());
        $this->assertSame(['1000.000', '300000.00'], [$line->quantity, $line->freight_cost]);
    }

    public function test_a_shipment_cannot_depart_until_everything_on_its_notas_is_in_a_container(): void
    {
        $purchase = $this->orderedPurchase('1000');

        $this->actingAs($this->admin)->post($this->url('store'), $this->body([$purchase], [$this->row($purchase, 'SPNU 1', '900')], ['departure_date' => today()->toDateString()]))
            ->assertSessionHas('error', "Nota {$purchase->number}: jumlah Semen di container belum sama dengan jumlah di nota. Semua barang nota harus masuk container sebelum diberangkatkan.");
        $this->actingAs($this->admin)->post($this->url('store'), $this->body([$purchase], [], ['departure_date' => today()->toDateString()]))
            ->assertSessionHas('error', 'Isi packing list dulu: belum ada barang di container.');

        $this->assertSame(0, $this->store->run(fn (): int => Shipment::query()->count()));
        $this->assertSame(PurchaseStatus::Ordered, $this->statusOf($purchase));
    }

    public function test_a_container_cannot_hold_more_than_the_nota_says(): void
    {
        $purchase = $this->orderedPurchase('1000');

        $this->actingAs($this->admin)->post($this->url('store'), $this->body([$purchase], [$this->row($purchase, 'SPNU 1', '600'), $this->row($purchase, 'SPNU 2', '600')]))
            ->assertSessionHas('error', 'Jumlah Semen di container melebihi jumlah di nota (1000.000).');
    }

    public function test_the_packing_list_rows_are_validated(): void
    {
        $purchase = $this->orderedPurchase();

        $this->actingAs($this->admin)->post($this->url('store'), $this->body([$purchase], [['purchase_item_id' => $this->itemIdOf($purchase), 'container_number' => '', 'quantity' => 0, 'freight_cost' => -1]]))
            ->assertSessionHasErrors([
                'allocations.0.container_number' => 'Isi nomor container tiap baris.',
                'allocations.0.quantity' => 'Jumlah harus lebih dari nol.',
                'allocations.0.freight_cost' => 'Biaya tidak boleh negatif.',
            ]);
        $this->actingAs($this->admin)->post($this->url('store'), $this->body([$purchase], [], ['departure_date' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['departure_date']);
        $this->actingAs($this->admin)->post($this->url('store'), $this->body([], []))->assertSessionHasErrors(['purchase_ids' => 'Tambahkan minimal satu nota.']);
    }

    public function test_only_ordered_notas_that_are_in_no_other_shipment_can_be_loaded(): void
    {
        $ordered = $this->orderedPurchase('10', 'INV-A');
        $this->draft([$ordered]);
        $draftPurchase = $this->store->run(fn (): Purchase => Purchase::factory()->create(['supplier_id' => $this->supplier->id]));

        $this->actingAs($this->admin)->post($this->url('store'), $this->body([$ordered]))
            ->assertSessionHas('error', "Nota {$ordered->number} tidak bisa dimuat: notanya harus sudah dipesan (dibayar) dan belum masuk pengiriman lain.");
        $this->actingAs($this->admin)->post($this->url('store'), $this->body([$draftPurchase]))
            ->assertSessionHas('error', "Nota {$draftPurchase->number} tidak bisa dimuat: notanya harus sudah dipesan (dibayar) dan belum masuk pengiriman lain.");
        $this->assertSame(1, $this->store->run(fn (): int => Shipment::query()->count()));
    }

    public function test_a_row_for_a_line_that_is_not_on_the_shipments_notas_is_refused(): void
    {
        $inShipment = $this->orderedPurchase('10', 'INV-A');
        $other = $this->orderedPurchase('10', 'INV-B');

        $this->actingAs($this->admin)->post($this->url('store'), $this->body([$inShipment], [$this->row($other, 'SPNU 1', '10')]))
            ->assertSessionHas('error', 'Ada baris packing list yang barangnya bukan dari nota di pengiriman ini. Muat ulang layar lalu coba lagi.');
    }

    public function test_a_draft_can_be_changed_and_its_packing_list_and_notas_are_replaced(): void
    {
        $first = $this->orderedPurchase('10', 'INV-A');
        $second = $this->orderedPurchase('20', 'INV-B');
        $shipment = $this->draft([$first], [$this->row($first, 'SPNU 1', '10')]);

        $body = $this->body([$second], [$this->row($second, 'SPNU 9', '20', '70000')]);
        unset($body['client_uuid']);
        $this->actingAs($this->admin)->put($this->url('update', ['shipment' => $shipment->id]), $body)->assertSessionHasNoErrors()->assertRedirect($this->url('show', ['shipment' => $shipment->id]));

        $this->assertNull($first->fresh()->shipment_id);
        $this->assertSame($shipment->id, $second->fresh()->shipment_id);
        $this->assertSame(['SPNU 9'], $this->store->run(fn (): array => $shipment->containers()->pluck('number')->all()));
    }

    public function test_a_shipment_that_has_departed_is_locked_and_cannot_be_cancelled(): void
    {
        $purchase = $this->orderedPurchase('10');
        $shipment = $this->draft([$purchase], [$this->row($purchase, 'SPNU 1', '10')], ['departure_date' => today()->toDateString()]);

        $body = $this->body([$purchase], [$this->row($purchase, 'SPNU 1', '10')]);
        unset($body['client_uuid']);
        $this->actingAs($this->admin)->put($this->url('update', ['shipment' => $shipment->id]), $body)->assertSessionHas('error', 'Pengiriman ini sudah berangkat atau dibatalkan, jadi tidak bisa diubah lagi.');
        $this->actingAs($this->admin)->get($this->url('edit', ['shipment' => $shipment->id]))->assertRedirect($this->url('show', ['shipment' => $shipment->id]));
        $this->actingAs($this->admin)->post($this->url('cancel', ['shipment' => $shipment->id]), ['reason' => 'Salah'])->assertSessionHas('error', 'Hanya pengiriman yang belum berangkat yang bisa dibatalkan.');
    }

    public function test_cancelling_a_draft_frees_its_notas_for_another_shipment(): void
    {
        $purchase = $this->orderedPurchase('10');
        $shipment = $this->draft([$purchase]);

        $this->actingAs($this->admin)->post($this->url('cancel', ['shipment' => $shipment->id]), ['reason' => ''])->assertSessionHasErrors(['reason' => 'Isi alasan pembatalan.']);
        $this->actingAs($this->admin)->post($this->url('cancel', ['shipment' => $shipment->id]), ['reason' => ' Ganti ekspedisi '])->assertSessionHasNoErrors();

        $cancelled = $this->store->run(fn (): Shipment => Shipment::query()->findOrFail($shipment->id));
        $this->assertSame(ShipmentStatus::Cancelled, $cancelled->status);
        $this->assertSame('Ganti ekspedisi', $cancelled->cancel_reason);
        $this->assertNull($purchase->fresh()->shipment_id);

        $this->draft([$purchase]);
    }

    public function test_a_nota_in_a_shipment_cannot_be_cancelled_until_it_is_taken_out(): void
    {
        $purchase = $this->orderedPurchase('10');
        $shipment = $this->draft([$purchase]);
        $cancelPurchase = fn () => $this->actingAs($this->admin)->post(route('tenant.purchases.cancel', ['tenant' => $this->store->code, 'purchase' => $purchase->id]), ['reason' => 'Salah']);

        $cancelPurchase()->assertSessionHas('error', "Nota ini sudah masuk pengiriman {$shipment->number}. Keluarkan dulu dari pengiriman itu sebelum dibatalkan.");
        $this->actingAs($this->admin)->get(route('tenant.purchases.show', ['tenant' => $this->store->code, 'purchase' => $purchase->id]))
            ->assertInertia(fn (Assert $page) => $page->where('purchase.is_cancellable', false)->where('purchase.shipment_number', $shipment->number));

        $this->actingAs($this->admin)->post($this->url('cancel', ['shipment' => $shipment->id]), ['reason' => 'Salah']);
        $cancelPurchase()->assertSessionHasNoErrors();
    }

    public function test_a_switched_off_carrier_cannot_be_chosen_for_a_new_shipment(): void
    {
        $purchase = $this->orderedPurchase('10');
        $this->store->run(fn () => $this->carrier->update(['is_active' => false]));

        $this->actingAs($this->admin)->post($this->url('store'), $this->body([$purchase]))->assertSessionHas('error', 'Ekspedisi ini sudah dinonaktifkan. Pilih ekspedisi lain.');
    }

    public function test_the_form_offers_the_ordered_notas_that_are_free_and_the_ones_the_draft_already_holds(): void
    {
        $free = $this->orderedPurchase('10', 'INV-A');
        $held = $this->orderedPurchase('20', 'INV-B');
        $taken = $this->orderedPurchase('30', 'INV-C');
        $this->draft([$taken]);
        $shipment = $this->draft([$held]);
        $this->store->run(fn () => Purchase::factory()->create(['supplier_id' => $this->supplier->id]));
        $numbers = fn (array $expected) => fn (Assert $page) => $page->where('available_purchases', fn ($rows) => collect($rows)->pluck('number')->all() === $expected);

        $this->actingAs($this->admin)->get($this->url('create'))->assertInertia($numbers([$free->number]));
        $this->actingAs($this->admin)->get($this->url('edit', ['shipment' => $shipment->id]))->assertInertia($numbers([$free->number, $held->number]));
        $this->actingAs($this->admin)->get($this->url('create'))->assertInertia(fn (Assert $page) => $page
            ->where('available_purchases.0.supplier_name', 'PT Semen Kuat')
            ->where('available_purchases.0.items.0.name', 'Semen')
            ->where('available_purchases.0.items.0.quantity', '10.000'));
    }

    public function test_the_detail_shows_each_container_with_its_lines_and_what_the_carrier_charged(): void
    {
        $purchase = $this->orderedPurchase('1000');
        $shipment = $this->draft([$purchase], [$this->row($purchase, 'SPNU 1', '600', '300000'), $this->row($purchase, 'SPNU 2', '400', '200000')], ['departure_date' => today()->toDateString()]);

        $this->actingAs($this->admin)->get($this->url('show', ['shipment' => $shipment->id]))->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Shipments/Show')
            ->where('shipment.status', 'shipped')
            ->where('shipment.total_freight_cost', '500000.00')
            ->where('shipment.expected_arrival_date', today()->addDays(10)->toDateString())
            ->where('shipment.containers.0.number', 'SPNU 1')
            ->where('shipment.containers.0.total_freight_cost', '300000.00')
            ->where('shipment.containers.0.lines.0.quantity', '600.000')
            ->where('shipment.containers.1.lines.0.product_name', 'Semen'));
    }

    public function test_the_list_shows_the_total_charged_and_can_be_searched_by_container_and_narrowed(): void
    {
        $first = $this->orderedPurchase('10', 'INV-A');
        $second = $this->orderedPurchase('20', 'INV-B');
        $this->draft([$first], [$this->row($first, 'SPNU 111', '10', '70000')]);
        $this->draft([$second], [$this->row($second, 'SPNU 222', '20', '90000')], ['departure_date' => today()->toDateString()]);
        $numbers = fn (array $expected) => fn (Assert $page) => $page->where('shipments.data', fn ($rows) => collect($rows)->pluck('containers_count')->all() === $expected);

        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('shipments.data.0.total_freight_cost', '90000.00')
            ->where('shipments.data.1.total_freight_cost', '70000.00'));
        $this->actingAs($this->admin)->get($this->url('index', ['search' => '111']))->assertInertia(fn (Assert $page) => $page->has('shipments.data', 1)->where('shipments.data.0.total_freight_cost', '70000.00'));
        $this->actingAs($this->admin)->get($this->url('index', ['status' => 'shipped']))->assertInertia($numbers([1]));
        $this->actingAs($this->admin)->get($this->url('index', ['carrier' => $this->carrier->id]))->assertInertia(fn (Assert $page) => $page->has('shipments.data', 2));
    }

    public function test_a_double_submit_returns_the_first_shipment(): void
    {
        $purchase = $this->orderedPurchase('10');
        $body = $this->body([$purchase]);

        $this->actingAs($this->admin)->post($this->url('store'), $body)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post($this->url('store'), $body)->assertSessionHasNoErrors();

        $this->assertSame(1, $this->store->run(fn (): int => Shipment::query()->count()));
    }
}
