<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DeliveryNoteStatus;
use App\Enums\UserRole;
use App\Models\DeliveryNote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class CashierDeliveryNoteTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_cashier_delivery';

    private Store $store;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_cashier_delivery', 'receipt_prefix' => 'TCD', 'address' => 'Jl. Uji 1', 'phone' => '021-123']);
        $this->cashier = $this->storeUser();
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.cashier.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

    /**
     * A completed receipt of the user, with one line of ten.
     *
     * @return array{0: Sale, 1: SaleItem}
     */
    private function makeSale(User $cashier, array $attributes = []): array
    {
        return $this->store->run(function () use ($cashier, $attributes): array {
            $sale = Sale::factory()->create(['cashier_id' => $cashier->id, 'cashier_name' => $cashier->name, 'customer_name' => 'Pak Budi', ...$attributes]);

            return [$sale, SaleItem::factory()->create(['sale_id' => $sale->id, 'product_name' => 'Cat Tembok', 'unit_name' => 'PAIL', 'quantity' => 10])];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function body(SaleItem $item, int|float $quantity, array $overrides = []): array
    {
        return ['client_uuid' => (string) Str::uuid(), 'recipient_name' => 'Pak Budi', 'recipient_phone' => '0812', 'recipient_address' => 'Jl. Mawar 5', 'items' => [['sale_item_id' => $item->id, 'quantity' => $quantity]], ...$overrides];
    }

    private function noteCount(): int
    {
        return $this->store->run(fn () => DeliveryNote::query()->count());
    }

    public function test_the_dialog_gets_the_lines_with_what_is_left_and_the_notes_already_made(): void
    {
        [$sale, $item] = $this->makeSale($this->cashier, ['number' => 'INV/1']);
        $this->actingAs($this->cashier)->postJson($this->url('receipts.delivery-notes.store', ['sale' => $sale->id]), $this->body($item, 6))->assertCreated();

        $this->actingAs($this->cashier)->getJson($this->url('receipts.delivery', ['sale' => $sale->id]))->assertOk()
            ->assertJsonPath('data.number', 'INV/1')
            ->assertJsonPath('data.customer_name', 'Pak Budi')
            ->assertJsonPath('data.lines.0.quantity', '10.000')
            ->assertJsonPath('data.lines.0.remaining', '4.000')
            ->assertJsonCount(1, 'data.notes')
            ->assertJsonPath('data.notes.0.status', 'draft')
            ->assertJsonPath('data.notes.0.print_count', 0)
            ->assertJsonPath('data.notes.0.items.0.sale_item_id', $item->id)
            ->assertJsonPath('data.notes.0.items.0.name', 'Cat Tembok')
            ->assertJsonPath('data.notes.0.items.0.quantity', '6.000')
            ->assertJsonPath('data.notes.0.items.0.quantity_delivered', '0.000');
    }

    public function test_a_cashier_makes_a_note_for_their_own_receipt(): void
    {
        [$sale, $item] = $this->makeSale($this->cashier);

        $response = $this->actingAs($this->cashier)->postJson($this->url('receipts.delivery-notes.store', ['sale' => $sale->id]), $this->body($item, 6))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.print_count', 0);

        $note = $this->store->run(fn () => DeliveryNote::query()->with('items')->sole());
        $this->assertSame('SJ/TCD/'.now()->format('ym').'/0001', $note->number);
        $this->assertSame([$this->cashier->id, $sale->id, DeliveryNoteStatus::Draft, '6.000'], [$note->created_by, $note->sale_id, $note->status, $note->items[0]->quantity]);
        $this->assertStringEndsWith('/cashier/delivery-notes/'.$note->id.'/print', $response->json('data.print_url'));
    }

    public function test_the_note_input_is_validated_and_the_remaining_quantity_is_enforced(): void
    {
        [$sale, $item] = $this->makeSale($this->cashier);
        $store = $this->url('receipts.delivery-notes.store', ['sale' => $sale->id]);

        $this->actingAs($this->cashier)->postJson($store, $this->body($item, 6, ['recipient_name' => '']))->assertUnprocessable()->assertJsonValidationErrors('recipient_name');
        $this->actingAs($this->cashier)->postJson($store, $this->body($item, 0))->assertUnprocessable()->assertJsonPath('message', 'Pilih minimal satu barang yang diantar.');
        $this->actingAs($this->cashier)->postJson($store, $this->body($item, 11))->assertUnprocessable()->assertJsonPath('message', 'Cat Tembok melebihi sisa nota yang belum diantar, sisa 10 PAIL.');

        $this->assertSame(0, $this->noteCount());
    }

    public function test_a_voided_receipt_cannot_get_a_note(): void
    {
        [$sale, $item] = $this->makeSale($this->cashier, ['status' => 'voided']);

        $this->actingAs($this->cashier)->postJson($this->url('receipts.delivery-notes.store', ['sale' => $sale->id]), $this->body($item, 1))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Surat jalan hanya bisa dibuat dari nota yang selesai, bukan nota void.');
    }

    public function test_a_cashier_cannot_touch_the_receipt_of_someone_else_but_an_admin_can(): void
    {
        $other = $this->storeUser();
        [$sale, $item] = $this->makeSale($other);
        $note = $this->store->run(fn () => DeliveryNote::factory()->create(['sale_id' => $sale->id]));

        $this->actingAs($this->cashier)->getJson($this->url('receipts.delivery', ['sale' => $sale->id]))->assertForbidden();
        $this->actingAs($this->cashier)->postJson($this->url('receipts.delivery-notes.store', ['sale' => $sale->id]), $this->body($item, 1))->assertForbidden();
        $this->actingAs($this->cashier)->postJson($this->url('delivery-notes.print', ['delivery_note' => $note->id]))->assertForbidden();
        $this->actingAs($this->cashier)->get($this->url('delivery-notes.sheet', ['delivery_note' => $note->id]))->assertForbidden();
        $this->assertSame(1, $this->noteCount());

        $admin = $this->storeUser(UserRole::StoreAdmin);
        $this->actingAs($admin)->postJson($this->url('receipts.delivery-notes.store', ['sale' => $sale->id]), $this->body($item, 1))->assertCreated();
    }

    public function test_the_first_print_is_the_original_and_later_ones_are_copies(): void
    {
        [$sale, $item] = $this->makeSale($this->cashier);
        $printUrl = $this->actingAs($this->cashier)->postJson($this->url('receipts.delivery-notes.store', ['sale' => $sale->id]), $this->body($item, 6))->json('data.print_url');

        $this->actingAs($this->cashier)->postJson($printUrl)->assertOk()->assertJsonPath('data.is_copy', false)->assertJsonPath('data.print_count', 1);
        $this->actingAs($this->cashier)->postJson($printUrl)->assertOk()->assertJsonPath('data.is_copy', true)->assertJsonPath('data.print_count', 2);
    }

    public function test_the_sheet_is_the_note_without_prices_and_says_copy_after_the_first_print(): void
    {
        [$sale, $item] = $this->makeSale($this->cashier, ['number' => 'INV/1']);
        $created = $this->actingAs($this->cashier)->postJson($this->url('receipts.delivery-notes.store', ['sale' => $sale->id]), $this->body($item, 6))->json('data');
        $this->assertArrayNotHasKey('sheet_url', $created);

        $firstSheetUrl = $this->actingAs($this->cashier)->postJson($created['print_url'])->json('data.sheet_url');
        $this->actingAs($this->cashier)->get($firstSheetUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Print/DeliveryNote')
            ->where('note.number', $created['number'])
            ->where('note.is_copy', false)
            ->where('note.recipient_name', 'Pak Budi')
            ->where('note.sale.number', 'INV/1')
            ->where('note.store.name', $this->store->name)
            ->where('note.store.address', 'Jl. Uji 1')
            ->where('note.items.0.name', 'Cat Tembok')
            ->missing('note.items.0.unit_price'));

        $secondSheetUrl = $this->actingAs($this->cashier)->postJson($created['print_url'])->json('data.sheet_url');
        $this->actingAs($this->cashier)->get($secondSheetUrl)->assertInertia(fn (Assert $page) => $page->where('note.is_copy', true));
        $this->actingAs($this->cashier)->get($firstSheetUrl)->assertInertia(fn (Assert $page) => $page->where('note.is_copy', false));
        $this->actingAs($this->cashier)->get($this->url('delivery-notes.sheet', ['delivery_note' => $created['id']]))->assertForbidden();
    }

    public function test_sending_the_same_note_twice_from_the_till_gives_back_the_first_one(): void
    {
        [$sale, $item] = $this->makeSale($this->cashier);
        $body = $this->body($item, 6);
        $store = $this->url('receipts.delivery-notes.store', ['sale' => $sale->id]);

        $first = $this->actingAs($this->cashier)->postJson($store, $body)->assertCreated();
        $second = $this->actingAs($this->cashier)->postJson($store, $body)->assertCreated();

        $this->assertSame($first->json('data.number'), $second->json('data.number'));
        $this->assertSame(1, $this->noteCount());
        $this->actingAs($this->cashier)->getJson($this->url('receipts.delivery', ['sale' => $sale->id]))->assertJsonPath('data.lines.0.remaining', '4.000');
    }

    public function test_a_note_uuid_that_belongs_to_someone_else_is_refused_at_the_till(): void
    {
        [$sale, $item] = $this->makeSale($this->cashier);
        $body = $this->body($item, 6);
        $this->actingAs($this->cashier)->postJson($this->url('receipts.delivery-notes.store', ['sale' => $sale->id]), $body)->assertCreated();

        $this->actingAs($this->storeUser(UserRole::StoreAdmin))->postJson($this->url('receipts.delivery-notes.store', ['sale' => $sale->id]), $body)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pengajuan surat jalan ini sudah tercatat untuk orang atau nota lain. Muat ulang layar lalu coba lagi.');

        $this->assertSame(1, $this->noteCount());
    }

    public function test_someone_elses_receipt_is_refused_before_the_body_is_looked_at(): void
    {
        [$sale] = $this->makeSale($this->storeUser());

        $this->actingAs($this->cashier)->postJson($this->url('receipts.delivery-notes.store', ['sale' => $sale->id]), [])->assertForbidden();
    }

    public function test_the_receipt_lists_carry_the_links_the_till_needs_for_a_note(): void
    {
        [$sale] = $this->makeSale($this->cashier);
        $shift = $this->openShiftFor($this->cashier);
        $this->store->run(fn () => $sale->forceFill(['cash_shift_id' => $shift->id, 'register_id' => $shift->register_id])->save());

        $this->actingAs($this->cashier)->getJson($this->url('receipts'))
            ->assertJsonPath('data.0.delivery_url', $this->url('receipts.delivery', ['sale' => $sale->id]))
            ->assertJsonPath('data.0.delivery_note_store_url', $this->url('receipts.delivery-notes.store', ['sale' => $sale->id]));
    }

    public function test_a_guest_is_refused(): void
    {
        [$sale] = $this->makeSale($this->cashier);

        $this->getJson($this->url('receipts.delivery', ['sale' => $sale->id]))->assertUnauthorized();
    }
}
