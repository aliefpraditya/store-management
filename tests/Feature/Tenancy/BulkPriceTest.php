<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use OwenIt\Auditing\Models\Audit;
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

    private function apply(array $payload): TestResponse
    {
        return $this->actingAs($this->admin)->post($this->url('apply'), $payload);
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

        $this->actingAs($cashier)->get($this->url('index'))->assertForbidden();
        $this->actingAs($cashier)->postJson($this->url('preview'), $payload)->assertForbidden();
        $this->actingAs($cashier)->post($this->url('apply'), $payload)->assertForbidden();
        $this->assertSame(['6000.00', '10000.00'], $this->pricesOf($product, 'PCS'));
    }

    public function test_the_page_lists_the_items_and_narrows_them_by_category_and_brand(): void
    {
        [$paint, $tools] = $this->store->run(fn (): array => [Category::query()->create(['name' => 'Cat']), Category::query()->create(['name' => 'Alat'])]);
        $dulux = $this->store->run(fn (): Brand => Brand::query()->create(['name' => 'Dulux']));
        $wall = $this->item('Cat Tembok', ['category_id' => $paint->id, 'brand_id' => $dulux->id]);
        $this->item('Cat Kayu', ['category_id' => $paint->id]);
        $brush = $this->item('Kuas', ['category_id' => $tools->id]);

        $this->actingAs($this->admin)->get($this->url('index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Products/BulkPrice')
            ->has('products.data', 3)
            ->where('products.data.0.name', 'Cat Kayu')
            ->where('products.data.0.units.0', ['code' => 'PCS', 'cost_price' => '6000.00', 'selling_price' => '10000.00', 'is_sellable' => true])
            ->has('matching_ids', 3)
            ->where('max_products', 2000)
            ->where('urls.apply', $this->url('apply')));

        $this->actingAs($this->admin)->get($this->url('index').'?category='.$paint->id.'&brand='.$dulux->id)->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('matching_ids', [$wall->id])
            ->where('filters.brand', $dulux->id));

        $this->actingAs($this->admin)->get($this->url('index').'?category='.$tools->id)->assertInertia(fn (Assert $page) => $page->where('matching_ids', [$brush->id]));
    }

    public function test_a_percent_change_of_both_prices_is_previewed_per_unit_without_saving(): void
    {
        $product = $this->item();

        $this->preview($this->payload($product, ['cost_method' => 'percent', 'cost_value' => 10, 'selling_method' => 'percent', 'selling_value' => 10]))
            ->assertOk()
            ->assertJsonPath('data.totals', ['products_selected' => 1, 'products_changed' => 1, 'units_changed' => 2, 'units_skipped' => 0])
            ->assertJsonPath('data.products.0.name', 'Kuas')
            ->assertJsonPath('data.products.0.units.0.old_cost', '6000.00')
            ->assertJsonPath('data.products.0.units.0.new_cost', '6600.00')
            ->assertJsonPath('data.products.0.units.0.new_price', '11000.00')
            ->assertJsonPath('data.products.0.units.1.new_cost', '77000.00')
            ->assertJsonPath('data.products.0.units.1.new_price', '121000.00');

        $this->assertSame(['6000.00', '10000.00'], $this->pricesOf($product, 'PCS'));
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

        $this->preview($this->payload($product, ['cost_method' => 'nominal', 'cost_value' => 2000, 'selling_method' => 'margin', 'selling_value' => 25]))
            ->assertOk()
            ->assertJsonPath('data.products.0.units.0.new_cost', '8000.00')
            ->assertJsonPath('data.products.0.units.0.new_price', '10666.67');
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
            ->assertJsonPath('data.totals', ['products_selected' => 1, 'products_changed' => 0, 'units_changed' => 0, 'units_skipped' => 1])
            ->assertJsonPath('data.products.0.units.0.skip_reason', 'Modal akan jadi negatif.');

        $this->preview($this->payload($cheap, ['unit_code' => 'PCS', 'selling_method' => 'nominal', 'selling_value' => -20000]))
            ->assertJsonPath('data.products.0.units.0.skip_reason', 'Harga jual akan jadi negatif.');

        $this->preview($this->payload($free, ['selling_method' => 'margin', 'selling_value' => 30]))
            ->assertJsonPath('data.totals.units_skipped', 2)
            ->assertJsonPath('data.products.0.units.0.skip_reason', 'Modal 0, harga dari margin tidak bisa dihitung.');

        $this->preview($this->payload($free, ['cost_method' => 'percent', 'cost_value' => 10, 'selling_method' => 'keep_margin']))
            ->assertJsonPath('data.products.0.units.0.skip_reason', 'Modal atau harga jual sekarang 0, margin lama tidak bisa dihitung.');
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

        $this->apply($this->payload($product, ['cost_method' => 'nominal', 'cost_value' => -10000]))
            ->assertSessionHas('success', 'Harga 1 barang diubah (1 satuan). 1 satuan dilewati.');

        $this->assertSame('6000.00', $this->pricesOf($product, 'PCS')[0]);
        $this->assertSame('60000.00', $this->pricesOf($product, 'DUS')[0]);
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
        $this->preview(['product_ids' => range(1, 2001), 'selling_method' => 'percent', 'selling_value' => 5])->assertJsonValidationErrors('product_ids');
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
