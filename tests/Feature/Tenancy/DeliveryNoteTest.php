<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DeliveryNoteStatus;
use App\Enums\UserRole;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class DeliveryNoteTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_delivery_notes';

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_delivery_notes', 'receipt_prefix' => 'TDN', 'address' => 'Jl. Uji 1', 'phone' => '021-123']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.delivery-notes.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

    /**
     * A completed receipt with one line of ten pieces and one of four, ready to be delivered.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: Sale, 1: SaleItem, 2: SaleItem}
     */
    private function makeSale(array $attributes = []): array
    {
        return $this->store->run(function () use ($attributes): array {
            $sale = Sale::factory()->create(['customer_name' => 'Pak Budi', ...$attributes]);
            $paint = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_name' => 'Cat Tembok', 'unit_name' => 'PAIL', 'quantity' => 10]);
            $brush = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_name' => 'Kuas', 'unit_name' => 'PCS', 'quantity' => 4]);

            return [$sale, $paint, $brush];
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(Sale $sale, array $items, array $overrides = []): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'recipient_name' => 'Pak Budi',
            'recipient_phone' => '0812-0000',
            'recipient_address' => 'Jl. Mawar 5',
            'driver_name' => 'Joko',
            'vehicle_number' => 'B 1234 XY',
            'notes' => 'Taruh di gudang belakang',
            'items' => array_map(fn (array $item): array => ['sale_item_id' => $item[0]->id, 'quantity' => $item[1]], $items),
            ...$overrides,
        ];
    }

    private function createNote(Sale $sale, array $items, array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, $items, $overrides));
    }

    private function note(?int $id = null): DeliveryNote
    {
        return $this->store->run(fn () => $id === null ? DeliveryNote::query()->latest('id')->with('items')->firstOrFail() : DeliveryNote::query()->with('items')->findOrFail($id));
    }

    /**
     * A shipped note for the sale with the given quantities.
     */
    private function shippedNote(Sale $sale, array $items): DeliveryNote
    {
        $this->createNote($sale, $items);
        $note = $this->note();
        $this->actingAs($this->admin)->post($this->url('ship', ['delivery_note' => $note->id]));

        return $this->note($note->id);
    }

    /**
     * @param  array<int, array{0: int, 1: string|int, 2: string|int}>  $rows  item id, delivered, returned
     */
    private function complete(DeliveryNote $note, array $rows): TestResponse
    {
        return $this->actingAs($this->admin)->post($this->url('complete', ['delivery_note' => $note->id]), [
            'results' => array_map(fn (array $row): array => ['item_id' => $row[0], 'quantity_delivered' => $row[1], 'quantity_returned' => $row[2]], $rows),
        ]);
    }

    public function test_only_admins_may_use_delivery_notes(): void
    {
        [$sale, $paint] = $this->makeSale();
        $note = $this->store->run(fn () => DeliveryNote::factory()->create(['sale_id' => $sale->id]));

        $this->actingAs($this->storeUser());
        $this->get($this->url('index'))->assertForbidden();
        $this->get($this->url('create'))->assertForbidden();
        $this->getJson($this->url('sales', ['q' => 'x']))->assertForbidden();
        $this->post($this->url('store'), $this->payload($sale, [[$paint, 1]]))->assertForbidden();
        $this->get($this->url('show', ['delivery_note' => $note->id]))->assertForbidden();
        $this->post($this->url('ship', ['delivery_note' => $note->id]))->assertForbidden();
        $this->postJson($this->url('print', ['delivery_note' => $note->id]))->assertForbidden();
        $this->get($this->url('sheet', ['delivery_note' => $note->id]))->assertForbidden();

        $this->assertSame(1, $this->store->run(fn () => DeliveryNote::query()->count()));
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get($this->url('index'))->assertRedirect(route('login'));
    }

    public function test_a_note_is_made_from_a_receipt_as_a_numbered_draft(): void
    {
        [$sale, $paint, $brush] = $this->makeSale();

        $response = $this->createNote($sale, [[$paint, 6], [$brush, 0]]);

        $note = $this->note();
        $response->assertRedirect($this->url('show', ['delivery_note' => $note->id]))->assertSessionHas('success', 'Surat jalan dibuat.');

        $this->assertSame('SJ/TDN/'.now()->format('ym').'/0001', $note->number);
        $this->assertSame([DeliveryNoteStatus::Draft, $sale->id, 'Pak Budi', '0812-0000', 'Jl. Mawar 5', 'Joko', 'B 1234 XY'], [
            $note->status, $note->sale_id, $note->recipient_name, $note->recipient_phone, $note->recipient_address, $note->driver_name, $note->vehicle_number,
        ]);
        $this->assertSame([$this->admin->id, $this->admin->name, today()->toDateString()], [$note->created_by, $note->created_by_name, $note->business_date->toDateString()]);
        $this->assertCount(1, $note->items);
        $this->assertSame(['Cat Tembok', 'PAIL', '6.000', '0.000', '0.000', $paint->id], [
            $note->items[0]->product_name, $note->items[0]->unit_name, $note->items[0]->quantity, $note->items[0]->quantity_delivered, $note->items[0]->quantity_returned, $note->items[0]->sale_item_id,
        ]);
    }

    public function test_numbers_run_on_within_a_month(): void
    {
        [$sale, $paint] = $this->makeSale();

        $this->createNote($sale, [[$paint, 1]]);
        $this->createNote($sale, [[$paint, 1]]);

        $this->assertSame(
            ['SJ/TDN/'.now()->format('ym').'/0001', 'SJ/TDN/'.now()->format('ym').'/0002'],
            $this->store->run(fn () => DeliveryNote::query()->orderBy('id')->pluck('number')->all()),
        );
    }

    public function test_a_note_never_touches_the_stock(): void
    {
        [$sale, $paint] = $this->makeSale();
        $this->store->run(fn () => Stock::query()->insert(['product_id' => $paint->product_id, 'warehouse_id' => $sale->warehouse_id, 'quantity' => 20]));

        $this->createNote($sale, [[$paint, 6]]);
        $note = $this->note();
        $this->actingAs($this->admin)->post($this->url('ship', ['delivery_note' => $note->id]));
        $this->complete($this->note($note->id), [[$note->items[0]->id, 2, 4]]);

        $this->assertSame('20.000', $this->store->run(fn () => (string) Stock::query()->where('product_id', $paint->product_id)->value('quantity')));
        $this->assertSame(0, $this->store->run(fn () => StockMovement::query()->count()));
    }

    public function test_the_note_input_is_validated(): void
    {
        [$sale, $paint] = $this->makeSale();
        $otherSaleItem = $this->makeSale()[1];

        $this->createNote($sale, [[$paint, 1]], ['recipient_name' => ''])->assertSessionHasErrors(['recipient_name' => 'Nama penerima wajib diisi.']);
        $this->createNote($sale, [], ['items' => []])->assertSessionHasErrors('items');
        $this->createNote($sale, [[$paint, -1]])->assertSessionHasErrors('items.0.quantity');
        $this->createNote($sale, [[$paint, 1.2345]])->assertSessionHasErrors('items.0.quantity');
        $this->createNote($sale, [[$paint, 1]], ['sale_id' => 9999])->assertSessionHasErrors('sale_id');
        $this->createNote($sale, [[$otherSaleItem, 1]])->assertSessionHas('error', 'Ada barang yang tidak ada di nota ini.');

        $this->assertSame(0, $this->store->run(fn () => DeliveryNote::query()->count()));
    }

    public function test_a_note_needs_at_least_one_line_above_zero(): void
    {
        [$sale, $paint, $brush] = $this->makeSale();

        $this->createNote($sale, [[$paint, 0], [$brush, 0]])->assertSessionHas('error', 'Pilih minimal satu barang yang diantar.');

        $this->assertSame(0, $this->store->run(fn () => DeliveryNote::query()->count()));
    }

    public function test_only_a_completed_receipt_can_have_a_note(): void
    {
        [$sale, $paint] = $this->makeSale(['status' => 'voided']);

        $this->createNote($sale, [[$paint, 1]])->assertSessionHas('error', 'Surat jalan hanya bisa dibuat dari nota yang selesai, bukan nota void.');
    }

    public function test_a_receipt_can_be_delivered_in_parts_but_never_more_than_it_holds(): void
    {
        [$sale, $paint] = $this->makeSale();

        $this->createNote($sale, [[$paint, 6]])->assertSessionHas('success');
        $this->createNote($sale, [[$paint, 5]])->assertSessionHas('error', 'Cat Tembok melebihi sisa nota yang belum diantar, sisa 4 PAIL.');
        $this->createNote($sale, [[$paint, 4]])->assertSessionHas('success');

        $this->assertSame(2, $this->store->run(fn () => DeliveryNote::query()->count()));
        $this->createNote($sale, [[$paint, 1]])->assertSessionHas('error');
    }

    public function test_a_cancelled_note_gives_its_quantity_back(): void
    {
        [$sale, $paint] = $this->makeSale();
        $this->createNote($sale, [[$paint, 10]]);
        $this->actingAs($this->admin)->post($this->url('cancel', ['delivery_note' => $this->note()->id]));

        $this->createNote($sale, [[$paint, 10]])->assertSessionHas('success');
    }

    public function test_a_note_is_shipped_once_from_draft(): void
    {
        [$sale, $paint] = $this->makeSale();
        $this->createNote($sale, [[$paint, 3]]);
        $note = $this->note();

        $this->actingAs($this->admin)->post($this->url('ship', ['delivery_note' => $note->id]))->assertSessionHas('success');
        $shipped = $this->note($note->id);
        $this->assertSame(DeliveryNoteStatus::Shipped, $shipped->status);
        $this->assertNotNull($shipped->shipped_at);

        $this->actingAs($this->admin)->post($this->url('ship', ['delivery_note' => $note->id]))->assertSessionHas('error', 'Surat jalan tidak bisa dikirim karena berstatus Dikirim. Muat ulang halaman untuk melihat status terbarunya.');
    }

    public function test_a_delivery_where_everything_arrived_is_delivered(): void
    {
        [$sale, $paint, $brush] = $this->makeSale();
        $note = $this->shippedNote($sale, [[$paint, 6], [$brush, 2]]);

        $this->complete($note, [[$note->items[0]->id, 6, 0], [$note->items[1]->id, 2, 0]])->assertSessionHas('success', 'Pengiriman diselesaikan.');

        $done = $this->note($note->id);
        $this->assertSame(DeliveryNoteStatus::Delivered, $done->status);
        $this->assertNotNull($done->completed_at);
        $this->assertSame(['6.000', '0.000'], [$done->items[0]->quantity_delivered, $done->items[0]->quantity_returned]);
    }

    public function test_a_delivery_where_everything_came_back_is_returned(): void
    {
        [$sale, $paint] = $this->makeSale();
        $note = $this->shippedNote($sale, [[$paint, 6]]);

        $this->complete($note, [[$note->items[0]->id, 0, 6]]);

        $this->assertSame(DeliveryNoteStatus::Returned, $this->note($note->id)->status);
    }

    public function test_a_delivery_with_some_returned_is_partially_delivered(): void
    {
        [$sale, $paint, $brush] = $this->makeSale();
        $note = $this->shippedNote($sale, [[$paint, 6], [$brush, 2]]);

        $this->complete($note, [[$note->items[0]->id, 4, 2], [$note->items[1]->id, 2, 0]]);

        $done = $this->note($note->id);
        $this->assertSame(DeliveryNoteStatus::PartiallyDelivered, $done->status);
        $this->assertSame(['4.000', '2.000'], [$done->items[0]->quantity_delivered, $done->items[0]->quantity_returned]);
    }

    public function test_the_result_must_add_up_to_what_was_sent_for_every_item(): void
    {
        [$sale, $paint, $brush] = $this->makeSale();
        $note = $this->shippedNote($sale, [[$paint, 6], [$brush, 2]]);

        $this->complete($note, [[$note->items[0]->id, 5, 0], [$note->items[1]->id, 2, 0]])
            ->assertSessionHas('error', 'Cat Tembok: jumlah terkirim ditambah kembali harus sama dengan jumlah yang dikirim.');
        $this->complete($note, [[$note->items[0]->id, 6, 0]])->assertSessionHas('error', 'Isi hasil pengiriman untuk setiap barang.');

        $this->assertSame(DeliveryNoteStatus::Shipped, $this->note($note->id)->status);
        $this->assertSame('0.000', $this->note($note->id)->items[0]->quantity_delivered);
    }

    public function test_only_a_shipped_note_can_be_completed(): void
    {
        [$sale, $paint] = $this->makeSale();
        $this->createNote($sale, [[$paint, 3]]);
        $note = $this->note();

        $this->complete($note, [[$note->items[0]->id, 3, 0]])->assertSessionHas('error', 'Surat jalan tidak bisa diselesaikan karena berstatus Draft. Muat ulang halaman untuk melihat status terbarunya.');
    }

    public function test_a_draft_or_shipped_note_can_be_cancelled_but_a_finished_one_cannot(): void
    {
        [$sale, $paint] = $this->makeSale();

        $this->createNote($sale, [[$paint, 1]]);
        $draft = $this->note();
        $this->actingAs($this->admin)->post($this->url('cancel', ['delivery_note' => $draft->id]))->assertSessionHas('success', 'Surat jalan dibatalkan.');
        $this->assertSame(DeliveryNoteStatus::Cancelled, $this->note($draft->id)->status);

        $shipped = $this->shippedNote($sale, [[$paint, 1]]);
        $this->actingAs($this->admin)->post($this->url('cancel', ['delivery_note' => $shipped->id]))->assertSessionHas('success');

        $finished = $this->shippedNote($sale, [[$paint, 1]]);
        $this->complete($finished, [[$finished->items[0]->id, 1, 0]]);
        $this->actingAs($this->admin)->post($this->url('cancel', ['delivery_note' => $finished->id]))->assertSessionHas('error');
        $this->assertSame(DeliveryNoteStatus::Delivered, $this->note($finished->id)->status);
    }

    public function test_the_first_print_is_the_original_and_later_ones_are_copies(): void
    {
        [$sale, $paint] = $this->makeSale();
        $this->createNote($sale, [[$paint, 6]]);
        $printUrl = $this->url('print', ['delivery_note' => $this->note()->id]);

        $this->actingAs($this->admin)->postJson($printUrl)->assertOk()->assertJsonPath('data.is_copy', false)->assertJsonPath('data.print_count', 1);
        $this->actingAs($this->admin)->postJson($printUrl)->assertOk()->assertJsonPath('data.is_copy', true)->assertJsonPath('data.print_count', 2);
    }

    public function test_the_sheet_is_the_note_without_prices_and_says_copy_after_the_first_print(): void
    {
        [$sale, $paint] = $this->makeSale(['number' => 'INV/1']);
        $this->createNote($sale, [[$paint, 6]]);
        $note = $this->note();
        $printUrl = $this->url('print', ['delivery_note' => $note->id]);

        $firstSheetUrl = $this->actingAs($this->admin)->postJson($printUrl)->json('data.sheet_url');
        $this->actingAs($this->admin)->get($firstSheetUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Print/DeliveryNote')
            ->where('note.number', $note->number)
            ->where('note.is_copy', false)
            ->where('note.created_at', $note->created_at->toIso8601String())
            ->where('note.recipient_name', 'Pak Budi')
            ->where('note.sale.number', 'INV/1')
            ->where('note.store.address', 'Jl. Uji 1')
            ->where('note.items.0.name', 'Cat Tembok')
            ->missing('note.items.0.unit_price')
            ->missing('note.items.0.subtotal'));

        $secondSheetUrl = $this->actingAs($this->admin)->postJson($printUrl)->json('data.sheet_url');
        $this->actingAs($this->admin)->get($secondSheetUrl)->assertInertia(fn (Assert $page) => $page->where('note.is_copy', true));
    }

    public function test_a_sheet_keeps_saying_what_its_own_print_was_even_after_later_prints(): void
    {
        [$sale, $paint] = $this->makeSale();
        $this->createNote($sale, [[$paint, 6]]);
        $printUrl = $this->url('print', ['delivery_note' => $this->note()->id]);

        $originalSheetUrl = $this->actingAs($this->admin)->postJson($printUrl)->json('data.sheet_url');
        $this->actingAs($this->admin)->postJson($printUrl);

        $this->actingAs($this->admin)->get($originalSheetUrl)->assertInertia(fn (Assert $page) => $page->where('note.is_copy', false));
        $this->assertSame(2, $this->note()->print_count);
    }

    public function test_the_sheet_cannot_be_opened_without_the_address_a_print_gave(): void
    {
        [$sale, $paint] = $this->makeSale();
        $this->createNote($sale, [[$paint, 6]]);
        $note = $this->note();
        $signedSheetUrl = $this->actingAs($this->admin)->postJson($this->url('print', ['delivery_note' => $note->id]))->json('data.sheet_url');

        $this->actingAs($this->admin)->get($this->url('sheet', ['delivery_note' => $note->id]))->assertForbidden();
        $this->actingAs($this->admin)->get(str_replace('print=1', 'print=2', $signedSheetUrl))->assertForbidden();
        $this->assertSame(1, $this->note()->print_count);
    }

    public function test_a_cancelled_note_can_still_be_printed_and_says_so_on_the_sheet(): void
    {
        [$sale, $paint] = $this->makeSale();
        $this->createNote($sale, [[$paint, 6]]);
        $note = $this->note();
        $this->actingAs($this->admin)->post($this->url('cancel', ['delivery_note' => $note->id]));

        $sheetUrl = $this->actingAs($this->admin)->postJson($this->url('print', ['delivery_note' => $note->id]))->assertOk()->json('data.sheet_url');

        $this->actingAs($this->admin)->get($sheetUrl)->assertInertia(fn (Assert $page) => $page->where('note.status', 'cancelled'));
    }

    public function test_sending_the_same_note_twice_makes_it_once(): void
    {
        [$sale, $paint] = $this->makeSale();
        $payload = $this->payload($sale, [[$paint, 3]]);

        $this->actingAs($this->admin)->post($this->url('store'), $payload)->assertSessionHas('success');
        $this->actingAs($this->admin)->post($this->url('store'), $payload)->assertSessionHas('success');

        $this->assertSame(1, $this->store->run(fn () => DeliveryNote::query()->count()));
    }

    public function test_a_note_uuid_that_belongs_to_someone_else_is_refused(): void
    {
        [$sale, $paint] = $this->makeSale();
        $payload = $this->payload($sale, [[$paint, 3]]);
        $this->actingAs($this->admin)->post($this->url('store'), $payload);

        $this->actingAs($this->storeUser(UserRole::StoreAdmin))->post($this->url('store'), $payload)
            ->assertSessionHas('error', 'Pengajuan surat jalan ini sudah tercatat untuk orang atau nota lain. Muat ulang layar lalu coba lagi.');

        $this->assertSame(1, $this->store->run(fn () => DeliveryNote::query()->count()));
    }

    public function test_a_note_needs_its_uuid(): void
    {
        [$sale, $paint] = $this->makeSale();

        $this->createNote($sale, [[$paint, 3]], ['client_uuid' => 'bukan-uuid'])->assertSessionHasErrors('client_uuid');
    }

    public function test_the_same_line_sent_twice_is_added_up_before_it_is_checked_against_what_is_left(): void
    {
        [$sale, $paint] = $this->makeSale();

        $this->createNote($sale, [[$paint, 6], [$paint, 6]])
            ->assertSessionHas('error', 'Cat Tembok melebihi sisa nota yang belum diantar, sisa 10 PAIL.');

        $this->assertSame(0, $this->store->run(fn () => DeliveryNote::query()->count()));
    }

    public function test_the_outcome_of_an_item_that_is_not_on_the_note_or_sent_twice_is_refused(): void
    {
        [$sale, $paint, $brush] = $this->makeSale();
        $note = $this->shippedNote($sale, [[$paint, 6]]);
        $otherNote = $this->shippedNote($sale, [[$brush, 2]]);
        $message = 'Ada hasil untuk barang yang tidak ada di surat jalan ini, atau barang yang sama diisi dua kali.';

        $this->complete($note, [[$note->items[0]->id, 6, 0], [$otherNote->items[0]->id, 2, 0]])->assertSessionHas('error', $message);
        $this->actingAs($this->admin)->post($this->url('complete', ['delivery_note' => $note->id]), ['results' => [
            ['item_id' => $note->items[0]->id, 'quantity_delivered' => 6, 'quantity_returned' => 0],
            ['item_id' => $note->items[0]->id, 'quantity_delivered' => 6, 'quantity_returned' => 0],
        ]])->assertSessionHasErrors('results.1.item_id');

        $this->assertSame(DeliveryNoteStatus::Shipped, $this->note($note->id)->status);
        $this->assertSame(DeliveryNoteStatus::Shipped, $this->note($otherNote->id)->status);
    }

    public function test_only_admins_may_complete_or_cancel_a_note(): void
    {
        [$sale, $paint] = $this->makeSale();
        $note = $this->shippedNote($sale, [[$paint, 6]]);

        $this->actingAs($this->storeUser())->post($this->url('complete', ['delivery_note' => $note->id]), ['results' => [['item_id' => $note->items[0]->id, 'quantity_delivered' => 6, 'quantity_returned' => 0]]])->assertForbidden();
        $this->actingAs($this->storeUser())->post($this->url('cancel', ['delivery_note' => $note->id]))->assertForbidden();

        $this->assertSame(DeliveryNoteStatus::Shipped, $this->note($note->id)->status);
    }

    public function test_completing_a_note_leaves_an_audit_record_of_what_arrived(): void
    {
        config(['audit.console' => true]);
        [$sale, $paint] = $this->makeSale();
        $note = $this->shippedNote($sale, [[$paint, 6]]);

        $this->complete($note, [[$note->items[0]->id, 4, 2]]);

        $audit = $this->store->run(fn () => $this->note($note->id)->items[0]->audits()->where('event', 'updated')->sole());
        $this->assertEquals([4, 2], [$audit->new_values['quantity_delivered'], $audit->new_values['quantity_returned']]);
    }

    public function test_receipts_that_are_fully_on_notes_do_not_crowd_the_receipt_lookup(): void
    {
        [$open] = $this->makeSale(['number' => 'INV/OPEN', 'sold_at' => now()->subDays(3)]);

        foreach (range(1, 11) as $number) {
            [$usedUp, $usedUpPaint, $usedUpBrush] = $this->makeSale(['number' => "INV/USED{$number}"]);
            $this->createNote($usedUp, [[$usedUpPaint, 10], [$usedUpBrush, 4]])->assertSessionHas('success');
        }

        $this->actingAs($this->admin)->getJson($this->url('sales', ['q' => 'INV/']))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $open->number);
    }

    public function test_the_list_can_be_filtered_up_to_a_day(): void
    {
        [$sale, $paint] = $this->makeSale();
        $this->createNote($sale, [[$paint, 1]]);

        $this->actingAs($this->admin)->get($this->url('index', ['to' => today()->subDay()->toDateString()]))->assertInertia(fn (Assert $page) => $page->has('notes.data', 0));
        $this->actingAs($this->admin)->get($this->url('index', ['to' => today()->toDateString()]))->assertInertia(fn (Assert $page) => $page->has('notes.data', 1));
    }

    public function test_the_list_shows_notes_newest_first_and_can_be_filtered(): void
    {
        [$sale, $paint, $brush] = $this->makeSale(['number' => 'INV/AAA']);
        [$otherSale, $otherPaint] = $this->makeSale(['number' => 'INV/BBB']);
        $this->createNote($sale, [[$paint, 1], [$brush, 1]], ['recipient_name' => 'Pak Budi']);
        $this->createNote($otherSale, [[$otherPaint, 1]], ['recipient_name' => 'Bu Sari']);
        $this->actingAs($this->admin)->post($this->url('ship', ['delivery_note' => $this->note()->id]));

        $this->actingAs($this->admin)->get($this->url('index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/DeliveryNotes/Index')
            ->has('notes.data', 2)
            ->where('notes.data.0.recipient_name', 'Bu Sari')
            ->where('notes.data.0.status_label', 'Dikirim')
            ->where('notes.data.0.sale_number', 'INV/BBB')
            ->where('notes.data.1.items_count', 2)
            ->has('statuses', 6));

        $this->actingAs($this->admin)->get($this->url('index', ['status' => 'draft']))->assertInertia(fn (Assert $page) => $page->has('notes.data', 1)->where('notes.data.0.recipient_name', 'Pak Budi'));
        $this->actingAs($this->admin)->get($this->url('index', ['search' => 'sari']))->assertInertia(fn (Assert $page) => $page->has('notes.data', 1));
        $this->actingAs($this->admin)->get($this->url('index', ['search' => 'INV/AAA']))->assertInertia(fn (Assert $page) => $page->has('notes.data', 1)->where('notes.data.0.recipient_name', 'Pak Budi'));
        $this->actingAs($this->admin)->get($this->url('index', ['from' => today()->addDay()->toDateString()]))->assertInertia(fn (Assert $page) => $page->has('notes.data', 0));
    }

    public function test_the_note_page_gives_what_may_be_done_next(): void
    {
        [$sale, $paint] = $this->makeSale();
        $this->createNote($sale, [[$paint, 3]]);
        $note = $this->note();

        $this->actingAs($this->admin)->get($this->url('show', ['delivery_note' => $note->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/DeliveryNotes/Show')
            ->where('note.number', $note->number)
            ->where('note.status_label', 'Draft')
            ->where('note.can_ship', true)
            ->where('note.can_complete', false)
            ->where('note.can_cancel', true)
            ->where('note.items.0.name', 'Cat Tembok')
            ->where('note.sale.number', $sale->number)
            ->where('note.urls.print', $this->url('print', ['delivery_note' => $note->id]))
            ->missing('note.urls.sheet'));
    }

    public function test_the_create_page_can_come_with_a_receipt_and_what_is_left_to_deliver(): void
    {
        [$sale, $paint] = $this->makeSale();
        $this->createNote($sale, [[$paint, 6]]);

        $this->actingAs($this->admin)->get($this->url('create', ['sale' => $sale->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/DeliveryNotes/Create')
            ->where('sale.number', $sale->number)
            ->where('sale.customer_name', 'Pak Budi')
            ->where('sale.lines.0.quantity', '10.000')
            ->where('sale.lines.0.remaining', '4.000')
            ->where('sale.lines.1.remaining', '4.000'));

        $this->actingAs($this->admin)->get($this->url('create'))->assertInertia(fn (Assert $page) => $page->where('sale', null));
    }

    public function test_the_receipt_lookup_only_offers_completed_receipts_with_something_left(): void
    {
        [$open, $openPaint, $openBrush] = $this->makeSale(['number' => 'INV/OPEN']);
        [$done, $donePaint, $doneBrush] = $this->makeSale(['number' => 'INV/DONE']);
        $this->makeSale(['number' => 'INV/VOID', 'status' => 'voided']);
        $this->createNote($done, [[$donePaint, 10], [$doneBrush, 4]]);

        $response = $this->actingAs($this->admin)->getJson($this->url('sales', ['q' => 'INV/']))->assertOk();

        $this->assertSame(['INV/OPEN'], $response->json('data.*.number'));
        $this->actingAs($this->admin)->getJson($this->url('sales', ['q' => 'budi']))->assertJsonCount(1, 'data');
        $this->assertSame([$openPaint->id, $openBrush->id], array_column($response->json('data.0.lines'), 'id'));
    }

    public function test_the_items_of_other_notes_are_not_mixed_up(): void
    {
        [$sale, $paint] = $this->makeSale();
        $this->createNote($sale, [[$paint, 2]]);
        $this->createNote($sale, [[$paint, 3]]);

        $this->assertSame(['2.000', '3.000'], $this->store->run(fn () => DeliveryNoteItem::query()->orderBy('id')->pluck('quantity')->all()));
    }
}
