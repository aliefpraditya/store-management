<?php

namespace Tests\Feature\Tenancy;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Store;
use App\Models\User;
use App\Services\SaleService;
use App\Services\ShiftService;
use App\Services\StoreSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    private function opnameWithCount(StockOpnameStatus $status, ?string $counted = '98', ?string $reason = null): StockOpname
    {
        return $this->store->run(function () use ($status, $counted, $reason): StockOpname {
            $opname = StockOpname::factory()->create([
                'warehouse_id' => $this->mainWarehouse()->id,
                'reason_code' => StockOpnameReason::Correction,
                'scope' => StockOpnameScope::Selected,
                'status' => $status,
                'snapshot_at' => now(),
                'created_by' => $this->admin->id,
            ]);
            StockOpnameItem::factory()->create(['stock_opname_id' => $opname->id, 'product_id' => $this->brush->id, 'snapshot_quantity' => '100', 'counted_quantity' => $counted, 'reason' => $reason]);

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
        $this->actingAs($this->cashier)->put($this->url('registers.update', ['register' => $this->register()->id]), ['fixed_opening_cash' => 1000])->assertForbidden();
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
            ->has('registers', 1)
            ->where('registers.0.code', 'K1')
            ->where('registers.0.has_open_shift', false));
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
        $put(['large_variance_threshold' => -1])->assertSessionHasErrors(['large_variance_threshold' => 'Ambang selisih tidak boleh negatif.']);
        $put(['large_variance_threshold' => 'banyak'])->assertSessionHasErrors('large_variance_threshold');
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

    public function test_the_fixed_opening_cash_of_a_register_is_changed_for_the_next_shift(): void
    {
        $register = $this->register();

        $this->actingAs($this->admin)->put($this->url('registers.update', ['register' => $register->id]), ['fixed_opening_cash' => 250000])->assertSessionHasNoErrors()->assertSessionHas('success');

        $shift = $this->openShiftFor($this->cashier);
        $this->assertSame('250000.00', $shift->opening_cash);
    }

    public function test_a_running_shift_keeps_the_opening_cash_it_started_with(): void
    {
        $shift = $this->openShiftFor($this->cashier);

        $this->actingAs($this->admin)->put($this->url('registers.update', ['register' => $this->register()->id]), ['fixed_opening_cash' => 250000])->assertSessionHasNoErrors();

        $this->assertSame('0.00', $this->store->run(fn (): string => app(ShiftService::class)->summarize($shift)['opening_cash']));
        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('registers.0.has_open_shift', true)
            ->where('registers.0.fixed_opening_cash', '250000.00'));
    }

    public function test_the_opening_cash_is_validated(): void
    {
        $put = fn ($value) => $this->actingAs($this->admin)->put($this->url('registers.update', ['register' => $this->register()->id]), ['fixed_opening_cash' => $value]);

        $put(-5)->assertSessionHasErrors(['fixed_opening_cash' => 'Modal kas tetap tidak boleh negatif.']);
        $put('')->assertSessionHasErrors('fixed_opening_cash');
        $put('abc')->assertSessionHasErrors('fixed_opening_cash');
        $this->assertSame('0.00', $this->store->run(fn (): string => Register::query()->where('code', 'K1')->value('fixed_opening_cash')));
    }

    public function test_a_variance_worth_more_than_the_limit_needs_a_reason_to_be_posted(): void
    {
        $this->save(['large_variance_threshold' => '10000']);
        $opname = $this->opnameWithCount(StockOpnameStatus::Review, '98');

        $this->postOpname($opname)->assertSessionHas('error', 'Selisih Kuas bernilai di atas Rp 10.000, jadi wajib diberi alasan. Isi alasannya di baris barang itu, lalu posting lagi.');

        $this->assertSame('100.000', $this->stockInPieces($this->brush));
        $this->assertSame(StockOpnameStatus::Review, $this->store->run(fn (): StockOpnameStatus => $opname->fresh()->status));
    }

    public function test_a_large_variance_with_a_reason_is_posted(): void
    {
        $this->save(['large_variance_threshold' => '10000']);
        $opname = $this->opnameWithCount(StockOpnameStatus::Review, '98', 'Dua kuas rusak');

        $this->postOpname($opname)->assertSessionHasNoErrors();

        $this->assertSame('98.000', $this->stockInPieces($this->brush));
    }

    public function test_a_small_variance_needs_no_reason(): void
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
        $this->store->run(fn () => StockOpname::query()->update(['status' => StockOpnameStatus::Draft]));
        $this->sellBrushes($shift, 1);

        $this->store->run(fn () => StockOpname::query()->update(['status' => StockOpnameStatus::Posted]));
        $this->sellBrushes($shift, 1);

        $this->assertSame('97.000', $this->stockInPieces($this->brush));
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
}
