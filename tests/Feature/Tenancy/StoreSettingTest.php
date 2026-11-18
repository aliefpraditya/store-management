<?php

namespace Tests\Feature\Tenancy;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Enums\UserRole;
use App\Exceptions\StockOpnameException;
use App\Models\CashShift;
use App\Models\DeliveryNote;
use App\Models\Driver;
use App\Models\OpnameReason;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Store;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\SaleService;
use App\Services\StoreSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class StoreSettingTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_store_settings';

    private Store $store;

    private User $admin;

    private User $cashier;

    private Product $brush;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_store_settings', 'receipt_prefix' => 'TSS', 'name' => 'Toko Uji', 'address' => 'Jl. Uji 1', 'phone' => '021-111']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
        $this->brush = $this->sellableProduct('Kuas', '100');
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.settings.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(array $overrides = []): array
    {
        return [
            'receipt_name' => '',
            'receipt_address' => '',
            'receipt_phone' => '',
            'receipt_footer' => '',
            'paper_width' => 80,
            'large_variance_threshold' => '',
            'lock_sales_during_opname' => false,
            'auto_print_receipt' => false,
            ...$overrides,
        ];
    }

    private function save(array $overrides = []): void
    {
        $this->actingAs($this->admin)->put($this->url('update'), $this->body($overrides))->assertSessionHasNoErrors();
    }

    private function sellBrushes(CashShift $shift, int $pieces = 2): Sale
    {
        return $this->store->run(fn (): Sale => app(SaleService::class)->create($shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($this->brush, 'PCS')->id, 'quantity' => $pieces]],
            'payments' => [['method' => 'cash', 'amount' => $pieces * 10000]],
        ]));
    }

    /**
     * An opname of the brushes in the given status, with the count typed in (the stock is 100).
     */
    private function opnameWithCount(StockOpnameStatus $status, ?string $counted = '98', ?string $note = null, StockOpnameReason $reason = StockOpnameReason::Correction): StockOpname
    {
        return $this->store->run(function () use ($status, $counted, $note, $reason): StockOpname {
            $opname = StockOpname::factory()->create([
                'warehouse_id' => $this->mainWarehouse()->id,
                'reason_code' => $reason,
                'scope' => StockOpnameScope::Selected,
                'status' => $status,
                'snapshot_at' => now(),
                'created_by' => $this->admin->id,
            ]);
            StockOpnameItem::factory()->create(['stock_opname_id' => $opname->id, 'product_id' => $this->brush->id, 'snapshot_quantity' => '100', 'counted_quantity' => $counted, 'note' => $note]);

            return $opname;
        });
    }

    private function postOpname(StockOpname $opname)
    {
        return $this->actingAs($this->admin)->post(route('tenant.stock-opnames.post', ['tenant' => $this->store->code, 'stock_opname' => $opname->id]));
    }

    public function test_only_admins_may_use_the_settings(): void
    {
        $this->actingAs($this->cashier)->get($this->url('index'))->assertForbidden();
        $this->actingAs($this->cashier)->put($this->url('update'), $this->body())->assertForbidden();
    }

    public function test_the_page_starts_with_the_defaults(): void
    {
        $this->actingAs($this->admin)->get($this->url('index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Settings/Index')
            ->where('settings.receipt_name', '')
            ->where('settings.receipt_footer', StoreSettingService::DEFAULT_RECEIPT_FOOTER)
            ->where('settings.paper_width', 80)
            ->where('settings.large_variance_threshold', '')
            ->where('settings.lock_sales_during_opname', false)
            ->where('store_letterhead', ['name' => 'Toko Uji', 'address' => 'Jl. Uji 1', 'phone' => '021-111'])
            ->where('paper_widths', [58, 80])
            ->has('units.data', 2)
            ->where('units.data.0.code', 'DUS')
            ->where('units.data.0.products_count', 1)
            ->where('unit_filters', ['search' => '', 'sort' => 'code', 'direction' => 'asc', 'per_page' => 10])
            ->where('unit_per_page_options', [10, 25, 50, 100])
            ->where('urls.unit_create', route('tenant.units.store', ['tenant' => $this->store->code]))
            ->missing('registers'));
    }

    public function test_the_settings_are_saved_and_shown_again(): void
    {
        $this->save(['receipt_name' => 'MITRA UTAMA', 'receipt_footer' => "Terima kasih\nDatang lagi", 'paper_width' => 58, 'large_variance_threshold' => '50000', 'lock_sales_during_opname' => true]);

        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('settings.receipt_name', 'MITRA UTAMA')
            ->where('settings.receipt_footer', "Terima kasih\nDatang lagi")
            ->where('settings.paper_width', 58)
            ->where('settings.large_variance_threshold', '50000')
            ->where('settings.lock_sales_during_opname', true));
    }

    public function test_a_blank_text_goes_back_to_its_default(): void
    {
        $this->save(['receipt_name' => 'MITRA UTAMA', 'receipt_footer' => 'Sampai jumpa']);
        $this->save(['receipt_name' => '', 'receipt_footer' => '']);

        $letterhead = $this->store->run(fn (): array => app(StoreSettingService::class)->letterhead());
        $this->assertSame('Toko Uji', $letterhead['name']);
        $this->assertSame(StoreSettingService::DEFAULT_RECEIPT_FOOTER, $this->store->run(fn (): string => app(StoreSettingService::class)->receiptFooter()));
    }

    public function test_the_settings_are_validated(): void
    {
        $put = fn (array $overrides) => $this->actingAs($this->admin)->put($this->url('update'), $this->body($overrides));

        $put(['paper_width' => 70])->assertSessionHasErrors(['paper_width' => 'Lebar kertas nota hanya 58 atau 80 mm.']);
        $put(['large_variance_threshold' => -1])->assertSessionHasErrors(['large_variance_threshold' => 'Ambang selisih harus lebih dari 0. Kosongkan untuk tanpa batas.']);
        $put(['large_variance_threshold' => 0])->assertSessionHasErrors(['large_variance_threshold' => 'Ambang selisih harus lebih dari 0. Kosongkan untuk tanpa batas.']);
        $put(['large_variance_threshold' => '1500.5'])->assertSessionHasErrors(['large_variance_threshold' => 'Ambang selisih harus bilangan bulat rupiah.']);
        $put(['large_variance_threshold' => 'banyak'])->assertSessionHasErrors('large_variance_threshold');
        $put(['auto_print_receipt' => null])->assertSessionHasErrors('auto_print_receipt');
        $put(['receipt_footer' => str_repeat('a', 1001)])->assertSessionHasErrors('receipt_footer');
    }

    public function test_the_receipt_prints_the_name_footer_and_paper_width_of_the_settings(): void
    {
        $this->save(['receipt_name' => 'MITRA UTAMA', 'receipt_address' => 'Jl. Poros Kuprik', 'receipt_phone' => '0813-5411-1168', 'receipt_footer' => 'Terima kasih', 'paper_width' => 58]);
        $sale = $this->sellBrushes($this->openShiftFor($this->cashier));

        $sheetUrl = $this->actingAs($this->cashier)->postJson(route('tenant.cashier.receipts.print', ['tenant' => $this->store->code, 'sale' => $sale->id]))->json('data.sheet_url');

        $this->actingAs($this->cashier)->get($sheetUrl)->assertInertia(fn (Assert $page) => $page
            ->where('receipt.store', ['name' => 'MITRA UTAMA', 'address' => 'Jl. Poros Kuprik', 'phone' => '0813-5411-1168'])
            ->where('receipt.footer', 'Terima kasih')
            ->where('receipt.paper_width', 58));
    }

    public function test_a_receipt_without_settings_prints_the_store_data_and_the_default_footer(): void
    {
        $sale = $this->sellBrushes($this->openShiftFor($this->cashier));
        $sheetUrl = $this->actingAs($this->cashier)->postJson(route('tenant.cashier.receipts.print', ['tenant' => $this->store->code, 'sale' => $sale->id]))->json('data.sheet_url');

        $this->actingAs($this->cashier)->get($sheetUrl)->assertInertia(fn (Assert $page) => $page
            ->where('receipt.store', ['name' => 'Toko Uji', 'address' => 'Jl. Uji 1', 'phone' => '021-111'])
            ->where('receipt.footer', StoreSettingService::DEFAULT_RECEIPT_FOOTER)
            ->where('receipt.paper_width', 80));
    }

    public function test_the_delivery_note_prints_the_name_of_the_settings_too(): void
    {
        $this->save(['receipt_name' => 'MITRA UTAMA']);
        $shift = $this->openShiftFor($this->cashier);
        $sale = $this->store->run(fn (): Sale => app(SaleService::class)->create($shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($this->brush, 'PCS')->id, 'quantity' => 2]],
            'payments' => [['method' => 'cod', 'amount' => 20000]],
            'delivery_note' => ['recipient_name' => 'Pak Budi', 'recipient_address' => 'Jl. Mawar 5'],
        ]));
        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->where('sale_id', $sale->id)->sole());

        $sheetUrl = $this->actingAs($this->cashier)->postJson(route('tenant.cashier.delivery-notes.print', ['tenant' => $this->store->code, 'delivery_note' => $note->id]))->json('data.sheet_url');

        $this->actingAs($this->cashier)->get($sheetUrl)->assertInertia(fn (Assert $page) => $page->where('note.store.name', 'MITRA UTAMA'));
    }

    public function test_a_variance_worth_more_than_the_limit_needs_a_note_to_be_posted(): void
    {
        $this->save(['large_variance_threshold' => '10000']);
        $opname = $this->opnameWithCount(StockOpnameStatus::Review, '98');

        $this->postOpname($opname)->assertSessionHas('error', 'Selisih Kuas bernilai di atas Rp 10.000, jadi wajib diberi catatan. Klik Hitung Ulang, isi catatannya di baris barang itu, lalu selesaikan hitung dan posting lagi.');

        $this->assertSame('100.000', $this->stockInPieces($this->brush));
        $this->assertSame(StockOpnameStatus::Review, $this->store->run(fn (): StockOpnameStatus => $opname->fresh()->status));
    }

    public function test_a_large_variance_with_a_note_is_posted(): void
    {
        $this->save(['large_variance_threshold' => '10000']);
        $opname = $this->opnameWithCount(StockOpnameStatus::Review, '98', 'Dua kuas rusak');

        $this->postOpname($opname)->assertSessionHasNoErrors();

        $this->assertSame('98.000', $this->stockInPieces($this->brush));
    }

    public function test_a_small_variance_needs_no_note(): void
    {
        $this->save(['large_variance_threshold' => '10000']);
        $opname = $this->opnameWithCount(StockOpnameStatus::Review, '99');

        $this->postOpname($opname)->assertSessionHasNoErrors();

        $this->assertSame('99.000', $this->stockInPieces($this->brush));
    }

    public function test_without_a_limit_any_variance_is_posted(): void
    {
        $opname = $this->opnameWithCount(StockOpnameStatus::Review, '40');

        $this->postOpname($opname)->assertSessionHasNoErrors();

        $this->assertSame('40.000', $this->stockInPieces($this->brush));
    }

    public function test_a_product_being_counted_cannot_be_sold_when_sales_are_locked(): void
    {
        $this->save(['lock_sales_during_opname' => true]);
        $this->opnameWithCount(StockOpnameStatus::Counting, null);
        $shift = $this->openShiftFor($this->cashier);

        $this->actingAs($this->cashier)->post(route('tenant.cashier.sales.store', ['tenant' => $this->store->code]), [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($this->brush, 'PCS')->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10000]],
        ])->assertSessionHas('error', 'Kuas sedang dihitung di stok opname dan belum bisa dijual. Hapus dari keranjang, atau tunggu opname selesai.');

        $this->assertSame('100.000', $this->stockInPieces($this->brush));
        $this->assertSame(0, $this->store->run(fn (): int => Sale::query()->where('cash_shift_id', $shift->id)->count()));
    }

    public function test_the_till_shows_a_product_being_counted_as_not_for_sale(): void
    {
        $this->save(['lock_sales_during_opname' => true]);
        $this->opnameWithCount(StockOpnameStatus::Review);
        $this->openShiftFor($this->cashier);

        $this->actingAs($this->cashier)->getJson(route('tenant.cashier.products', ['tenant' => $this->store->code, 'q' => 'Kuas']))->assertOk()->assertJsonPath('data.0.is_being_counted', true);
    }

    public function test_sales_go_on_when_they_are_not_locked_or_the_opname_is_not_counting(): void
    {
        $shift = $this->openShiftFor($this->cashier);
        $this->opnameWithCount(StockOpnameStatus::Counting, null);

        $this->sellBrushes($shift, 1);

        $this->save(['lock_sales_during_opname' => true]);
        $this->store->run(fn () => StockOpname::query()->update(['status' => StockOpnameStatus::Posted]));
        $this->sellBrushes($shift, 1);

        $this->assertSame('98.000', $this->stockInPieces($this->brush));
        $this->actingAs($this->cashier)->getJson(route('tenant.cashier.products', ['tenant' => $this->store->code, 'q' => 'Kuas']))->assertJsonPath('data.0.is_being_counted', false);
    }

    public function test_another_product_can_still_be_sold_while_one_is_counted(): void
    {
        $this->save(['lock_sales_during_opname' => true]);
        $this->opnameWithCount(StockOpnameStatus::Counting, null);
        $shift = $this->openShiftFor($this->cashier);
        $paint = $this->sellableProduct('Cat', '50');

        $this->store->run(fn (): Sale => app(SaleService::class)->create($shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($paint, 'PCS')->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10000]],
        ]));

        $this->assertSame('49.000', $this->stockInPieces($paint));
    }

    public function test_a_large_variance_needs_no_note_in_an_opening_stock_count(): void
    {
        $this->save(['large_variance_threshold' => '10000']);
        $opname = $this->opnameWithCount(StockOpnameStatus::Review, '98', null, StockOpnameReason::OpeningBalance);

        $this->postOpname($opname)->assertSessionHasNoErrors();

        $this->assertSame('98.000', $this->stockInPieces($this->brush));
    }

    public function test_the_note_message_names_five_products_and_counts_the_rest(): void
    {
        $names = array_map(fn (int $number): string => "Barang {$number}", range(1, 7));

        $message = StockOpnameException::largeVarianceNeedsNote($names, '10.000')->getMessage();

        $this->assertStringContainsString('Selisih Barang 1, Barang 2, Barang 3, Barang 4, Barang 5 dan 2 barang lain bernilai di atas Rp 10.000', $message);
        $this->assertStringNotContainsString('Barang 6', $message);
    }

    public function test_a_limit_that_was_stored_with_decimals_still_shows_the_page(): void
    {
        $this->store->run(fn () => app(StoreSettingService::class)->update(['paper_width' => 80, 'lock_sales_during_opname' => false, 'large_variance_threshold' => '1500.5']));

        $this->actingAs($this->admin)->get($this->url('index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('settings.large_variance_threshold', '1501'));
    }

    public function test_one_save_writes_the_settings_the_opname_reasons_and_the_drivers_together(): void
    {
        $stockCheck = $this->store->run(fn (): OpnameReason => OpnameReason::query()->where('name', 'Cek stok')->firstOrFail());
        $joko = $this->store->run(fn (): Driver => Driver::factory()->create(['name' => 'Pak Joko']));

        $this->save([
            'paper_width' => 58,
            'opname_reasons' => [['id' => $stockCheck->id, 'name' => 'Cek stok rutin', 'is_active' => true], ['id' => null, 'name' => 'Kadaluarsa', 'is_active' => true]],
            'drivers' => [['id' => $joko->id, 'name' => 'Pak Joko', 'is_active' => false], ['id' => null, 'name' => 'Pak Adi', 'is_active' => true]],
        ]);

        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('settings.paper_width', 58)
            ->where('opname_reasons.1.name', 'Cek stok rutin')
            ->where('opname_reasons.4.name', 'Kadaluarsa')
            ->where('drivers.0.name', 'Pak Adi')
            ->where('drivers.1', ['id' => $joko->id, 'name' => 'Pak Joko', 'is_active' => false]));
    }

    public function test_a_list_that_is_left_out_of_the_save_is_left_as_it_is(): void
    {
        $this->store->run(fn () => Driver::factory()->create(['name' => 'Pak Joko']));

        $this->save(['paper_width' => 58]);

        $this->assertSame(['Pak Joko'], $this->store->run(fn (): array => Driver::query()->pluck('name')->all()));
        $this->assertSame(4, $this->store->run(fn (): int => OpnameReason::query()->count()));
    }

    public function test_nothing_is_saved_when_two_names_swap_places_and_meet_at_the_unique_name(): void
    {
        $joko = $this->store->run(fn (): Driver => Driver::factory()->create(['name' => 'Pak Joko']));
        $budi = $this->store->run(fn (): Driver => Driver::factory()->create(['name' => 'Pak Budi']));

        $this->actingAs($this->admin)->put($this->url('update'), $this->body([
            'paper_width' => 58,
            'drivers' => [['id' => $joko->id, 'name' => 'Pak Budi', 'is_active' => true], ['id' => $budi->id, 'name' => 'Pak Joko', 'is_active' => true]],
        ]))->assertSessionHas('error', 'Ada nama yang bentrok saat disimpan, sehingga tidak ada yang tersimpan. Ubah nama satu per satu, lalu simpan lagi.');

        $this->assertSame(['Pak Joko', 'Pak Budi'], $this->store->run(fn (): array => [$joko->fresh()->name, $budi->fresh()->name]));
        $this->assertSame(80, $this->store->run(fn (): int => app(StoreSettingService::class)->paperWidth()));
    }

    public function test_a_first_save_creates_every_setting_once_even_when_one_row_already_exists(): void
    {
        $this->store->run(fn () => StoreSetting::query()->create(['key' => 'paper_width', 'value' => '58']));

        $this->save(['receipt_name' => 'Toko Baru', 'paper_width' => 80]);
        $this->save(['receipt_name' => 'Toko Baru 2', 'paper_width' => 80]);

        $this->store->run(function (): void {
            $this->assertSame(8, StoreSetting::query()->count());
            $this->assertSame(1, StoreSetting::query()->where('key', 'paper_width')->count());
            $this->assertSame('80', StoreSetting::query()->where('key', 'paper_width')->value('value'));
            $this->assertSame('Toko Baru 2', StoreSetting::query()->where('key', 'receipt_name')->value('value'));
        });
    }

    public function test_a_change_of_the_settings_is_audited(): void
    {
        config(['audit.console' => true]);
        $this->save(['paper_width' => 80]);

        $this->save(['paper_width' => 58]);

        $audit = $this->store->run(fn () => StoreSetting::query()->where('key', 'paper_width')->sole()->audits()->latest('id')->first());
        $this->assertSame(['updated', '58'], [$audit->event, $audit->new_values['value']]);
    }

    public function test_the_service_reads_the_settings_once_and_sees_a_save_made_through_it(): void
    {
        [$reads, $widthAfterSave] = $this->store->run(function (): array {
            $service = app(StoreSettingService::class);
            DB::enableQueryLog();

            $service->letterhead();
            $service->receiptFooter();
            $service->paperWidth();
            $reads = count(array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'from `settings`')));

            $service->update(['paper_width' => 58, 'lock_sales_during_opname' => false]);

            return [$reads, $service->paperWidth()];
        });

        $this->assertSame([1, 58], [$reads, $widthAfterSave]);
    }
}
