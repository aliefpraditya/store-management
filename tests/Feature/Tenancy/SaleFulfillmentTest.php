<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DeliveryNoteStatus;
use App\Enums\Permission;
use App\Enums\SaleFulfillment;
use App\Enums\UserRole;
use App\Exceptions\SaleException;
use App\Models\CashShift;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\CodSaleService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class SaleFulfillmentTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_fulfillment';

    private Store $store;

    private User $cashier;

    private User $admin;

    private Product $brush;

    private CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_fulfillment', 'receipt_prefix' => 'TFU']);
        $this->cashier = $this->storeUser();
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->brush = $this->sellableProduct('Kuas', '100');
        $this->shift = $this->openShiftFor($this->cashier);
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.cashier.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

    /**
     * A sale of ten brushes (100.000) paid in cash as the payment window sends it; `$overrides` replaces any field.
     *
     * @return array<string, mixed>
     */
    private function body(array $overrides = []): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'customer_name' => 'Pak Budi',
            'items' => [['product_unit_id' => $this->unitOf($this->brush, 'PCS')->id, 'quantity' => 10]],
            'payments' => [['method' => 'cash', 'amount' => 100000]],
            ...$overrides,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function recipient(): array
    {
        return ['recipient_name' => 'Pak Budi', 'recipient_address' => 'Jl. Mawar 5'];
    }

    private function sell(array $overrides = []): Sale
    {
        return $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, $this->body($overrides)));
    }

    private function noteCount(): int
    {
        return $this->store->run(fn (): int => DeliveryNote::query()->count());
    }

    private function fresh(Sale $sale): Sale
    {
        return $this->store->run(fn (): Sale => Sale::query()->with('deliveryNotes')->findOrFail($sale->id));
    }

    public function test_a_sale_taken_at_the_store_has_no_delivery_note(): void
    {
        $sale = $this->sell(['fulfillment' => 'in_store']);

        $this->assertSame(SaleFulfillment::InStore, $this->fresh($sale)->fulfillment);
        $this->assertSame(0, $this->noteCount());
        $this->assertFalse($this->fresh($sale)->isDeliveryPending());
    }

    public function test_a_sale_without_a_choice_is_taken_at_the_store_and_a_cod_one_is_delivered(): void
    {
        $plain = $this->sell();
        $cod = $this->sell(['payments' => [['method' => 'cod', 'amount' => 100000]], 'delivery_note' => $this->recipient()]);

        $this->assertSame([SaleFulfillment::InStore, SaleFulfillment::Delivery], [$this->fresh($plain)->fulfillment, $this->fresh($cod)->fulfillment]);
    }

    public function test_a_delivered_sale_paid_in_advance_gets_its_note_and_stays_not_delivered_until_it_is(): void
    {
        $sale = $this->sell(['fulfillment' => 'delivery', 'delivery_note' => $this->recipient()]);

        $this->assertSame(1, $this->noteCount());
        $this->assertTrue($this->fresh($sale)->isDeliveryPending());
        $this->assertSame(1, $this->store->run(fn (): int => Sale::query()->pendingDelivery()->count()));

        $this->actingAs($this->cashier)->postJson($this->url('cod.delivered', ['sale' => $sale->id]))->assertOk();

        $this->assertSame(DeliveryNoteStatus::Delivered, $this->store->run(fn (): DeliveryNoteStatus => DeliveryNote::query()->sole()->status));
        $this->assertFalse($this->fresh($sale)->isDeliveryPending());
        $this->assertSame(0, $this->store->run(fn (): int => Sale::query()->pendingDelivery()->count()));
    }

    public function test_a_delivered_sale_cannot_be_saved_without_who_and_where_to_deliver(): void
    {
        $post = fn (array $overrides) => $this->actingAs($this->cashier)->post($this->url('sales.store'), $this->body(['fulfillment' => 'delivery', ...$overrides]));

        $post(['delivery_note' => null])->assertSessionHasErrors(['delivery_note.recipient_name', 'delivery_note.recipient_address']);
        $post(['delivery_note' => ['recipient_name' => 'Pak Budi']])->assertSessionHasErrors(['delivery_note.recipient_address' => 'Alamat pengantaran wajib diisi untuk nota yang diantar.']);

        $this->assertSame(0, $this->store->run(fn (): int => Sale::query()->count()));
    }

    public function test_a_sale_taken_at_the_store_needs_no_recipient(): void
    {
        $this->actingAs($this->cashier)->post($this->url('sales.store'), $this->body(['fulfillment' => 'in_store']))->assertSessionHasNoErrors()->assertSessionHas('last_sale');
    }

    public function test_cod_is_refused_for_a_sale_taken_at_the_store(): void
    {
        $this->expectException(SaleException::class);
        $this->expectExceptionMessage('COD hanya untuk nota yang diantar.');

        $this->sell(['fulfillment' => 'in_store', 'payments' => [['method' => 'cod', 'amount' => 100000]]]);
    }

    public function test_an_unknown_fulfillment_is_refused(): void
    {
        $this->actingAs($this->cashier)->post($this->url('sales.store'), $this->body(['fulfillment' => 'drone']))->assertSessionHasErrors('fulfillment');
    }

    public function test_the_cashier_screen_offers_both_ways_and_counts_what_is_not_delivered_in_the_shift(): void
    {
        $this->sell(['fulfillment' => 'delivery', 'delivery_note' => $this->recipient()]);
        $this->sell(['fulfillment' => 'in_store']);

        $this->actingAs($this->cashier)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('fulfillment_options', [['value' => 'in_store', 'label' => 'Ambil di toko'], ['value' => 'delivery', 'label' => 'Diantar']])
            ->where('pending_delivery_count', 1));
    }

    public function test_the_shift_receipts_say_how_each_was_handed_over_and_what_is_not_delivered(): void
    {
        $delivered = $this->sell(['fulfillment' => 'delivery', 'delivery_note' => $this->recipient()]);
        $inStore = $this->sell(['fulfillment' => 'in_store']);

        $response = $this->actingAs($this->cashier)->getJson($this->url('receipts'))->assertOk();
        $rows = collect($response->json('data'))->keyBy('number');

        $this->assertSame(['delivery', true], [$rows[$delivered->number]['fulfillment'], $rows[$delivered->number]['is_delivery_pending']]);
        $this->assertSame(['in_store', false], [$rows[$inStore->number]['fulfillment'], $rows[$inStore->number]['is_delivery_pending']]);
        $this->assertStringEndsWith('/receipts/'.$inStore->id.'/delivery-mode', $rows[$inStore->number]['delivery_mode_url']);
    }

    public function test_a_sale_points_to_the_delivery_note_to_finish_only_while_one_is_on_its_way(): void
    {
        $withNote = $this->sell(['fulfillment' => 'delivery', 'delivery_note' => $this->recipient()]);
        $finished = $this->sell(['fulfillment' => 'delivery', 'delivery_note' => $this->recipient()]);
        $withoutNote = $this->sell(['fulfillment' => 'in_store']);
        $this->store->run(fn () => app(CodSaleService::class)->markDelivered($finished));
        $this->actingAs($this->cashier)->postJson($this->url('receipts.delivery-mode', ['sale' => $withoutNote->id]))->assertOk();

        $rows = collect($this->actingAs($this->cashier)->getJson($this->url('receipts'))->json('data'))->keyBy('number');

        $this->assertSame(
            [$withNote->number => [true, true], $finished->number => [false, false], $withoutNote->number => [true, false]],
            collect([$withNote, $finished, $withoutNote])->mapWithKeys(fn (Sale $sale): array => [$sale->number => [$rows[$sale->number]['is_delivery_pending'], $rows[$sale->number]['has_open_delivery_note']]])->all(),
        );
        $this->assertArrayNotHasKey('delivered_url', $rows[$withNote->number]);
    }

    public function test_a_receipt_carries_its_delivery_note_with_a_link_only_for_those_who_may_view_delivery_notes(): void
    {
        $this->sell(['fulfillment' => 'delivery', 'delivery_note' => $this->recipient()]);
        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->sole());

        $without = $this->actingAs($this->cashier)->getJson($this->url('receipts'))->json('data.0.delivery_note');

        $this->assertSame([$note->number, 'draft', 'Draft', null], [$without['number'], $without['status'], $without['status_label'], $without['show_url']]);
        $this->assertStringEndsWith('/cashier/delivery-notes/'.$note->id.'/print', $without['print_url']);

        $central = DB::connection('mysql');
        $central->table('permission_user')->insert(['user_id' => $this->cashier->id, 'permission_id' => $central->table('permissions')->where('name', Permission::DeliveryNotesView->value)->value('id')]);

        $with = $this->actingAs($this->cashier)->getJson($this->url('receipts'))->json('data.0.delivery_note');

        $this->assertStringEndsWith('/delivery-notes/'.$note->id, $with['show_url']);
    }

    public function test_a_receipt_without_a_delivery_note_carries_none_and_a_cancelled_one_does_not_count(): void
    {
        $inStore = $this->sell(['fulfillment' => 'in_store']);
        $cancelled = $this->sell(['fulfillment' => 'delivery', 'delivery_note' => $this->recipient()]);
        $this->store->run(fn () => DeliveryNote::query()->where('sale_id', $cancelled->id)->update(['status' => DeliveryNoteStatus::Cancelled]));

        $rows = collect($this->actingAs($this->cashier)->getJson($this->url('receipts'))->json('data'))->keyBy('number');

        $this->assertNull($rows[$inStore->number]['delivery_note']);
        $this->assertNull($rows[$cancelled->number]['delivery_note']);
    }

    public function test_the_delivery_list_holds_every_delivered_receipt_not_delivered_yet_and_only_a_cod_one_can_be_voided(): void
    {
        $cod = $this->sell(['payments' => [['method' => 'cod', 'amount' => 100000]], 'delivery_note' => $this->recipient()]);
        $paid = $this->sell(['fulfillment' => 'delivery', 'delivery_note' => $this->recipient()]);
        $this->sell(['fulfillment' => 'in_store']);
        $done = $this->sell(['fulfillment' => 'delivery', 'delivery_note' => $this->recipient()]);
        $this->store->run(fn () => app(CodSaleService::class)->markDelivered($done));

        $response = $this->actingAs($this->cashier)->getJson($this->url('cod.index'))->assertOk()->assertJsonPath('meta.total', 2);
        $rows = collect($response->json('data'))->keyBy('number');

        $this->assertEqualsCanonicalizing([$cod->number, $paid->number], $rows->keys()->all());
        $this->assertSame([true, '100000.00'], [$rows[$cod->number]['is_cod'], $rows[$cod->number]['cod_amount']]);
        $this->assertSame([false, '0.00', '100000.00'], [$rows[$paid->number]['is_cod'], $rows[$paid->number]['cod_amount'], $rows[$paid->number]['total']]);
        $this->assertSame('Draft', $rows[$paid->number]['delivery_note']['status_label']);
    }

    public function test_a_cashier_turns_their_own_in_store_sale_into_a_delivery(): void
    {
        $sale = $this->sell(['fulfillment' => 'in_store']);

        $this->actingAs($this->cashier)->postJson($this->url('receipts.delivery-mode', ['sale' => $sale->id]))->assertOk();

        $this->assertSame(SaleFulfillment::Delivery, $this->fresh($sale)->fulfillment);
        $this->assertTrue($this->fresh($sale)->isDeliveryPending());
    }

    public function test_another_cashier_cannot_turn_someones_sale_into_a_delivery(): void
    {
        $sale = $this->sell(['fulfillment' => 'in_store']);

        $this->actingAs($this->storeUser())->postJson($this->url('receipts.delivery-mode', ['sale' => $sale->id]))->assertForbidden();

        $this->assertSame(SaleFulfillment::InStore, $this->fresh($sale)->fulfillment);
    }

    public function test_a_voided_sale_cannot_be_turned_into_a_delivery(): void
    {
        $sale = $this->sell(['fulfillment' => 'in_store']);
        $this->store->run(fn () => Sale::query()->whereKey($sale->id)->update(['status' => 'voided']));

        $this->actingAs($this->cashier)->postJson($this->url('receipts.delivery-mode', ['sale' => $sale->id]))->assertUnprocessable();
    }

    public function test_making_a_delivery_note_marks_a_store_sale_as_delivered(): void
    {
        $sale = $this->sell(['fulfillment' => 'in_store']);
        $line = $this->store->run(fn () => $sale->items()->firstOrFail());

        $this->actingAs($this->cashier)->postJson($this->url('receipts.delivery-notes.store', ['sale' => $sale->id]), [
            'client_uuid' => (string) Str::uuid(),
            ...$this->recipient(),
            'items' => [['sale_item_id' => $line->id, 'quantity' => 3]],
        ])->assertCreated();

        $this->assertSame(SaleFulfillment::Delivery, $this->fresh($sale)->fulfillment);
    }

    public function test_delivery_can_only_be_confirmed_for_a_delivered_sale(): void
    {
        $sale = $this->sell(['fulfillment' => 'in_store']);

        $this->actingAs($this->cashier)->postJson($this->url('cod.delivered', ['sale' => $sale->id]))->assertUnprocessable()
            ->assertJsonPath('message', 'Nota ini diambil di toko, tidak ada yang diantar.');
    }

    public function test_the_admin_history_marks_and_filters_what_is_not_delivered_and_the_menu_counts_it(): void
    {
        $pending = $this->sell(['fulfillment' => 'delivery', 'delivery_note' => $this->recipient()]);
        $done = $this->sell(['fulfillment' => 'delivery', 'delivery_note' => $this->recipient()]);
        $inStore = $this->sell(['fulfillment' => 'in_store']);
        $this->store->run(fn () => app(CodSaleService::class)->markDelivered($done));
        $index = route('tenant.sales.index', ['tenant' => $this->store->code]);

        $this->actingAs($this->admin)->get($index.'?from=&to=')->assertInertia(fn (Assert $page) => $page
            ->where('menu_badges.sales', 1)
            ->where('sales.data', fn ($rows) => $rows->pluck('is_delivery_pending', 'number')->all() === [$inStore->number => false, $done->number => false, $pending->number => true]));

        $this->actingAs($this->admin)->get($index.'?from=&to=&delivery=pending')->assertInertia(fn (Assert $page) => $page
            ->where('sales.data', fn ($rows) => $rows->pluck('number')->all() === [$pending->number])
            ->where('filters.delivery', 'pending'));
    }

    public function test_the_menu_count_of_undelivered_sales_is_only_for_those_who_may_see_the_history(): void
    {
        $this->sell(['fulfillment' => 'delivery', 'delivery_note' => $this->recipient()]);

        $this->actingAs($this->cashier)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page->where('menu_badges.sales', 0));
    }

    public function test_an_admin_turns_a_store_sale_into_a_delivery_from_its_page(): void
    {
        $sale = $this->sell(['fulfillment' => 'in_store']);
        $show = route('tenant.sales.show', ['tenant' => $this->store->code, 'sale' => $sale->id]);

        $this->actingAs($this->admin)->get($show)->assertInertia(fn (Assert $page) => $page
            ->where('sale.fulfillment', 'in_store')
            ->where('sale.is_delivery_pending', false)
            ->where('urls.delivery_mode', route('tenant.sales.delivery-mode', ['tenant' => $this->store->code, 'sale' => $sale->id])));

        $this->actingAs($this->admin)->post(route('tenant.sales.delivery-mode', ['tenant' => $this->store->code, 'sale' => $sale->id]))
            ->assertSessionHas('success');

        $this->actingAs($this->admin)->get($show)->assertInertia(fn (Assert $page) => $page
            ->where('sale.fulfillment', 'delivery')
            ->where('sale.is_delivery_pending', true));
    }

    public function test_a_cashier_cannot_use_the_admin_delivery_mode_route(): void
    {
        $sale = $this->sell(['fulfillment' => 'in_store']);

        $this->actingAs($this->cashier)->post(route('tenant.sales.delivery-mode', ['tenant' => $this->store->code, 'sale' => $sale->id]))->assertForbidden();

        $this->assertSame(SaleFulfillment::InStore, $this->fresh($sale)->fulfillment);
    }
}
