<?php

namespace Tests\Feature\Tenancy;

use App\Enums\OpnameReasonBehavior;
use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Enums\UserRole;
use App\Models\OpnameReason;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StoreSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class StockOpnameReviewTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_opname_review';

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_opname_review', 'receipt_prefix' => 'TOR']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
    }

    private function url(string $route, array $parameters = []): string
    {
        return route("tenant.stock-opnames.{$route}", ['tenant' => $this->store->code, ...$parameters]);
    }

    private function reasonId(string $name): int
    {
        return $this->store->run(fn (): int => OpnameReason::query()->where('name', $name)->value('id'));
    }

    /**
     * A product with 10 pieces in stock whose SKU is KUAS-1; PCS is the base unit and 1 DUS holds 12.
     */
    private function brush(string $sku = 'KUAS-1', string $stock = '10'): Product
    {
        return $this->sellableProduct('Kuas '.$sku, $stock, ['sku' => $sku]);
    }

    /**
     * A routine opname in review that counted the given quantity of the product (the stock is what it was when the opname
     * was made).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function opnameCounting(Product $product, ?string $counted, array $attributes = []): array
    {
        return $this->store->run(function () use ($product, $counted, $attributes): array {
            $opname = StockOpname::factory()->create([
                'warehouse_id' => $this->mainWarehouse()->id,
                'reason_code' => StockOpnameReason::Routine,
                'scope' => StockOpnameScope::Selected,
                'status' => StockOpnameStatus::Review,
                'snapshot_at' => now(),
                'created_by' => $this->admin->id,
                ...$attributes,
            ]);
            $item = StockOpnameItem::factory()->create([
                'stock_opname_id' => $opname->id,
                'product_id' => $product->id,
                'snapshot_quantity' => '10',
                'counted_quantity' => $counted,
            ]);

            return [$opname, $item];
        });
    }

    /**
     * One review row as the screen sends it.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function row(StockOpnameItem $item, array $row = []): array
    {
        return [
            'item_id' => $item->id,
            'reason_id' => null,
            'note' => null,
            'is_reviewed' => false,
            'dead_stock_quantity' => null,
            'damaged_goods_quantity' => null,
            'full_price_quantity' => null,
            'damaged_goods_price' => null,
            ...$row,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function review(StockOpname $opname, StockOpnameItem $item, array $row = []): TestResponse
    {
        return $this->actingAs($this->admin)->put($this->url('review', ['stock_opname' => $opname->id]), ['rows' => [$this->row($item, $row)]]);
    }

    private function postOpname(StockOpname $opname): TestResponse
    {
        return $this->actingAs($this->admin)->post($this->url('post', ['stock_opname' => $opname->id]));
    }

    private function stockOf(Product $product, string $warehouseCode = 'MAIN'): string
    {
        return $this->store->run(fn (): string => (string) (Stock::query()
            ->where('product_id', $product->id)
            ->whereIn('warehouse_id', Warehouse::query()->where('code', $warehouseCode)->select('id'))
            ->value('quantity') ?? '0.000'));
    }

    private function shortageDecision(array $overrides = []): array
    {
        return ['reason_id' => $this->reasonId('Rusak'), 'dead_stock_quantity' => 1, 'damaged_goods_quantity' => 1, 'full_price_quantity' => 1, 'damaged_goods_price' => 5000, 'is_reviewed' => true, ...$overrides];
    }

    private function twinOf(Product $product): ?Product
    {
        return $this->store->run(fn () => Product::query()->where('damaged_from_product_id', $product->id)->first());
    }

    public function test_every_store_starts_with_the_four_reasons(): void
    {
        $reasons = $this->store->run(fn (): array => OpnameReason::query()->orderBy('id')->get()->map(fn (OpnameReason $reason): array => [$reason->name, $reason->behavior, $reason->is_system, $reason->is_active])->all());

        $this->assertSame([
            ['Pembelian', OpnameReasonBehavior::Plain, true, true],
            ['Cek stok', OpnameReasonBehavior::Plain, true, true],
            ['Hilang', OpnameReasonBehavior::Plain, true, true],
            ['Rusak', OpnameReasonBehavior::Damaged, true, true],
        ], $reasons);
    }

    public function test_a_variance_cannot_be_posted_without_a_reason_and_a_review(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '8');

        $this->postOpname($opname)->assertSessionHas('error', 'Selisih belum selesai direview: Kuas KUAS-1 (alasan belum dipilih).');

        $this->review($opname, $item, ['reason_id' => $this->reasonId('Hilang')])->assertSessionHas('success');
        $this->postOpname($opname)->assertSessionHas('error', 'Selisih belum selesai direview: Kuas KUAS-1 (belum ditandai sudah direview).');

        $this->assertSame('10.000', $this->stockOf($product));
        $this->assertSame(StockOpnameStatus::Review, $this->store->run(fn (): StockOpnameStatus => $opname->fresh()->status));
    }

    public function test_a_reviewed_variance_is_posted_with_its_reason_and_note_on_the_ledger(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '8');

        $this->review($opname, $item, ['reason_id' => $this->reasonId('Hilang'), 'note' => 'dicuri', 'is_reviewed' => true])->assertSessionHasNoErrors()->assertSessionHas('success', 'Review disimpan.');
        $this->postOpname($opname)->assertSessionHas('success');

        $this->assertSame('8.000', $this->stockOf($product));
        $item = $this->store->run(fn () => $item->fresh());
        $this->assertSame([$this->admin->id, true], [$item->reviewed_by, $item->reviewed_at !== null]);
        $this->assertSame('Hilang: dicuri', $this->store->run(fn () => StockMovement::query()->where('product_id', $product->id)->sole()->note));
    }

    public function test_items_without_a_variance_need_no_reason_or_review(): void
    {
        $product = $this->brush();
        [$opname] = $this->opnameCounting($product, '10');

        $this->postOpname($opname)->assertSessionHas('success');

        $this->assertSame('10.000', $this->stockOf($product));
    }

    public function test_arrivals_and_legacy_opnames_are_not_asked_for_a_review(): void
    {
        $arrival = $this->brush('KUAS-A');
        [$arrivalOpname] = $this->opnameCounting($arrival, '5', ['reason_code' => StockOpnameReason::StockIncrease]);
        $legacy = $this->brush('KUAS-B');
        [$legacyOpname] = $this->opnameCounting($legacy, '7', ['reason_code' => StockOpnameReason::Lost]);

        $this->postOpname($arrivalOpname)->assertSessionHas('success');
        $this->postOpname($legacyOpname)->assertSessionHas('success');

        $this->assertSame(['15.000', '7.000'], [$this->stockOf($arrival), $this->stockOf($legacy)]);
    }

    public function test_an_item_cannot_be_marked_reviewed_without_a_reason_or_before_it_is_counted(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '8');
        [, $uncounted] = $this->opnameCounting($this->brush('KUAS-2'), null);

        $this->review($opname, $item, ['is_reviewed' => true])->assertSessionHas('error', 'Kuas KUAS-1: alasan belum dipilih');
        $this->review($uncounted->stockOpname, $uncounted, ['reason_id' => $this->reasonId('Hilang'), 'is_reviewed' => true])
            ->assertSessionHas('error', 'Kuas KUAS-2: belum dihitung, jadi belum bisa ditandai sudah direview.');
    }

    public function test_the_review_can_only_be_saved_while_the_opname_is_in_review(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '8', ['status' => StockOpnameStatus::Counting]);

        $this->review($opname, $item, ['reason_id' => $this->reasonId('Hilang')])->assertSessionHas('error', 'Opname berstatus Menghitung, jadi tidak bisa direview.');
    }

    public function test_reopening_the_count_clears_every_review_mark(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '8');
        $this->review($opname, $item, ['reason_id' => $this->reasonId('Hilang'), 'is_reviewed' => true]);

        $this->actingAs($this->admin)->post($this->url('reopen', ['stock_opname' => $opname->id]))->assertSessionHas('success');

        $item = $this->store->run(fn () => $item->fresh());
        $this->assertSame([null, null], [$item->reviewed_at, $item->reviewed_by]);
        $this->assertSame($this->reasonId('Hilang'), $item->reason_id);
    }

    public function test_the_review_is_validated(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '8');
        [, $otherItem] = $this->opnameCounting($this->brush('KUAS-2'), '8');
        $inactive = $this->store->run(fn (): OpnameReason => OpnameReason::factory()->create(['is_active' => false]));

        $this->review($opname, $item, ['reason_id' => 9999])->assertSessionHasErrors(['rows.0.reason_id' => 'Alasan tidak dikenal.']);
        $this->review($opname, $item, ['reason_id' => $inactive->id])->assertSessionHasErrors(['rows.0.reason_id' => 'Alasan itu sudah dinonaktifkan.']);
        $this->review($opname, $otherItem)->assertSessionHasErrors(['rows.0.item_id' => 'Barang tidak ada di opname ini.']);
        $this->review($opname, $item, ['dead_stock_quantity' => -1])->assertSessionHasErrors('rows.0.dead_stock_quantity');
        $this->review($opname, $item, ['damaged_goods_quantity' => 'banyak'])->assertSessionHasErrors('rows.0.damaged_goods_quantity');
        $this->review($opname, $item, ['damaged_goods_price' => -5])->assertSessionHasErrors('rows.0.damaged_goods_price');
        $this->review($opname, $item, ['is_reviewed' => 'mungkin'])->assertSessionHasErrors('rows.0.is_reviewed');
    }

    public function test_an_item_may_keep_a_reason_that_was_switched_off_later(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '8');
        $custom = $this->store->run(fn (): OpnameReason => OpnameReason::factory()->create(['name' => 'Tumpah']));
        $this->review($opname, $item, ['reason_id' => $custom->id])->assertSessionHasNoErrors();
        $this->store->run(fn () => $custom->forceFill(['is_active' => false])->save());

        $this->review($opname, $item, ['reason_id' => $custom->id, 'is_reviewed' => true])->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    public function test_damaged_goods_are_split_into_dead_stock_a_dg_product_and_full_price(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '7');

        $this->review($opname, $item, $this->shortageDecision())->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->postOpname($opname)->assertSessionHas('success');

        $twin = $this->twinOf($product);
        $this->assertSame(['KUAS-1-DG', '(DG) Kuas KUAS-1', true], [$twin->sku, $twin->name, $twin->is_damaged_goods]);
        $this->assertSame([$product->category_id, $product->base_unit_code, '6000.00'], [$twin->category_id, $twin->base_unit_code, $twin->cost_price]);
        $this->assertSame('8.000', $this->stockOf($product), 'one unit goes to dead stock, one becomes DG, one stays on the shelf');
        $this->assertSame('1.000', $this->stockOf($product, 'DEAD'));
        $this->assertSame('1.000', $this->stockOf($twin));
        $this->assertSame(['5000.00', '60000.00'], $this->store->run(fn (): array => $twin->productUnits()->orderBy('sort_order')->pluck('selling_price')->all()));
        $this->assertSame(['PCS', 'DUS'], $this->store->run(fn (): array => $twin->productUnits()->orderBy('sort_order')->pluck('unit_code')->all()));
        $this->assertSame('12.000', $this->store->run(fn (): string => $twin->productUnits()->where('unit_code', 'DUS')->value('conversion_factor')));
        $item = $this->store->run(fn () => $item->fresh());
        $this->assertSame('-3.000', $item->variance);
    }

    public function test_the_ledger_tells_where_each_damaged_unit_went(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '7');
        $this->review($opname, $item, $this->shortageDecision(['note' => 'jatuh']));
        $this->postOpname($opname)->assertSessionHas('success');

        $movements = $this->store->run(fn (): array => StockMovement::query()->orderBy('id')->get()->map(fn (StockMovement $movement): array => [
            Product::query()->find($movement->product_id)->sku, Warehouse::query()->find($movement->warehouse_id)->code, $movement->quantity, $movement->note,
        ])->all());

        $this->assertSame([
            ['KUAS-1', 'MAIN', '-3.000', 'Rusak: jatuh'],
            ['KUAS-1', 'DEAD', '1.000', 'Rusak: jatuh (dead stock)'],
            ['KUAS-1-DG', 'MAIN', '1.000', 'Rusak: jatuh (barang DG KUAS-1-DG)'],
            ['KUAS-1', 'MAIN', '1.000', 'Rusak: jatuh (layak jual penuh, kembali ke stok jual)'],
        ], $movements);
    }

    public function test_damaged_units_that_are_all_dead_stock_make_no_dg_product(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '7');
        $this->review($opname, $item, $this->shortageDecision(['dead_stock_quantity' => 3, 'damaged_goods_quantity' => 0, 'full_price_quantity' => 0, 'damaged_goods_price' => null]))->assertSessionHas('success');

        $this->postOpname($opname)->assertSessionHas('success');

        $this->assertNull($this->twinOf($product));
        $this->assertSame(['7.000', '3.000'], [$this->stockOf($product), $this->stockOf($product, 'DEAD')]);
    }

    public function test_damaged_units_that_are_all_fine_leave_the_shelf_and_come_back_so_the_history_shows_them_checked(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '7');
        $this->review($opname, $item, $this->shortageDecision(['dead_stock_quantity' => 0, 'damaged_goods_quantity' => 0, 'full_price_quantity' => 3, 'damaged_goods_price' => null]))->assertSessionHas('success');

        $this->postOpname($opname)->assertSessionHas('success');

        $this->assertSame(['10.000', '0.000'], [$this->stockOf($product), $this->stockOf($product, 'DEAD')]);
        $this->assertSame([['-3.000', '7.000'], ['3.000', '10.000']], $this->store->run(fn (): array => StockMovement::query()->orderBy('id')->get()->map(fn (StockMovement $movement): array => [$movement->quantity, $movement->balance_after])->all()));
    }

    public function test_a_dg_product_made_before_is_used_again_and_repriced(): void
    {
        $product = $this->brush();
        [$first, $firstItem] = $this->opnameCounting($product, '9');
        $this->review($first, $firstItem, $this->shortageDecision(['dead_stock_quantity' => 0, 'damaged_goods_quantity' => 1, 'full_price_quantity' => 0, 'damaged_goods_price' => 5000]));
        $this->postOpname($first)->assertSessionHas('success');
        $twin = $this->twinOf($product);

        [$second, $secondItem] = $this->opnameCounting($product, '8', ['number' => 'OPN/TST/SECOND']);
        $this->store->run(fn () => $secondItem->forceFill(['snapshot_quantity' => '9'])->save());
        $this->review($second, $secondItem, $this->shortageDecision(['dead_stock_quantity' => 0, 'damaged_goods_quantity' => 1, 'full_price_quantity' => 0, 'damaged_goods_price' => 4500]));
        $this->postOpname($second)->assertSessionHas('success');

        $this->assertSame(1, $this->store->run(fn (): int => Product::query()->where('damaged_from_product_id', $product->id)->count()));
        $this->assertSame('2.000', $this->stockOf($twin));
        $this->assertSame(['4500.00', '54000.00'], $this->store->run(fn (): array => $twin->productUnits()->orderBy('sort_order')->pluck('selling_price')->all()));
    }

    public function test_a_damaged_decision_must_add_up_to_the_shortage(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '7');

        $this->review($opname, $item, $this->shortageDecision(['full_price_quantity' => 0]))
            ->assertSessionHas('error', 'Kuas KUAS-1: keputusan barang rusak harus berjumlah sama dengan selisih (3)');
        $this->review($opname, $item, $this->shortageDecision(['dead_stock_quantity' => null, 'is_reviewed' => true]))
            ->assertSessionHas('error', 'Kuas KUAS-1: keputusan barang rusak belum diisi');
    }

    public function test_units_made_into_dg_need_a_price(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '7');

        $this->review($opname, $item, $this->shortageDecision(['damaged_goods_price' => null]))
            ->assertSessionHas('error', 'Kuas KUAS-1: harga jual barang DG belum diisi');
    }

    public function test_the_damaged_reason_is_only_for_a_shortage(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '12');

        $this->review($opname, $item, $this->shortageDecision(['dead_stock_quantity' => 2, 'damaged_goods_quantity' => 0, 'full_price_quantity' => 0]))
            ->assertSessionHas('error', 'Kuas KUAS-1: alasan Rusak hanya untuk selisih kurang');
    }

    public function test_a_dg_sku_that_belongs_to_another_product_is_refused(): void
    {
        $product = $this->brush();
        $this->brush('KUAS-1-DG');
        [$opname, $item] = $this->opnameCounting($product, '7');

        $this->review($opname, $item, $this->shortageDecision())->assertSessionHas('error', 'Kuas KUAS-1: SKU KUAS-1-DG sudah dipakai barang lain.');
    }

    public function test_a_dg_product_cannot_be_made_damaged_again_but_may_go_to_dead_stock(): void
    {
        $product = $this->brush();
        $this->store->run(fn () => $product->forceFill(['is_damaged_goods' => true])->save());
        [$opname, $item] = $this->opnameCounting($product, '7');

        $this->review($opname, $item, $this->shortageDecision())->assertSessionHas('error', 'Kuas KUAS-1: sudah barang DG, jadi tidak bisa dibuatkan DG lagi.');
        $this->review($opname, $item, $this->shortageDecision(['dead_stock_quantity' => 3, 'damaged_goods_quantity' => 0, 'full_price_quantity' => 0, 'damaged_goods_price' => null]))->assertSessionHas('success');
    }

    public function test_the_decision_is_forgotten_when_the_reason_is_changed_to_a_plain_one(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '7');
        $this->review($opname, $item, $this->shortageDecision(['is_reviewed' => false]));

        $this->review($opname, $item, ['reason_id' => $this->reasonId('Hilang')])->assertSessionHas('success');

        $item = $this->store->run(fn () => $item->fresh());
        $this->assertSame([null, null, null, null], [$item->dead_stock_quantity, $item->damaged_goods_quantity, $item->full_price_quantity, $item->damaged_goods_price]);
    }

    public function test_a_sale_during_the_review_that_changes_the_shortage_stops_the_posting(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '7');
        $this->review($opname, $item, $this->shortageDecision());
        $this->store->run(fn () => Stock::query()->where('product_id', $product->id)->update(['quantity' => 9]));

        $this->postOpname($opname)->assertSessionHas('error', 'Selisih belum selesai direview: Kuas KUAS-1 (keputusan barang rusak harus berjumlah sama dengan selisih (2)).');

        $this->assertNull($this->twinOf($product));
        $this->assertSame('9.000', $this->stockOf($product));
    }

    public function test_a_failed_posting_leaves_no_dg_product_behind(): void
    {
        $product = $this->brush();
        $plain = $this->brush('KUAS-2');
        [$opname, $item] = $this->opnameCounting($product, '7');
        $this->store->run(fn () => StockOpnameItem::factory()->create(['stock_opname_id' => $opname->id, 'product_id' => $plain->id, 'snapshot_quantity' => '10', 'counted_quantity' => '8']));
        $this->review($opname, $item, $this->shortageDecision());

        $this->postOpname($opname)->assertSessionHas('error');

        $this->assertNull($this->twinOf($product));
        $this->assertSame('10.000', $this->stockOf($product));
    }

    public function test_the_threshold_note_is_still_asked_for_a_large_variance(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '2');
        $this->store->run(fn () => app(StoreSettingService::class)->update(['large_variance_threshold' => '10000']));
        $this->review($opname, $item, ['reason_id' => $this->reasonId('Hilang'), 'is_reviewed' => true]);

        $this->postOpname($opname)->assertSessionHas('error', 'Selisih Kuas KUAS-1 bernilai di atas Rp 10.000, jadi wajib diberi catatan. Klik Hitung Ulang, isi catatannya di baris barang itu, lalu selesaikan hitung dan posting lagi.');
    }

    public function test_the_show_page_gives_the_reasons_the_review_state_and_the_damaged_decision(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '7');
        $this->review($opname, $item, $this->shortageDecision(['is_reviewed' => false, 'note' => 'jatuh']));

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/StockOpnames/Show')
            ->where('opname.requires_review', true)
            ->where('opname.can_review', true)
            ->where('opname.urls.review', $this->url('review', ['stock_opname' => $opname->id]))
            ->where('summary.variance_count', 1)
            ->where('summary.reviewed_count', 0)
            ->has('reasons', 4)
            ->where('reasons.3', ['id' => $this->reasonId('Rusak'), 'name' => 'Rusak', 'is_active' => true, 'needs_damage_decision' => true])
            ->where('reasons.2.needs_damage_decision', false)
            ->where('items.data.0.reason_name', 'Rusak')
            ->where('items.data.0.note', 'jatuh')
            ->where('items.data.0.needs_damage_decision', true)
            ->where('items.data.0.is_reviewed', false)
            ->where('items.data.0.damage.damaged_goods_price', '5000.00')
            ->where('items.data.0.damage.damaged_goods_sku', 'KUAS-1-DG')
            ->where('items.data.0.damage.original_cost_price', '6000.00')
            ->where('items.data.0.damage.original_selling_price', '10000.00')
            ->where('items.data.0.damage.damaged_goods_problem', null));
    }

    public function test_the_show_page_measures_the_loss_against_the_purchase_price_and_the_rest_is_loss_profit(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '7');
        $this->review($opname, $item, $this->shortageDecision(['is_reviewed' => false]));

        // Cost 6.000, selling 10.000, DG sold at 5.000: dead stock loses its cost (6.000) and 4.000 of margin; the DG unit
        // loses 1.000 below cost and 4.000 of margin above it.
        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertInertia(fn (Assert $page) => $page
            ->where('damage_summary.items.0', [
                'product_name' => 'Kuas KUAS-1', 'base_unit' => 'PCS', 'dead_stock_quantity' => '1.000', 'damaged_goods_quantity' => '1.000', 'full_price_quantity' => '1.000',
                'cost_price' => '6000.00', 'original_price' => '10000.00', 'damaged_goods_price' => '5000.00', 'existing_price' => null,
                'loss' => '7000.00', 'loss_profit' => '8000.00',
            ])
            ->where('damage_summary.dead_stock_loss', '6000.00')
            ->where('damage_summary.damaged_goods_loss', '1000.00')
            ->where('damage_summary.total_loss', '7000.00')
            ->where('damage_summary.loss_profit', '8000.00'));
    }

    public function test_a_dg_price_still_above_the_purchase_price_is_only_loss_profit(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '7');
        $this->review($opname, $item, $this->shortageDecision(['dead_stock_quantity' => 0, 'damaged_goods_quantity' => 1, 'full_price_quantity' => 0, 'damaged_goods_price' => 8000, 'is_reviewed' => false]));

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertInertia(fn (Assert $page) => $page
            ->where('damage_summary.items.0.loss', '0.00')
            ->where('damage_summary.items.0.loss_profit', '2000.00')
            ->where('damage_summary.total_loss', '0.00')
            ->where('damage_summary.loss_profit', '2000.00'));
    }

    public function test_a_damaged_decision_that_is_not_complete_is_left_out_of_the_summary(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '7');
        $this->review($opname, $item, ['reason_id' => $this->reasonId('Rusak'), 'dead_stock_quantity' => 1]);

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertInertia(fn (Assert $page) => $page
            ->has('damage_summary.items', 0)
            ->where('damage_summary.total_loss', '0.00')
            ->where('damage_summary.loss_profit', '0.00'));
    }

    public function test_there_is_no_damaged_goods_summary_outside_the_review_of_a_routine_opname(): void
    {
        $product = $this->brush();
        [$counting] = $this->opnameCounting($product, '7', ['status' => StockOpnameStatus::Counting]);
        [$legacy] = $this->opnameCounting($this->brush('KUAS-2'), '7', ['reason_code' => StockOpnameReason::Lost]);

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $counting->id]))->assertInertia(fn (Assert $page) => $page->where('damage_summary', null));
        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $legacy->id]))->assertInertia(fn (Assert $page) => $page->where('damage_summary', null));
    }

    public function test_an_existing_dg_product_and_its_price_are_shown_while_deciding_and_in_the_summary(): void
    {
        $product = $this->brush();
        [$first, $firstItem] = $this->opnameCounting($product, '9');
        $this->review($first, $firstItem, $this->shortageDecision(['dead_stock_quantity' => 0, 'damaged_goods_quantity' => 1, 'full_price_quantity' => 0, 'damaged_goods_price' => 5000]));
        $this->postOpname($first)->assertSessionHas('success');

        [$second, $secondItem] = $this->opnameCounting($product, '8', ['number' => 'OPN/TST/SECOND']);
        $this->review($second, $secondItem, $this->shortageDecision(['dead_stock_quantity' => 0, 'damaged_goods_quantity' => 1, 'full_price_quantity' => 0, 'damaged_goods_price' => 4500, 'is_reviewed' => false]));

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $second->id]))->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.damage.existing', ['name' => '(DG) Kuas KUAS-1', 'sku' => 'KUAS-1-DG', 'price' => '5000.00'])
            ->where('items.data.0.damage.damaged_goods_price', '4500.00')
            ->where('damage_summary.items.0.existing_price', '5000.00')
            ->where('damage_summary.items.0.damaged_goods_price', '4500.00'));
    }

    public function test_a_product_without_a_dg_product_has_nothing_existing(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '7');
        $this->review($opname, $item, $this->shortageDecision(['is_reviewed' => false]));

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertInertia(fn (Assert $page) => $page->where('items.data.0.damage.existing', null));
    }

    public function test_unreviewed_variances_can_be_filtered_and_counted(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '8');
        $other = $this->brush('KUAS-2');
        $this->store->run(fn () => StockOpnameItem::factory()->create(['stock_opname_id' => $opname->id, 'product_id' => $other->id, 'snapshot_quantity' => '10', 'counted_quantity' => '9']));
        $this->review($opname, $item, ['reason_id' => $this->reasonId('Hilang'), 'is_reviewed' => true]);

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]).'?filter=unreviewed')->assertInertia(fn (Assert $page) => $page
            ->where('summary.variance_count', 2)
            ->where('summary.reviewed_count', 1)
            ->has('items.data', 1)
            ->where('items.data.0.product.sku', 'KUAS-2'));
    }

    public function test_only_admins_may_save_a_review(): void
    {
        $product = $this->brush();
        [$opname, $item] = $this->opnameCounting($product, '8');

        $this->actingAs($this->storeUser())->put($this->url('review', ['stock_opname' => $opname->id]), ['rows' => [$this->row($item)]])->assertForbidden();
    }

    public function test_the_create_form_only_accepts_the_selectable_reasons(): void
    {
        $base = ['warehouse_id' => $this->mainWarehouse()->id, 'opname_date' => '2026-10-24', 'scope' => StockOpnameScope::Selected->value];

        $this->actingAs($this->admin)->post($this->url('store'), [...$base, 'reason_code' => StockOpnameReason::Damaged->value])->assertSessionHasErrors('reason_code');
        $this->actingAs($this->admin)->post($this->url('store'), [...$base, 'reason_code' => StockOpnameReason::Correction->value])->assertSessionHasErrors('reason_code');
        $this->actingAs($this->admin)->post($this->url('store'), [...$base, 'reason_code' => StockOpnameReason::Routine->value])->assertSessionHasNoErrors();
    }

    public function test_the_item_list_can_be_narrowed_to_dg_products(): void
    {
        $product = $this->brush();
        $twin = $this->store->run(fn (): Product => Product::factory()->create(['sku' => 'X-DG', 'name' => '(DG) X', 'is_damaged_goods' => true, 'damaged_from_product_id' => $product->id]));
        $this->store->run(fn () => ProductUnit::factory()->create(['product_id' => $twin->id, 'unit_code' => 'PCS']));
        $listUrl = route('tenant.products.index', ['tenant' => $this->store->code]);

        $this->actingAs($this->admin)->get($listUrl)->assertInertia(fn (Assert $page) => $page->has('products.data', 2)->has('conditions', 2));
        $this->actingAs($this->admin)->get($listUrl.'?condition=damaged')->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'X-DG')
            ->where('products.data.0.is_damaged_goods', true)
            ->where('filters.condition', 'damaged'));
        $this->actingAs($this->admin)->get($listUrl.'?condition=normal')->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'KUAS-1')
            ->where('products.data.0.is_damaged_goods', false));
    }
}
