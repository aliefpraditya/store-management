<?php

namespace Tests\Feature\Tenancy;

use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseAttachment;
use App\Models\PurchaseItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_purchases';

    private Store $store;

    private User $admin;

    private User $cashier;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_purchases', 'receipt_prefix' => 'TPU']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
        $this->supplier = $this->store->run(fn (): Supplier => Supplier::factory()->create(['name' => 'PT Cat Jaya']));
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.purchases.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

    /**
     * A nota as the form sends it, with one line of 5 DUS (of 12 PCS) at 70.000; `$overrides` replaces any field.
     *
     * @return array<string, mixed>
     */
    private function body(Product $product, array $overrides = []): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'supplier_document_number' => 'INV-001',
            'purchase_date' => today()->toDateString(),
            'warehouse_id' => $this->mainWarehouse()->id,
            'items' => [['product_id' => $product->id, 'unit_code' => 'DUS', 'quantity' => 5, 'unit_cost' => 70000]],
            ...$overrides,
        ];
    }

    private function onlyPurchase(): Purchase
    {
        return $this->store->run(fn (): Purchase => Purchase::query()->sole());
    }

    private function draft(Product $product, array $overrides = []): Purchase
    {
        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product, $overrides))->assertSessionHasNoErrors();

        return $this->store->run(fn (): Purchase => Purchase::query()->latest('id')->firstOrFail());
    }

    private function statusOf(Purchase $purchase): PurchaseStatus
    {
        return $this->store->run(fn (): PurchaseStatus => Purchase::query()->findOrFail($purchase->id)->status);
    }

    public function test_only_users_who_may_manage_purchases_can_open_them(): void
    {
        $purchase = $this->draft($this->sellableProduct());

        foreach (['index' => [], 'create' => [], 'show' => ['purchase' => $purchase->id]] as $name => $parameters) {
            $this->actingAs($this->cashier)->get($this->url($name, $parameters))->assertForbidden();
            $this->actingAs($this->admin)->get($this->url($name, $parameters))->assertOk();
        }

        $this->actingAs($this->cashier)->post($this->url('store'), $this->body($this->sellableProduct('Lain')))->assertForbidden();
    }

    public function test_a_draft_keeps_each_line_in_the_unit_it_was_bought_in_and_in_the_base_unit(): void
    {
        $product = $this->sellableProduct();

        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product, [
            'items' => [
                ['product_id' => $product->id, 'unit_code' => 'DUS', 'quantity' => 5, 'unit_cost' => 70000],
                ['product_id' => $product->id, 'unit_code' => 'PCS', 'quantity' => '3.5', 'unit_cost' => '6000.50', 'notes' => 'eceran'],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $purchase = $this->onlyPurchase();
        $items = $this->store->run(fn () => $purchase->items()->orderBy('id')->get());

        $this->assertSame(PurchaseStatus::Draft, $purchase->status);
        $this->assertSame('PB/TPU/'.today()->format('ym').'/0001', $purchase->number);
        $this->assertSame('371001.75', $purchase->total_amount);
        $this->assertSame(['DUS', '12.000', '5.000', '60.000', '70000.00', '5833.3333', '350000.00'], [$items[0]->unit_code, $items[0]->conversion_factor, $items[0]->quantity, $items[0]->base_quantity, $items[0]->unit_cost, $items[0]->base_unit_cost, $items[0]->subtotal]);
        $this->assertSame(['PCS', '1.000', '3.500', '3.500', '6000.50', '6000.5000', '21001.75'], [$items[1]->unit_code, $items[1]->conversion_factor, $items[1]->quantity, $items[1]->base_quantity, $items[1]->unit_cost, $items[1]->base_unit_cost, $items[1]->subtotal]);
        $this->assertSame('eceran', $items[1]->notes);
    }

    public function test_a_nota_changes_no_stock_and_no_cost_price(): void
    {
        $product = $this->sellableProduct(stockInPieces: '100');

        $this->draft($product);

        $this->assertSame('100.000', $this->store->run(fn (): string => (string) $product->stocks()->sole()->quantity));
        $this->assertSame('6000.00', $this->store->run(fn (): string => (string) Product::query()->findOrFail($product->id)->cost_price));
    }

    public function test_sending_the_same_nota_twice_makes_only_one(): void
    {
        $product = $this->sellableProduct();
        $body = $this->body($product);

        $this->actingAs($this->admin)->post($this->url('store'), $body)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post($this->url('store'), $body)->assertSessionHasNoErrors();

        $this->assertSame(1, $this->store->run(fn (): int => Purchase::query()->count()));
        $this->assertSame(1, $this->store->run(fn (): int => PurchaseItem::query()->count()));
    }

    public function test_the_marker_of_someone_elses_nota_is_refused(): void
    {
        $product = $this->sellableProduct();
        $body = $this->body($product);
        $this->actingAs($this->admin)->post($this->url('store'), $body)->assertSessionHasNoErrors();

        $otherAdmin = $this->storeUser(UserRole::StoreAdmin);
        $this->actingAs($otherAdmin)->post($this->url('store'), $body)->assertSessionHas('error', 'Nota ini sudah dibuat oleh orang lain. Muat ulang layar lalu coba lagi.');

        $this->assertSame(1, $this->store->run(fn (): int => Purchase::query()->count()));
    }

    public function test_the_numbers_run_on_in_a_month(): void
    {
        $product = $this->sellableProduct();

        $this->draft($product, ['supplier_document_number' => 'A-1']);
        $second = $this->draft($product, ['supplier_document_number' => 'A-2']);

        $this->assertSame('PB/TPU/'.today()->format('ym').'/0002', $second->number);
    }

    public function test_a_nota_needs_a_supplier_a_sellable_warehouse_a_date_and_at_least_one_line(): void
    {
        $product = $this->sellableProduct();
        $deadStockWarehouseId = $this->store->run(fn (): int => Warehouse::query()->where('type', 'dead_stock')->value('id'));

        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product, ['supplier_id' => null, 'purchase_date' => '', 'items' => []]))
            ->assertSessionHasErrors(['supplier_id' => 'Pilih supplier.', 'purchase_date' => 'Isi tanggal nota.', 'items' => 'Tambahkan minimal satu barang.']);
        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product, ['warehouse_id' => $deadStockWarehouseId]))
            ->assertSessionHasErrors(['warehouse_id' => 'Gudang tujuan harus gudang jual yang aktif.']);
        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product, ['expected_date' => today()->subDay()->toDateString()]))
            ->assertSessionHasErrors(['expected_date' => 'Perkiraan tiba tidak boleh sebelum tanggal nota.']);

        $this->assertSame(0, $this->store->run(fn (): int => Purchase::query()->count()));
    }

    public function test_quantities_and_prices_must_be_positive_numbers_within_their_decimals(): void
    {
        $product = $this->sellableProduct();

        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product, ['items' => [['product_id' => $product->id, 'unit_code' => 'PCS', 'quantity' => 0, 'unit_cost' => 0]]]))
            ->assertSessionHasErrors(['items.0.quantity' => 'Jumlah harus lebih dari nol.', 'items.0.unit_cost' => 'Harga harus lebih dari nol.']);
        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product, ['items' => [['product_id' => $product->id, 'unit_code' => 'PCS', 'quantity' => '1.2345', 'unit_cost' => '10.123']]]))
            ->assertSessionHasErrors(['items.0.quantity' => 'Jumlah maksimal 3 angka desimal.', 'items.0.unit_cost' => 'Harga maksimal 2 angka desimal.']);
    }

    public function test_a_product_can_be_on_a_nota_once_per_unit(): void
    {
        $product = $this->sellableProduct();
        $line = ['product_id' => $product->id, 'unit_code' => 'DUS', 'quantity' => 1, 'unit_cost' => 70000];

        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product, ['items' => [$line, $line]]))
            ->assertSessionHas('error', 'Kuas dalam satuan DUS muncul dua kali di nota. Gabungkan jadi satu baris.');

        $this->assertSame(0, $this->store->run(fn (): int => Purchase::query()->count()));
    }

    public function test_a_unit_the_product_does_not_have_is_refused(): void
    {
        $product = $this->sellableProduct();

        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product, ['items' => [['product_id' => $product->id, 'unit_code' => 'ROL', 'quantity' => 1, 'unit_cost' => 1000]]]))
            ->assertSessionHas('error', 'Satuan ROL bukan satuan barang Kuas.');
    }

    public function test_an_inactive_product_and_a_damaged_goods_product_cannot_be_bought(): void
    {
        $inactive = $this->sellableProduct('Lama', productAttributes: ['is_active' => false]);
        $damaged = $this->sellableProduct('Kuas DG', productAttributes: ['is_damaged_goods' => true]);

        $this->actingAs($this->admin)->post($this->url('store'), $this->body($inactive))
            ->assertSessionHas('error', 'Barang Lama sudah dinonaktifkan, jadi tidak bisa dibeli. Aktifkan dulu di Daftar Barang.');
        $this->actingAs($this->admin)->post($this->url('store'), $this->body($damaged))
            ->assertSessionHas('error', 'Kuas DG adalah barang DG (rusak) yang dibuat dari stok sendiri, jadi tidak dibeli dari supplier.');
    }

    public function test_an_inactive_supplier_cannot_be_bought_from(): void
    {
        $product = $this->sellableProduct();
        $this->store->run(fn () => $this->supplier->forceFill(['is_active' => false])->save());

        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product))
            ->assertSessionHas('error', 'Supplier ini sudah dinonaktifkan. Pilih supplier lain atau aktifkan kembali.');
    }

    public function test_a_suppliers_nota_number_is_used_once_unless_the_first_was_cancelled(): void
    {
        $product = $this->sellableProduct();
        $first = $this->draft($product);

        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product))
            ->assertSessionHasErrors(['supplier_document_number' => 'Nomor nota supplier ini sudah dipakai di nota lain dari supplier yang sama.']);

        $otherSupplier = $this->store->run(fn (): Supplier => Supplier::factory()->create());
        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product, ['supplier_id' => $otherSupplier->id]))->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post($this->url('cancel', ['purchase' => $first->id]), ['reason' => 'Salah input'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product))->assertSessionHasNoErrors();
    }

    public function test_a_draft_can_be_changed_and_its_lines_are_replaced(): void
    {
        $product = $this->sellableProduct();
        $other = $this->sellableProduct('Cat');
        $draft = $this->draft($product);

        $body = $this->body($other, ['items' => [['product_id' => $other->id, 'unit_code' => 'PCS', 'quantity' => 24, 'unit_cost' => 5000]]]);
        unset($body['client_uuid']);

        $this->actingAs($this->admin)->put($this->url('update', ['purchase' => $draft->id]), $body)->assertRedirect($this->url('show', ['purchase' => $draft->id]))->assertSessionHasNoErrors();

        $items = $this->store->run(fn () => PurchaseItem::query()->where('purchase_id', $draft->id)->get());

        $this->assertCount(1, $items);
        $this->assertSame($other->id, $items[0]->product_id);
        $this->assertSame('120000.00', $this->store->run(fn (): string => (string) Purchase::query()->findOrFail($draft->id)->total_amount));
    }

    public function test_a_confirmed_nota_is_locked(): void
    {
        $product = $this->sellableProduct();
        $draft = $this->draft($product);
        $this->actingAs($this->admin)->post($this->url('confirm', ['purchase' => $draft->id]))->assertSessionHasNoErrors();

        $body = $this->body($product);
        unset($body['client_uuid']);

        $this->actingAs($this->admin)->put($this->url('update', ['purchase' => $draft->id]), $body)
            ->assertSessionHas('error', 'Nota ini sudah dikonfirmasi, jadi tidak bisa diubah lagi. Batalkan lalu buat nota baru bila ada yang salah.');
        $this->actingAs($this->admin)->get($this->url('edit', ['purchase' => $draft->id]))->assertRedirect($this->url('show', ['purchase' => $draft->id]));
    }

    public function test_confirming_turns_a_draft_into_an_order_once(): void
    {
        $draft = $this->draft($this->sellableProduct());

        $this->actingAs($this->admin)->post($this->url('confirm', ['purchase' => $draft->id]))->assertSessionHasNoErrors();

        $purchase = $this->onlyPurchase();
        $this->assertSame(PurchaseStatus::Ordered, $purchase->status);
        $this->assertSame($this->admin->id, $purchase->confirmed_by);
        $this->assertNotNull($purchase->confirmed_at);

        $this->actingAs($this->admin)->post($this->url('confirm', ['purchase' => $draft->id]))->assertSessionHas('error', 'Hanya nota draft yang bisa dikonfirmasi.');
    }

    public function test_a_nota_without_lines_or_with_a_switched_off_supplier_is_not_confirmed(): void
    {
        $empty = $this->store->run(fn (): Purchase => Purchase::factory()->create(['supplier_id' => $this->supplier->id]));

        $this->actingAs($this->admin)->post($this->url('confirm', ['purchase' => $empty->id]))->assertSessionHas('error', 'Nota belum punya barang. Tambahkan barang dulu.');

        $draft = $this->draft($this->sellableProduct());
        $this->store->run(fn () => $this->supplier->forceFill(['is_active' => false])->save());

        $this->actingAs($this->admin)->post($this->url('confirm', ['purchase' => $draft->id]))->assertSessionHas('error', 'Supplier ini sudah dinonaktifkan. Pilih supplier lain atau aktifkan kembali.');
        $this->assertSame(PurchaseStatus::Draft, $this->statusOf($draft));
    }

    public function test_a_draft_or_an_order_can_be_cancelled_with_a_reason_and_then_not_again(): void
    {
        $draft = $this->draft($this->sellableProduct());

        $this->actingAs($this->admin)->post($this->url('cancel', ['purchase' => $draft->id]), ['reason' => ''])->assertSessionHasErrors(['reason' => 'Isi alasan pembatalan.']);
        $this->assertSame(PurchaseStatus::Draft, $this->statusOf($draft));

        $this->actingAs($this->admin)->post($this->url('confirm', ['purchase' => $draft->id]));
        $this->actingAs($this->admin)->post($this->url('cancel', ['purchase' => $draft->id]), ['reason' => ' Supplier kehabisan '])->assertSessionHasNoErrors();

        $purchase = $this->onlyPurchase();
        $this->assertSame(PurchaseStatus::Cancelled, $purchase->status);
        $this->assertSame('Supplier kehabisan', $purchase->cancel_reason);
        $this->assertNotNull($purchase->cancelled_at);

        $this->actingAs($this->admin)->post($this->url('cancel', ['purchase' => $draft->id]), ['reason' => 'Lagi'])
            ->assertSessionHas('error', 'Nota ini tidak bisa dibatalkan lagi karena sudah ada barang yang diterima atau notanya sudah selesai.');
    }

    public function test_a_nota_that_has_received_goods_cannot_be_cancelled(): void
    {
        $purchase = $this->store->run(fn (): Purchase => Purchase::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PurchaseStatus::PartiallyReceived]));

        $this->actingAs($this->admin)->post($this->url('cancel', ['purchase' => $purchase->id]), ['reason' => 'Salah'])->assertSessionHas('error');

        $this->assertSame(PurchaseStatus::PartiallyReceived, $this->statusOf($purchase));
    }

    public function test_the_list_is_narrowed_by_search_supplier_status_and_day(): void
    {
        $product = $this->sellableProduct();
        $first = $this->draft($product, ['supplier_document_number' => 'INV-AAA', 'purchase_date' => '2026-11-01']);
        $otherSupplier = $this->store->run(fn (): Supplier => Supplier::factory()->create(['name' => 'CV Semen']));
        $second = $this->draft($product, ['supplier_id' => $otherSupplier->id, 'supplier_document_number' => 'INV-BBB', 'purchase_date' => '2026-11-15']);
        $this->actingAs($this->admin)->post($this->url('confirm', ['purchase' => $second->id]));

        $numbers = fn (array $expected) => fn (Assert $page) => $page->component('Tenant/Purchases/Index')->where('purchases.data', fn ($rows) => collect($rows)->pluck('number')->all() === $expected);

        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia($numbers([$second->number, $first->number]));
        $this->actingAs($this->admin)->get($this->url('index', ['search' => 'AAA']))->assertInertia($numbers([$first->number]));
        $this->actingAs($this->admin)->get($this->url('index', ['search' => 'semen']))->assertInertia($numbers([$second->number]));
        $this->actingAs($this->admin)->get($this->url('index', ['supplier' => $this->supplier->id]))->assertInertia($numbers([$first->number]));
        $this->actingAs($this->admin)->get($this->url('index', ['status' => 'ordered']))->assertInertia($numbers([$second->number]));
        $this->actingAs($this->admin)->get($this->url('index', ['from' => '2026-11-10', 'to' => '2026-11-20']))->assertInertia($numbers([$second->number]));
    }

    public function test_the_detail_shows_the_lines_the_files_and_what_can_be_done(): void
    {
        $draft = $this->draft($this->sellableProduct());

        $this->actingAs($this->admin)->get($this->url('show', ['purchase' => $draft->id]))->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Purchases/Show')
            ->where('purchase.number', $draft->number)
            ->where('purchase.supplier_name', 'PT Cat Jaya')
            ->where('purchase.is_editable', true)
            ->where('purchase.is_cancellable', true)
            ->where('purchase.total_amount', '350000.00')
            ->where('purchase.items.0.product.name', 'Kuas')
            ->where('purchase.items.0.unit_code', 'DUS')
            ->where('purchase.items.0.base_quantity', '60.000')
            ->where('purchase.items.0.product.units', ['PCS', 'DUS']));
    }

    public function test_files_sent_with_a_nota_are_kept_on_the_private_disk_and_opened_through_the_route(): void
    {
        Storage::fake('local');
        $product = $this->sellableProduct();

        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product, [
            'attachments' => [UploadedFile::fake()->image('nota.jpg'), UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf')],
        ]))->assertSessionHasNoErrors();

        $purchase = $this->onlyPurchase();
        $attachments = $this->store->run(fn () => $purchase->attachments()->orderBy('id')->get());

        $this->assertCount(2, $attachments);
        $this->assertSame(['nota.jpg', 'nota.pdf'], $attachments->pluck('original_name')->all());
        $this->assertStringStartsWith('purchases/test_purchases/'.$purchase->id.'/', $attachments[0]->path);
        Storage::disk('local')->assertExists($attachments[0]->path);

        $this->actingAs($this->admin)->get($this->url('attachments.show', ['purchase' => $purchase->id, 'attachment' => $attachments[0]->id]))->assertOk();
        $this->actingAs($this->cashier)->get($this->url('attachments.show', ['purchase' => $purchase->id, 'attachment' => $attachments[0]->id]))->assertForbidden();
    }

    public function test_only_photos_and_pdfs_up_to_five_megabytes_are_taken(): void
    {
        Storage::fake('local');
        $product = $this->sellableProduct();

        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product, ['attachments' => [UploadedFile::fake()->create('nota.exe', 10, 'application/octet-stream')]]))
            ->assertSessionHasErrors(['attachments.0' => 'Lampiran harus berupa foto (JPG, PNG, WebP) atau PDF.']);
        $this->actingAs($this->admin)->post($this->url('store'), $this->body($product, ['attachments' => [UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')]]))
            ->assertSessionHasErrors(['attachments.0' => 'Satu lampiran paling besar 5 MB.']);

        $this->assertSame(0, $this->store->run(fn (): int => Purchase::query()->count()));
    }

    public function test_files_can_be_added_later_and_removed_but_a_nota_has_at_most_ten(): void
    {
        Storage::fake('local');
        $draft = $this->draft($this->sellableProduct());

        $this->actingAs($this->admin)->post($this->url('attachments.store', ['purchase' => $draft->id]), ['attachments' => [UploadedFile::fake()->image('a.png')]])->assertSessionHasNoErrors();

        $attachment = $this->store->run(fn (): PurchaseAttachment => PurchaseAttachment::query()->sole());

        $this->actingAs($this->admin)->delete($this->url('attachments.destroy', ['purchase' => $draft->id, 'attachment' => $attachment->id]))->assertSessionHasNoErrors();

        $this->assertSame(0, $this->store->run(fn (): int => PurchaseAttachment::query()->count()));
        Storage::disk('local')->assertMissing($attachment->path);

        $files = fn (int $count) => array_map(fn (int $number) => UploadedFile::fake()->image("n{$number}.png"), range(1, $count));
        $this->actingAs($this->admin)->post($this->url('attachments.store', ['purchase' => $draft->id]), ['attachments' => $files(10)])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post($this->url('attachments.store', ['purchase' => $draft->id]), ['attachments' => $files(1)])
            ->assertSessionHas('error', 'Satu nota paling banyak punya 10 lampiran.');

        $this->assertSame(10, $this->store->run(fn (): int => PurchaseAttachment::query()->count()));
    }

    public function test_the_files_of_another_nota_cannot_be_opened_through_this_one(): void
    {
        Storage::fake('local');
        $product = $this->sellableProduct();
        $first = $this->draft($product, ['supplier_document_number' => 'A']);
        $second = $this->draft($product, ['supplier_document_number' => 'B']);

        $this->actingAs($this->admin)->post($this->url('attachments.store', ['purchase' => $first->id]), ['attachments' => [UploadedFile::fake()->image('a.png')]]);
        $attachment = $this->store->run(fn (): PurchaseAttachment => PurchaseAttachment::query()->sole());

        $this->actingAs($this->admin)->get($this->url('attachments.show', ['purchase' => $second->id, 'attachment' => $attachment->id]))->assertNotFound();
    }

    public function test_a_cancelled_nota_keeps_its_files_as_they_are(): void
    {
        Storage::fake('local');
        $draft = $this->draft($this->sellableProduct());
        $this->actingAs($this->admin)->post($this->url('attachments.store', ['purchase' => $draft->id]), ['attachments' => [UploadedFile::fake()->image('a.png')]]);
        $attachment = $this->store->run(fn (): PurchaseAttachment => PurchaseAttachment::query()->sole());
        $this->actingAs($this->admin)->post($this->url('cancel', ['purchase' => $draft->id]), ['reason' => 'Salah']);

        $this->actingAs($this->admin)->post($this->url('attachments.store', ['purchase' => $draft->id]), ['attachments' => [UploadedFile::fake()->image('b.png')]])
            ->assertSessionHas('error', 'Nota yang dibatalkan tidak bisa diubah lampirannya.');
        $this->actingAs($this->admin)->delete($this->url('attachments.destroy', ['purchase' => $draft->id, 'attachment' => $attachment->id]))
            ->assertSessionHas('error', 'Nota yang dibatalkan tidak bisa diubah lampirannya.');

        $this->assertSame(1, $this->store->run(fn (): int => PurchaseAttachment::query()->count()));
    }
}
