<?php

namespace Tests\Feature\Tenancy;

use App\Enums\SellingChangeMethod;
use App\Enums\UserRole;
use App\Exceptions\BulkPriceException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use App\Models\User;
use App\Services\BulkPriceService;
use App\Services\StoreSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use OwenIt\Auditing\Models\Audit;
use RuntimeException;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class BulkPriceTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_bulk_prices';

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_bulk_prices', 'receipt_prefix' => 'TBP']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
    }

    /**
     * A store starts with no rounding. A test that wants one sets it before its first request: the controller keeps the
     * settings it read first.
     */
    private function setSellingPriceStep(int $step): void
    {
        $this->store->run(fn () => app(StoreSettingService::class)->update(['selling_price_step' => $step]));
    }

    private function url(string $route): string
    {
        return route("tenant.bulk-prices.{$route}", ['tenant' => $this->store->code]);
    }

    /**
     * Kuas: PCS (modal 6.000, jual 10.000) and DUS of 12 (modal 70.000, jual 110.000), unless made otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function item(string $name = 'Kuas', array $attributes = []): Product
    {
        return $this->sellableProduct($name, '100', $attributes);
    }

    /**
     * @param  array<string, mixed>  $change
     * @return array<string, mixed>
     */
    private function payload(Product|array $products, array $change): array
    {
        $products = $products instanceof Product ? [$products] : $products;

        return ['product_ids' => array_map(fn (Product $product): int => $product->id, $products), ...$change];
    }

    private function preview(array $payload): TestResponse
    {
        return $this->actingAs($this->admin)->postJson($this->url('preview'), $payload);
    }

    /**
     * Applies what the admin would have previewed just before, unless the test brings a fingerprint of its own.
     */
    private function apply(array $payload): TestResponse
    {
        $payload['preview_fingerprint'] ??= $this->preview($payload)->json('data.fingerprint') ?? str_repeat('0', 64);

        return $this->actingAs($this->admin)->post($this->url('apply'), $payload);
    }

    /**
     * @return list<int>
     */
    private function matchingIdsOf(string $listUrl): array
    {
        $ids = [];
        $this->actingAs($this->admin)->get($listUrl)->assertInertia(function (Assert $page) use (&$ids): void {
            $page->reloadOnly('matching_ids', function (Assert $reload) use (&$ids): void {
                $ids = $reload->toArray()['props']['matching_ids'];
            });
        });

        return $ids;
    }

    /**
     * @return array{0: string, 1: string} the modal and the selling price of a unit as stored
     */
    private function pricesOf(Product $product, string $unitCode): array
    {
        $unit = $this->unitOf($product, $unitCode);

        return [$unit->cost_price, $unit->selling_price];
    }

    public function test_only_admins_may_change_prices_in_bulk(): void
    {
        $cashier = $this->storeUser();
        $product = $this->item();
        $payload = $this->payload($product, ['selling_method' => 'percent', 'selling_value' => 10]);

        $this->actingAs($cashier)->postJson($this->url('preview'), $payload)->assertForbidden();
        $this->actingAs($cashier)->post($this->url('apply'), $payload)->assertForbidden();
        $this->assertSame(['6000.00', '10000.00'], $this->pricesOf($product, 'PCS'));
    }

    public function test_the_item_list_offers_the_picking_and_what_the_bulk_window_needs(): void
    {
        [$paint, $tools] = $this->store->run(fn (): array => [Category::query()->create(['name' => 'Cat']), Category::query()->create(['name' => 'Alat'])]);
        $dulux = $this->store->run(fn (): Brand => Brand::query()->create(['name' => 'Dulux']));
        $wall = $this->item('Cat Tembok', ['category_id' => $paint->id, 'brand_id' => $dulux->id]);
        $this->item('Cat Kayu', ['category_id' => $paint->id]);
        $brush = $this->item('Kuas', ['category_id' => $tools->id]);
        $listUrl = route('tenant.products.index', ['tenant' => $this->store->code]);

        $this->actingAs($this->admin)->get($listUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Products/Index')
            ->has('products.data', 3)
            ->missing('matching_ids')
            ->reloadOnly('matching_ids', fn (Assert $reload) => $reload->has('matching_ids', 3))
            ->has('brands', 1)
            ->where('bulk_price.max_products', 500)
            ->where('bulk_price.selling_price_step', 0)
            ->where('bulk_price.cost_methods.0', ['value' => 'percent', 'label' => 'Persen (naik atau turun)'])
            ->where('bulk_price.selling_methods.2', ['value' => 'set', 'label' => 'Setarakan ke harga tertentu', 'takes_value' => true, 'is_derived_from_rule' => false])
            ->where('bulk_price.selling_methods.0.is_derived_from_rule', true)
            ->where('bulk_price.selling_methods.4', ['value' => 'keep_margin', 'label' => 'Pertahankan margin sekarang', 'takes_value' => false, 'is_derived_from_rule' => true])
            ->where('bulk_price.cost_methods.2', ['value' => 'set', 'label' => 'Setarakan ke harga tertentu'])
            ->where('bulk_price.urls.units', $this->url('units'))
            ->where('bulk_price.urls.preview', $this->url('preview'))
            ->where('bulk_price.urls.apply', $this->url('apply')));

        $this->actingAs($this->admin)->get($listUrl.'?category='.$paint->id.'&brand='.$dulux->id)->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->reloadOnly('matching_ids', fn (Assert $reload) => $reload->where('matching_ids', [$wall->id]))
            ->where('filters.brand', $dulux->id));

        $this->actingAs($this->admin)->get($listUrl.'?category='.$tools->id)->assertInertia(fn (Assert $page) => $page->reloadOnly('matching_ids', fn (Assert $reload) => $reload->where('matching_ids', [$brush->id])));
    }

    public function test_select_all_follows_the_condition_and_status_filters_too(): void
    {
        $normal = $this->item('Normal');
        $inactive = $this->item('Mati', ['is_active' => false]);
        $dg = $this->item('Rusak', ['is_damaged_goods' => true]);
        $listUrl = route('tenant.products.index', ['tenant' => $this->store->code]);

        $this->assertSame([$inactive->id], $this->matchingIdsOf($listUrl.'?status=inactive'));
        $this->assertSame([$dg->id], $this->matchingIdsOf($listUrl.'?condition=damaged'));
        $this->assertSame([$normal->id], $this->matchingIdsOf($listUrl.'?condition=normal&status=active'));
    }

    public function test_the_bulk_price_change_has_no_page_of_its_own(): void
    {
        $this->assertFalse(Route::has('tenant.bulk-prices.index'));
        $this->assertTrue(Route::has('tenant.bulk-prices.preview') && Route::has('tenant.bulk-prices.apply'));
    }

    public function test_a_percent_change_of_both_prices_is_previewed_per_unit_without_saving(): void
    {
        $product = $this->item();

        $this->preview($this->payload($product, ['cost_method' => 'percent', 'cost_value' => 10, 'selling_method' => 'percent', 'selling_value' => 10]))
            ->assertOk()
            ->assertJsonPath('data.totals', ['products_selected' => 1, 'products_changed' => 1, 'units_changed' => 2, 'units_skipped' => 0, 'units_below_cost' => 0])
            ->assertJsonPath('data.products.0.name', 'Kuas')
            ->assertJsonPath('data.products.0.units.0.old_cost', '6000.00')
            ->assertJsonPath('data.products.0.units.0.new_cost', '6600.00')
            ->assertJsonPath('data.products.0.units.0.new_price', '11000.00')
            ->assertJsonPath('data.products.0.units.1.new_cost', '77000.00')
            ->assertJsonPath('data.products.0.units.1.new_price', '121000.00');

        $this->assertSame(['6000.00', '10000.00'], $this->pricesOf($product, 'PCS'));
    }

    public function test_a_price_can_be_set_to_the_same_amount_for_every_chosen_unit(): void
    {
        $product = $this->item();

        $this->preview($this->payload($product, ['cost_method' => 'set', 'cost_value' => 4000, 'selling_method' => 'set', 'selling_value' => 55000]))
            ->assertOk()
            ->assertJsonPath('data.totals', ['products_selected' => 1, 'products_changed' => 1, 'units_changed' => 2, 'units_skipped' => 0, 'units_below_cost' => 0])
            ->assertJsonPath('data.products.0.units.0.new_cost', '4000.00')
            ->assertJsonPath('data.products.0.units.0.new_price', '55000.00')
            ->assertJsonPath('data.products.0.units.1.new_cost', '4000.00')
            ->assertJsonPath('data.products.0.units.1.new_price', '55000.00');
    }

    public function test_setting_a_price_can_be_limited_to_one_unit_and_keeps_the_products_modal_in_step(): void
    {
        $product = $this->item();

        $this->apply($this->payload($product, ['unit_code' => 'PCS', 'cost_method' => 'set', 'cost_value' => '5500.50', 'selling_method' => 'set', 'selling_value' => 9000]))->assertSessionHasNoErrors();

        $this->assertSame(['5500.50', '9000.00'], $this->pricesOf($product, 'PCS'));
        $this->assertSame(['70000.00', '110000.00'], $this->pricesOf($product, 'DUS'));
        $this->assertSame('5500.50', $this->store->run(fn () => Product::query()->findOrFail($product->id)->cost_price));
    }

    public function test_a_margin_can_follow_a_price_that_was_set_in_the_same_run(): void
    {
        $product = $this->item();

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'cost_method' => 'set', 'cost_value' => 8000, 'selling_method' => 'margin', 'selling_value' => 20]))
            ->assertJsonPath('data.products.0.units.0.new_price', '10000.00');
    }

    public function test_a_price_to_set_cannot_be_negative(): void
    {
        $ids = [$this->item()->id];

        $this->preview(['product_ids' => $ids, 'cost_method' => 'set', 'cost_value' => -1])->assertJsonValidationErrors(['cost_value' => 'Harga beli tidak boleh negatif.']);
        $this->preview(['product_ids' => $ids, 'selling_method' => 'set', 'selling_value' => -1])->assertJsonValidationErrors(['selling_value' => 'Harga jual tidak boleh negatif.']);
        $this->preview(['product_ids' => $ids, 'selling_method' => 'set', 'selling_value' => 0])->assertOk();
        $this->preview(['product_ids' => $ids, 'selling_method' => 'set'])->assertJsonValidationErrors(['selling_value' => 'Isi angka perubahan harga jual.']);
    }

    public function test_a_nominal_change_can_be_limited_to_one_unit(): void
    {
        $product = $this->item();

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'selling_method' => 'nominal', 'selling_value' => 500]))
            ->assertOk()
            ->assertJsonPath('data.totals.units_changed', 1)
            ->assertJsonPath('data.products.0.units.0.new_price', '10500.00')
            ->assertJsonPath('data.products.0.units.1.is_changed', false)
            ->assertJsonPath('data.products.0.units.1.new_price', '110000.00');
    }

    public function test_the_preview_counts_the_units_whose_price_would_end_under_their_modal(): void
    {
        $product = $this->item();

        $this->preview($this->payload($product, ['cost_method' => 'percent', 'cost_value' => 100]))
            ->assertOk()
            ->assertJsonPath('data.totals.units_below_cost', 2)
            ->assertJsonPath('data.products.0.units.0.is_below_cost', true)
            ->assertJsonPath('data.products.0.units.1.is_below_cost', true);

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'cost_method' => 'set', 'cost_value' => 12000]))
            ->assertJsonPath('data.totals.units_below_cost', 1)
            ->assertJsonPath('data.products.0.units.0.is_below_cost', true)
            ->assertJsonPath('data.products.0.units.1.is_below_cost', false);

        $this->preview($this->payload($product, ['cost_method' => 'percent', 'cost_value' => 100, 'selling_method' => 'keep_margin']))
            ->assertJsonPath('data.totals.units_below_cost', 0);
    }

    public function test_a_price_under_the_modal_is_counted_but_does_not_stop_the_change_from_being_applied(): void
    {
        $product = $this->item();

        $this->apply($this->payload($product, ['cost_method' => 'percent', 'cost_value' => 100]))->assertSessionHas('success');

        $this->assertSame(['12000.00', '10000.00'], $this->pricesOf($product, 'PCS'));
    }

    public function test_a_margin_sets_the_price_as_a_share_of_the_price_and_never_as_markup(): void
    {
        $product = $this->item();

        $this->preview($this->payload($product, ['selling_method' => 'margin', 'selling_value' => 20]))
            ->assertOk()
            ->assertJsonPath('data.products.0.units.0.new_price', '7500.00')
            ->assertJsonPath('data.products.0.units.1.new_price', '87500.00');
    }

    public function test_a_margin_uses_the_modal_as_it_will_be_after_the_same_run(): void
    {
        $product = $this->item();

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'cost_method' => 'nominal', 'cost_value' => 2000, 'selling_method' => 'margin', 'selling_value' => 25]))
            ->assertOk()
            ->assertJsonPath('data.products.0.units.0.new_cost', '8000.00')
            ->assertJsonPath('data.products.0.units.0.new_price', '10666.67');
    }

    public function test_a_price_from_a_percent_is_rounded_up_to_the_store_step_and_the_preview_tells_the_real_percent(): void
    {
        $this->setSellingPriceStep(1000);
        $product = $this->item();

        $this->preview($this->payload($product, ['selling_method' => 'percent', 'selling_value' => 2]))
            ->assertOk()
            ->assertJsonPath('data.products.0.units.0.new_price', '11000.00')
            ->assertJsonPath('data.products.0.units.0.is_price_rounded', true)
            ->assertJsonPath('data.products.0.units.0.price_change_percent', '10.00')
            ->assertJsonPath('data.products.0.units.1.new_price', '113000.00')
            ->assertJsonPath('data.products.0.units.1.price_change_percent', '2.73');

        $this->apply($this->payload($product, ['selling_method' => 'percent', 'selling_value' => 2]))->assertSessionHas('success');

        $this->assertSame(['6000.00', '11000.00'], $this->pricesOf($product, 'PCS'));
    }

    public function test_a_price_from_a_margin_or_a_kept_margin_is_rounded_up_to_the_store_step(): void
    {
        $this->setSellingPriceStep(1000);
        $product = $this->item();

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'selling_method' => 'margin', 'selling_value' => 20]))
            ->assertJsonPath('data.products.0.units.0.new_price', '8000.00')
            ->assertJsonPath('data.products.0.units.0.price_change_percent', '-20.00');

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'cost_method' => 'percent', 'cost_value' => 5, 'selling_method' => 'keep_margin']))
            ->assertJsonPath('data.products.0.units.0.new_price', '11000.00')
            ->assertJsonPath('data.products.0.units.0.is_price_rounded', true);
    }

    public function test_a_typed_rupiah_amount_and_a_price_the_rule_leaves_alone_are_not_rounded(): void
    {
        $this->setSellingPriceStep(3000);
        $product = $this->item();

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'selling_method' => 'nominal', 'selling_value' => 250]))
            ->assertJsonPath('data.products.0.units.0.new_price', '10250.00')
            ->assertJsonPath('data.products.0.units.0.is_price_rounded', false)
            ->assertJsonPath('data.products.0.units.0.price_change_percent', null);

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'selling_method' => 'set', 'selling_value' => 10250]))
            ->assertJsonPath('data.products.0.units.0.new_price', '10250.00');

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'selling_method' => 'percent', 'selling_value' => 0]))
            ->assertOk()
            ->assertJsonPath('data.totals.units_changed', 0);
    }

    public function test_a_preview_is_refused_once_the_store_changes_its_selling_price_step(): void
    {
        $product = $this->item();
        $change = ['unit_code' => null, 'cost_method' => null, 'cost_value' => null, 'selling_method' => SellingChangeMethod::Percent, 'selling_value' => '2'];

        $this->store->run(function () use ($product, $change): void {
            $fingerprint = app(BulkPriceService::class)->preview([$product->id], $change)['fingerprint'];
            app(StoreSettingService::class)->update(['selling_price_step' => 1000]);

            $this->expectException(BulkPriceException::class);

            app(BulkPriceService::class)->apply([$product->id], $change, $fingerprint);
        });
    }

    public function test_keeping_the_margin_moves_the_price_with_the_new_modal(): void
    {
        $product = $this->item();

        $this->preview($this->payload($product, ['cost_method' => 'percent', 'cost_value' => 10, 'selling_method' => 'keep_margin']))
            ->assertOk()
            ->assertJsonPath('data.products.0.units.0.new_cost', '6600.00')
            ->assertJsonPath('data.products.0.units.0.new_price', '11000.00')
            ->assertJsonPath('data.products.0.units.1.new_price', '121000.00');
    }

    public function test_money_is_rounded_to_the_sen_and_not_further(): void
    {
        $product = $this->item();
        $this->store->run(fn () => $this->unitOf($product, 'PCS')->forceFill(['cost_price' => '1234.56'])->save());

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'cost_method' => 'percent', 'cost_value' => 10]))
            ->assertJsonPath('data.products.0.units.0.new_cost', '1358.02');
    }

    public function test_units_that_would_go_wrong_are_skipped_with_the_reason(): void
    {
        $cheap = $this->item('Murah');
        $free = $this->item('Gratis');
        $this->store->run(fn () => ProductUnit::query()->where('product_id', $free->id)->update(['cost_price' => 0]));

        $this->preview($this->payload($cheap, ['unit_code' => 'PCS', 'cost_method' => 'nominal', 'cost_value' => -7000]))
            ->assertJsonPath('data.totals', ['products_selected' => 1, 'products_changed' => 0, 'units_changed' => 0, 'units_skipped' => 1, 'units_below_cost' => 0])
            ->assertJsonPath('data.products.0.units.0.skip_reason', 'Modal akan jadi negatif.');

        $this->preview($this->payload($cheap, ['unit_code' => 'PCS', 'selling_method' => 'nominal', 'selling_value' => -20000]))
            ->assertJsonPath('data.products.0.units.0.skip_reason', 'Harga jual akan jadi negatif.');

        $this->preview($this->payload($free, ['selling_method' => 'margin', 'selling_value' => 30]))
            ->assertJsonPath('data.totals.units_skipped', 2)
            ->assertJsonPath('data.products.0.units.0.skip_reason', 'Modal 0, harga dari margin tidak bisa dihitung.');

        $this->preview($this->payload($free, ['cost_method' => 'percent', 'cost_value' => 10, 'selling_method' => 'keep_margin']))
            ->assertJsonPath('data.products.0.units.0.skip_reason', 'Modal atau harga jual sekarang 0, margin lama tidak bisa dihitung. Modal satuan ini juga tidak diubah.');
    }

    public function test_a_unit_that_is_not_sold_keeps_its_selling_price(): void
    {
        $product = $this->item();
        $this->store->run(fn () => $this->unitOf($product, 'DUS')->forceFill(['is_sellable' => false, 'selling_price' => 0])->save());

        $this->preview($this->payload($product, ['selling_method' => 'percent', 'selling_value' => 10]))
            ->assertJsonPath('data.totals.units_changed', 1)
            ->assertJsonPath('data.products.0.units.1.is_changed', false);
    }

    public function test_applying_saves_each_unit_the_base_modal_and_the_audit_trail(): void
    {
        $product = $this->item();
        $versionBefore = $this->store->run(fn () => Product::query()->whereKey($product->id)->value('version'));
        config(['audit.console' => true]);

        $this->apply($this->payload($product, ['cost_method' => 'percent', 'cost_value' => 10, 'selling_method' => 'percent', 'selling_value' => 10]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Harga 1 barang diubah (2 satuan).');

        $this->assertSame(['6600.00', '11000.00'], $this->pricesOf($product, 'PCS'));
        $this->assertSame(['77000.00', '121000.00'], $this->pricesOf($product, 'DUS'));
        $this->store->run(function () use ($product, $versionBefore): void {
            $fresh = Product::query()->findOrFail($product->id);
            $this->assertSame('6600.00', $fresh->cost_price);
            $this->assertSame($versionBefore + 1, $fresh->version);

            $audit = Audit::query()->where('auditable_type', ProductUnit::class)->where('auditable_id', $this->unitOf($product, 'PCS')->id)->where('event', 'updated')->latest('id')->firstOrFail();
            $this->assertEquals(['cost_price' => '6000.00', 'selling_price' => '10000.00'], $audit->old_values);
            $this->assertEquals(['cost_price' => '6600.00', 'selling_price' => '11000.00'], $audit->new_values);
        });
    }

    public function test_a_change_that_leaves_the_base_unit_out_keeps_the_products_modal(): void
    {
        $product = $this->item();

        $this->apply($this->payload($product, ['unit_code' => 'DUS', 'cost_method' => 'percent', 'cost_value' => 10]))->assertSessionHasNoErrors();

        $this->assertSame('77000.00', $this->pricesOf($product, 'DUS')[0]);
        $this->assertSame('6000.00', $this->store->run(fn () => Product::query()->findOrFail($product->id)->cost_price));
    }

    public function test_skipped_units_stay_as_they_were_and_are_reported(): void
    {
        $product = $this->item();
        $this->store->run(fn () => $this->unitOf($product, 'PCS')->forceFill(['cost_price' => 0])->save());

        $this->apply($this->payload($product, ['selling_method' => 'margin', 'selling_value' => 30]))
            ->assertSessionHas('success', 'Harga 1 barang diubah (1 satuan). 1 satuan dilewati.');

        $this->assertSame(['0.00', '10000.00'], $this->pricesOf($product, 'PCS'));
        $this->assertSame(['70000.00', '100000.00'], $this->pricesOf($product, 'DUS'));
    }

    public function test_a_nominal_change_may_cover_every_unit_and_the_preview_shows_what_each_one_becomes(): void
    {
        $product = $this->item();

        $this->preview($this->payload($product, ['selling_method' => 'nominal', 'selling_value' => 500]))
            ->assertOk()
            ->assertJsonPath('data.totals.units_changed', 2)
            ->assertJsonPath('data.products.0.units.0.new_price', '10500.00')
            ->assertJsonPath('data.products.0.units.1.new_price', '110500.00');

        $this->apply($this->payload($product, ['cost_method' => 'nominal', 'cost_value' => 500]))->assertSessionHas('success');

        $this->assertSame('6500.00', $this->pricesOf($product, 'PCS')[0]);
        $this->assertSame('70500.00', $this->pricesOf($product, 'DUS')[0]);
    }

    public function test_the_units_the_picked_items_come_in_are_listed_with_how_many_items_have_each(): void
    {
        $brush = $this->item('Kuas');
        $paint = $this->item('Cat');
        $wire = $this->wireProduct();

        $this->actingAs($this->admin)->postJson($this->url('units'), ['product_ids' => [$brush->id, $paint->id, $wire->id]])
            ->assertOk()
            ->assertExactJson(['data' => [
                ['code' => 'DUS', 'products_count' => 2],
                ['code' => 'PCS', 'products_count' => 2],
                ['code' => 'MTR', 'products_count' => 1],
                ['code' => 'ROL', 'products_count' => 1],
            ]]);

        $this->actingAs($this->admin)->postJson($this->url('units'), ['product_ids' => [$wire->id]])
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_listing_the_units_needs_picked_items_and_an_admin(): void
    {
        $brush = $this->item('Kuas');

        $this->actingAs($this->admin)->postJson($this->url('units'), ['product_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('product_ids');
        $this->actingAs($this->storeUser())->postJson($this->url('units'), ['product_ids' => [$brush->id]])->assertForbidden();
    }

    public function test_applying_needs_the_preview_it_was_looked_at_from(): void
    {
        $product = $this->item();
        $change = ['selling_method' => 'percent', 'selling_value' => 10];

        $this->actingAs($this->admin)->post($this->url('apply'), $this->payload($product, $change))->assertSessionHasErrors(['preview_fingerprint' => 'Lihat pratinjaunya dulu sebelum menerapkan.']);
        $this->actingAs($this->admin)->post($this->url('apply'), $this->payload($product, [...$change, 'preview_fingerprint' => 'pendek']))->assertSessionHasErrors('preview_fingerprint');
        $this->assertSame('10000.00', $this->pricesOf($product, 'PCS')[1]);
    }

    public function test_prices_that_moved_since_the_preview_stop_the_whole_run(): void
    {
        $product = $this->item();
        $other = $this->item('Cat');
        $payload = $this->payload([$product, $other], ['selling_method' => 'percent', 'selling_value' => 10]);
        $fingerprint = $this->preview($payload)->json('data.fingerprint');

        $this->store->run(fn () => $this->unitOf($other, 'PCS')->forceFill(['selling_price' => 12000])->save());

        $this->apply([...$payload, 'preview_fingerprint' => $fingerprint])->assertSessionHas('error', 'Harga barang berubah sejak pratinjau dibuat, jadi belum ada yang disimpan. Pratinjau dihitung ulang: periksa lagi, lalu terapkan.');

        $this->assertSame('10000.00', $this->pricesOf($product, 'PCS')[1]);
        $this->assertSame('12000.00', $this->pricesOf($other, 'PCS')[1]);
        $this->assertNotSame($fingerprint, $this->preview($payload)->json('data.fingerprint'));
    }

    public function test_the_preview_fingerprint_is_the_same_while_nothing_moves(): void
    {
        $payload = $this->payload($this->item(), ['selling_method' => 'percent', 'selling_value' => 10]);

        $this->assertSame($this->preview($payload)->json('data.fingerprint'), $this->preview($payload)->json('data.fingerprint'));
    }

    public function test_a_result_past_what_a_price_column_holds_is_skipped_instead_of_failing_the_save(): void
    {
        $product = $this->item();

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'cost_method' => 'set', 'cost_value' => '999999999999', 'selling_method' => 'percent', 'selling_value' => 5]))
            ->assertJsonPath('data.products.0.units.0.is_changed', true);

        $this->store->run(fn () => $this->unitOf($product, 'PCS')->forceFill(['cost_price' => '9999999999999.00', 'selling_price' => '9999999999999.00'])->save());

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'cost_method' => 'nominal', 'cost_value' => 5]))
            ->assertJsonPath('data.products.0.units.0.skip_reason', 'Modal akan melebihi batas harga.');
        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'selling_method' => 'percent', 'selling_value' => 5]))
            ->assertJsonPath('data.products.0.units.0.skip_reason', 'Harga jual akan melebihi batas harga.');

        $this->apply($this->payload($product, ['unit_code' => 'PCS', 'selling_method' => 'percent', 'selling_value' => 5]))->assertSessionHas('success', 'Harga 0 barang diubah (0 satuan). 1 satuan dilewati.');
    }

    public function test_the_most_items_one_change_takes_is_allowed_but_not_one_more(): void
    {
        $this->preview(['product_ids' => range(1, 500), 'selling_method' => 'percent', 'selling_value' => 5])->assertStatus(409);
        $this->preview(['product_ids' => range(1, 501), 'selling_method' => 'percent', 'selling_value' => 5])->assertStatus(422);
    }

    public function test_the_preview_lists_the_first_hundred_changed_items_and_counts_all(): void
    {
        $products = array_map(fn (int $number): Product => $this->item("Barang {$number}"), range(1, 101));

        $this->preview($this->payload($products, ['selling_method' => 'percent', 'selling_value' => 10]))
            ->assertOk()
            ->assertJsonPath('data.is_truncated', true)
            ->assertJsonCount(100, 'data.products')
            ->assertJsonPath('data.totals', ['products_selected' => 101, 'products_changed' => 101, 'units_changed' => 202, 'units_skipped' => 0, 'units_below_cost' => 0]);

        $this->preview($this->payload(array_slice($products, 0, 100), ['selling_method' => 'percent', 'selling_value' => 10]))
            ->assertJsonPath('data.is_truncated', false)
            ->assertJsonCount(100, 'data.products');
    }

    public function test_midpoints_of_a_sen_round_up(): void
    {
        $product = $this->item();
        $this->store->run(fn () => $this->unitOf($product, 'PCS')->forceFill(['cost_price' => '1234.55'])->save());

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'cost_method' => 'percent', 'cost_value' => 10]))
            ->assertJsonPath('data.products.0.units.0.new_cost', '1358.01');
    }

    public function test_many_items_are_changed_with_a_kept_margin_and_the_unpriceable_ones_skipped(): void
    {
        $first = $this->item('Satu');
        $second = $this->item('Dua');
        $free = $this->item('Gratis');
        $this->store->run(fn () => ProductUnit::query()->where('product_id', $free->id)->update(['cost_price' => 0]));

        $this->apply($this->payload([$first, $second, $free], ['cost_method' => 'percent', 'cost_value' => 10, 'selling_method' => 'keep_margin']))
            ->assertSessionHas('success', 'Harga 2 barang diubah (4 satuan). 2 satuan dilewati.');

        foreach ([$first, $second] as $product) {
            $this->assertSame(['6600.00', '11000.00'], $this->pricesOf($product, 'PCS'));
            $this->assertSame(['77000.00', '121000.00'], $this->pricesOf($product, 'DUS'));
        }
        $this->assertSame(['0.00', '10000.00'], $this->pricesOf($free, 'PCS'));
    }

    public function test_a_save_that_fails_halfway_leaves_every_item_as_it_was(): void
    {
        $first = $this->item('Satu');
        $second = $this->item('Dua');
        $savedUnits = 0;
        ProductUnit::saving(function () use (&$savedUnits): void {
            if (++$savedUnits === 3) {
                throw new RuntimeException('Simpan gagal di tengah.');
            }
        });
        $payload = $this->payload([$first, $second], ['selling_method' => 'percent', 'selling_value' => 10]);
        $payload['preview_fingerprint'] = $this->preview($payload)->json('data.fingerprint');

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->admin)->post($this->url('apply'), $payload);
            $this->fail('The failing save should have stopped the run.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simpan gagal di tengah.', $exception->getMessage());
        }

        foreach ([$first, $second] as $product) {
            $this->assertSame('10000.00', $this->pricesOf($product, 'PCS')[1]);
            $this->assertSame('110000.00', $this->pricesOf($product, 'DUS')[1]);
        }
    }

    public function test_changing_only_a_unit_beside_the_base_moves_the_counter_without_an_empty_audit_entry(): void
    {
        $product = $this->item();
        config(['audit.console' => true]);

        $this->apply($this->payload($product, ['unit_code' => 'DUS', 'selling_method' => 'percent', 'selling_value' => 10]))->assertSessionHasNoErrors();

        $this->store->run(function () use ($product): void {
            $this->assertSame(2, Product::query()->findOrFail($product->id)->version);
            $this->assertSame(0, Audit::query()->where('auditable_type', Product::class)->where('auditable_id', $product->id)->where('event', 'updated')->count());
        });
    }

    public function test_items_nobody_changes_keep_their_save_counter(): void
    {
        $product = $this->item();
        $versionBefore = $this->store->run(fn () => Product::query()->whereKey($product->id)->value('version'));

        $this->apply($this->payload($product, ['cost_method' => 'percent', 'cost_value' => 0]))->assertSessionHasNoErrors();

        $this->assertSame($versionBefore, $this->store->run(fn () => Product::query()->whereKey($product->id)->value('version')));
    }

    public function test_an_item_that_is_gone_stops_the_whole_run(): void
    {
        $product = $this->item();
        $payload = $this->payload($product, ['selling_method' => 'percent', 'selling_value' => 10]);
        $payload['product_ids'][] = 999999;

        $this->apply($payload)->assertSessionHas('error', 'Sebagian barang yang dipilih sudah tidak ada. Muat ulang halaman, lalu pilih lagi.');
        $this->preview($payload)->assertStatus(409);

        $this->assertSame('10000.00', $this->pricesOf($product, 'PCS')[1]);
    }

    public function test_the_change_is_validated(): void
    {
        $product = $this->item();
        $ids = [$product->id];

        $this->preview(['product_ids' => [], 'selling_method' => 'percent', 'selling_value' => 5])->assertJsonValidationErrors(['product_ids' => 'Pilih barang yang mau diubah.']);
        $this->preview(['product_ids' => range(1, 501), 'selling_method' => 'percent', 'selling_value' => 5])->assertJsonValidationErrors('product_ids');
        $this->preview(['product_ids' => $ids])->assertJsonValidationErrors(['cost_method' => 'Pilih harga yang mau diubah: harga beli, harga jual, atau keduanya.']);
        $this->preview(['product_ids' => $ids, 'cost_method' => 'percent'])->assertJsonValidationErrors(['cost_value' => 'Isi angka perubahan harga beli.']);
        $this->preview(['product_ids' => $ids, 'selling_method' => 'nominal'])->assertJsonValidationErrors(['selling_value' => 'Isi angka perubahan harga jual.']);
        $this->preview(['product_ids' => $ids, 'cost_method' => 'percent', 'cost_value' => -100])->assertJsonValidationErrors(['cost_value' => 'Penurunan harga beli harus kurang dari 100 persen.']);
        $this->preview(['product_ids' => $ids, 'selling_method' => 'percent', 'selling_value' => -150])->assertJsonValidationErrors('selling_value');
        $this->preview(['product_ids' => $ids, 'selling_method' => 'margin', 'selling_value' => 100])->assertJsonValidationErrors(['selling_value' => 'Margin harus kurang dari 100 persen.']);
        $this->preview(['product_ids' => $ids, 'selling_method' => 'margin', 'selling_value' => -1])->assertJsonValidationErrors(['selling_value' => 'Margin tidak boleh negatif.']);
        $this->preview(['product_ids' => $ids, 'selling_method' => 'keep_margin'])->assertJsonValidationErrors(['selling_method' => 'Mempertahankan margin hanya berarti bila harga beli ikut diubah.']);
        $this->preview(['product_ids' => $ids, 'selling_method' => 'nominal', 'selling_value' => 'abc'])->assertJsonValidationErrors('selling_value');
        $this->preview(['product_ids' => $ids, 'unit_code' => 'TIDAKADA', 'selling_method' => 'percent', 'selling_value' => 5])->assertJsonValidationErrors(['unit_code' => 'Satuan tidak dikenal.']);
        $this->preview(['product_ids' => $ids, 'selling_method' => 'bebas', 'selling_value' => 5])->assertJsonValidationErrors('selling_method');
    }

    public function test_a_modal_decrease_may_be_a_negative_nominal_and_a_margin_may_be_zero(): void
    {
        $product = $this->item();

        $this->preview($this->payload($product, ['unit_code' => 'PCS', 'cost_method' => 'nominal', 'cost_value' => '-500.50', 'selling_method' => 'margin', 'selling_value' => 0]))
            ->assertOk()
            ->assertJsonPath('data.products.0.units.0.new_cost', '5499.50')
            ->assertJsonPath('data.products.0.units.0.new_price', '5499.50');
    }
}
