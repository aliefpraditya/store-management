<?php

namespace Tests\Feature\Tenancy;

use App\Enums\StockMovementType;
use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

/**
 * Returned goods wait in a returns warehouse. Its opname (an inspection) counts what is there and, for every unit counted,
 * decides where it goes: back on the shelf of the warehouse it was sold from, to dead stock, or to a DG product.
 */
class StockOpnameReturnsCheckTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_returns_check';

    private Store $store;

    private User $admin;

    private Product $brush;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_returns_check', 'receipt_prefix' => 'TRC']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->brush = $this->sellableProduct('Kuas KUAS-1', '100', ['sku' => 'KUAS-1']);
    }

    private function url(string $route, array $parameters = []): string
    {
        return route("tenant.stock-opnames.{$route}", ['tenant' => $this->store->code, ...$parameters]);
    }

    private function waitInReturns(Product $product, string $quantity): void
    {
        $this->store->run(fn () => app(StockService::class)->record($product->id, $this->returnsWarehouse()->id, StockMovementType::SaleReturn, $quantity));
    }

    private function stockOf(Product $product, string $warehouseCode): string
    {
        return $this->store->run(fn (): string => (string) (Stock::query()
            ->where('product_id', $product->id)
            ->whereIn('warehouse_id', Warehouse::query()->where('code', $warehouseCode)->select('id'))
            ->value('quantity') ?? '0.000'));
    }

    /**
     * An inspection of the returns warehouse, counted and in review. The reason and the scope typed are on purpose the wrong
     * ones: the server decides them for a returns warehouse.
     */
    private function inspectionCounting(string $counted): StockOpname
    {
        $this->actingAs($this->admin)->post($this->url('store'), [
            'warehouse_id' => $this->returnsWarehouse()->id,
            'opname_date' => now()->toDateString(),
            'scope' => StockOpnameScope::Category->value,
            'reason_code' => StockOpnameReason::Routine->value,
        ])->assertSessionHasNoErrors();

        $opname = $this->store->run(fn (): StockOpname => StockOpname::query()->latest('id')->firstOrFail());
        $item = $this->store->run(fn (): StockOpnameItem => StockOpnameItem::query()->where('stock_opname_id', $opname->id)->firstOrFail());

        $this->actingAs($this->admin)->put($this->url('counts', ['stock_opname' => $opname->id]), ['counts' => [[
            'item_id' => $item->id,
            'parts' => [['unit_code' => 'PCS', 'quantity' => $counted]],
            'reason_id' => null,
            'note' => null,
        ]]])->assertSessionHas('success');
        $this->actingAs($this->admin)->post($this->url('finish', ['stock_opname' => $opname->id]))->assertSessionHas('success');

        return $opname->refresh();
    }

    private function itemOf(StockOpname $opname): StockOpnameItem
    {
        return $this->store->run(fn (): StockOpnameItem => StockOpnameItem::query()->where('stock_opname_id', $opname->id)->firstOrFail());
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function review(StockOpname $opname, array $row): TestResponse
    {
        return $this->actingAs($this->admin)->put($this->url('review', ['stock_opname' => $opname->id]), ['rows' => [[
            'item_id' => $this->itemOf($opname)->id,
            'reason_id' => null,
            'note' => null,
            'is_reviewed' => false,
            'dead_stock_quantity' => null,
            'damaged_goods_quantity' => null,
            'full_price_quantity' => null,
            'damaged_goods_price' => null,
            ...$row,
        ]]]);
    }

    private function postOpname(StockOpname $opname): TestResponse
    {
        return $this->actingAs($this->admin)->post($this->url('post', ['stock_opname' => $opname->id]));
    }

    private function hasMessage(string $part): callable
    {
        return fn (string $message): bool => str_contains($message, $part);
    }

    public function test_the_opname_of_a_returns_warehouse_lists_what_waits_in_it_whatever_reason_and_scope_were_sent(): void
    {
        $this->waitInReturns($this->brush, '5');
        $this->sellableProduct('Cat', '9', ['sku' => 'CAT-1']);

        $this->actingAs($this->admin)->post($this->url('store'), [
            'warehouse_id' => $this->returnsWarehouse()->id,
            'opname_date' => now()->toDateString(),
            'scope' => StockOpnameScope::Category->value,
            'reason_code' => StockOpnameReason::Routine->value,
        ])->assertSessionHasNoErrors();

        $opname = $this->store->run(fn (): StockOpname => StockOpname::query()->sole());
        $this->assertSame([StockOpnameReason::ReturnsCheck, StockOpnameScope::Selected], [$opname->reason_code, $opname->scope]);
        $this->assertSame([$this->brush->id], $this->store->run(fn (): array => $opname->items()->pluck('product_id')->all()));
        $this->assertSame('5.000', (string) $this->itemOf($opname)->snapshot_quantity);
    }

    public function test_the_inspection_of_returns_is_only_for_a_returns_warehouse(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), [
            'warehouse_id' => $this->mainWarehouse()->id,
            'opname_date' => now()->toDateString(),
            'scope' => StockOpnameScope::Selected->value,
            'reason_code' => StockOpnameReason::ReturnsCheck->value,
        ])->assertSessionHasErrors('reason_code');

        $this->assertSame(0, $this->store->run(fn () => StockOpname::query()->count()));
    }

    public function test_the_create_page_marks_the_returns_warehouses(): void
    {
        $this->actingAs($this->admin)->get($this->url('create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/StockOpnames/Create')
            ->where('warehouses', fn ($warehouses): bool => collect($warehouses)->where('is_returns', true)->count() === 1 && collect($warehouses)->count() === 3));
    }

    public function test_every_unit_counted_goes_where_it_was_decided_and_the_returns_warehouse_ends_empty(): void
    {
        $this->waitInReturns($this->brush, '5');
        $opname = $this->inspectionCounting('5');

        $this->review($opname, ['dead_stock_quantity' => 2, 'damaged_goods_quantity' => 1, 'full_price_quantity' => 2, 'damaged_goods_price' => 5000, 'is_reviewed' => true])
            ->assertSessionHas('success', 'Review disimpan.');
        $this->postOpname($opname)->assertSessionHas('success');

        $twin = $this->store->run(fn () => Product::query()->where('damaged_from_product_id', $this->brush->id)->firstOrFail());
        $this->assertSame(StockOpnameStatus::Posted, $this->store->run(fn (): StockOpnameStatus => $opname->fresh()->status));
        $this->assertSame('0.000', $this->stockOf($this->brush, 'RETUR'));
        $this->assertSame('102.000', $this->stockOf($this->brush, 'MAIN'));
        $this->assertSame('2.000', $this->stockOf($this->brush, 'DEAD'));
        $this->assertSame('1.000', $this->stockOf($twin, 'MAIN'));
    }

    public function test_an_item_with_no_decision_cannot_be_posted(): void
    {
        $this->waitInReturns($this->brush, '5');
        $opname = $this->inspectionCounting('5');

        $this->postOpname($opname)->assertSessionHas('error', 'Selisih belum selesai direview: Kuas KUAS-1 (belum ditentukan ke mana barang pergi).');

        $this->assertSame('5.000', $this->stockOf($this->brush, 'RETUR'));
        $this->assertSame('100.000', $this->stockOf($this->brush, 'MAIN'));
    }

    public function test_the_decision_has_to_add_up_to_what_was_counted(): void
    {
        $this->waitInReturns($this->brush, '5');
        $opname = $this->inspectionCounting('5');

        $this->review($opname, ['dead_stock_quantity' => 1, 'damaged_goods_quantity' => 0, 'full_price_quantity' => 1, 'is_reviewed' => true])
            ->assertSessionHas('error', $this->hasMessage('pembagian barang harus berjumlah sama dengan yang dihitung (5)'));
    }

    public function test_goods_for_a_dg_product_need_a_price(): void
    {
        $this->waitInReturns($this->brush, '5');
        $opname = $this->inspectionCounting('5');

        $this->review($opname, ['dead_stock_quantity' => 0, 'damaged_goods_quantity' => 5, 'full_price_quantity' => 0, 'is_reviewed' => true])
            ->assertSessionHas('error', $this->hasMessage('harga jual barang DG belum diisi'));
    }

    public function test_a_count_below_the_books_needs_no_reason_and_is_written_as_a_shortage(): void
    {
        $this->waitInReturns($this->brush, '5');
        $opname = $this->inspectionCounting('4');
        $decision = ['dead_stock_quantity' => 0, 'damaged_goods_quantity' => 0, 'full_price_quantity' => 4, 'is_reviewed' => true];

        $this->review($opname, $decision)->assertSessionHas('success');
        $this->postOpname($opname)->assertSessionHas('success');

        $this->assertSame('0.000', $this->stockOf($this->brush, 'RETUR'));
        $this->assertSame('104.000', $this->stockOf($this->brush, 'MAIN'));
        $note = $this->store->run(fn (): ?string => StockMovement::query()->where('product_id', $this->brush->id)->where('type', StockMovementType::OpnameAdjustment)->orderBy('id')->value('note'));
        $this->assertSame('Pemeriksaan Retur', $note);
    }

    public function test_a_count_above_the_books_is_written_as_an_extra_before_everything_is_released(): void
    {
        $this->waitInReturns($this->brush, '5');
        $opname = $this->inspectionCounting('7');

        $this->review($opname, ['dead_stock_quantity' => 0, 'damaged_goods_quantity' => 0, 'full_price_quantity' => 7, 'is_reviewed' => true])->assertSessionHas('success');
        $this->postOpname($opname)->assertSessionHas('success');

        $this->assertSame('0.000', $this->stockOf($this->brush, 'RETUR'));
        $this->assertSame('107.000', $this->stockOf($this->brush, 'MAIN'));
        $this->assertSame(1, $this->store->run(fn (): int => StockMovement::query()->where('product_id', $this->brush->id)->where('type', StockMovementType::OpnameAdjustment)->where('quantity', '2.000')->count()));
    }

    public function test_a_product_another_active_opname_of_the_returns_warehouse_holds_is_left_out(): void
    {
        $this->waitInReturns($this->brush, '5');
        $this->actingAs($this->admin)->post($this->url('store'), [
            'warehouse_id' => $this->returnsWarehouse()->id,
            'opname_date' => now()->toDateString(),
            'scope' => StockOpnameScope::Selected->value,
            'reason_code' => StockOpnameReason::ReturnsCheck->value,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post($this->url('store'), [
            'warehouse_id' => $this->returnsWarehouse()->id,
            'opname_date' => now()->toDateString(),
            'scope' => StockOpnameScope::Selected->value,
            'reason_code' => StockOpnameReason::ReturnsCheck->value,
        ])->assertSessionHasNoErrors();

        $itemCounts = $this->store->run(fn (): array => StockOpname::query()->orderBy('id')->withCount('items')->get()->pluck('items_count')->all());
        $this->assertSame([1, 0], $itemCounts);
    }

    public function test_goods_for_dead_stock_cannot_be_decided_without_a_dead_stock_warehouse(): void
    {
        $this->waitInReturns($this->brush, '5');
        $opname = $this->inspectionCounting('5');
        $this->store->run(fn () => Warehouse::query()->where('code', 'DEAD')->update(['is_active' => false]));

        $this->review($opname, ['dead_stock_quantity' => 2, 'damaged_goods_quantity' => 0, 'full_price_quantity' => 3, 'is_reviewed' => true])
            ->assertSessionHas('error', $this->hasMessage('gudang dead stock tidak ada'));
    }

    public function test_the_returns_warehouses_cannot_be_rolled_back_once_goods_wait_in_them(): void
    {
        $this->waitInReturns($this->brush, '5');
        $migration = require database_path('migrations/tenant/2026_11_08_100000_add_returns_warehouses.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot roll back the returns warehouses');

        $this->store->run(fn () => $migration->down());
    }

    public function test_a_list_in_place_of_a_warehouse_id_is_refused_instead_of_failing(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), [
            'warehouse_id' => [$this->returnsWarehouse()->id, $this->mainWarehouse()->id],
            'opname_date' => now()->toDateString(),
            'scope' => StockOpnameScope::Selected->value,
            'reason_code' => StockOpnameReason::Routine->value,
        ])->assertSessionHasErrors('warehouse_id');
    }

    public function test_the_confirmation_of_an_inspection_says_what_it_will_do(): void
    {
        $this->waitInReturns($this->brush, '5');
        $opname = $this->inspectionCounting('5');
        $this->review($opname, ['dead_stock_quantity' => 1, 'damaged_goods_quantity' => 1, 'full_price_quantity' => 3, 'damaged_goods_price' => 5000, 'is_reviewed' => true])->assertSessionHas('success');

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertInertia(fn (Assert $page) => $page
            ->where('opname.is_returns_check', true)
            ->where('summary.counted_count', 1)
            ->where('damage_summary.items.0.dead_stock_quantity', '1.000')
            ->where('damage_summary.items.0.full_price_quantity', '3.000'));
    }

    public function test_the_summary_counts_every_counted_item_as_one_to_review(): void
    {
        $this->waitInReturns($this->brush, '5');
        $opname = $this->inspectionCounting('5');

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('opname.is_returns_check', true)
            ->where('opname.can_review', true)
            ->where('summary.variance_count', 0)
            ->where('summary.review_count', 1)
            ->where('summary.reviewed_count', 0)
            ->where('items.data.0.needs_damage_decision', true));

        $this->review($opname, ['dead_stock_quantity' => 0, 'damaged_goods_quantity' => 0, 'full_price_quantity' => 5, 'is_reviewed' => true]);

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertInertia(fn (Assert $page) => $page->where('summary.reviewed_count', 1));
    }

    public function test_the_item_list_shows_how_much_waits_in_the_returns_warehouse(): void
    {
        $this->waitInReturns($this->brush, '5');

        $this->actingAs($this->admin)->get(route('tenant.products.index', ['tenant' => $this->store->code]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.sellable_stock', '100.000')
            ->where('products.data.0.returns_stock', '5.000'));
    }
}
