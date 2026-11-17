<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Http\Requests\Tenant\BarcodeLabelSheetRequest;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use App\Models\User;
use App\Services\PriceLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class PriceLabelTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_price_labels';

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_price_labels', 'receipt_prefix' => 'TPL']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
    }

    private function url(string $route): string
    {
        return route("tenant.price-labels.{$route}", ['tenant' => $this->store->code]);
    }

    private function unitId(Product $product, string $unitCode): int
    {
        return $this->store->run(fn (): int => ProductUnit::query()->where('product_id', $product->id)->where('unit_code', $unitCode)->value('id'));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function sheet(array $query, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->get($this->url('sheet').'?'.http_build_query($query));
    }

    public function test_every_sellable_unit_of_the_picked_items_is_listed_with_its_price_and_the_base_unit_flagged(): void
    {
        $brush = $this->sellableProduct('Kuas');
        $this->sellableProduct('Cat');

        $this->actingAs($this->admin)->postJson($this->url('units'), ['product_ids' => [$brush->id]])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.product_id', $brush->id)
            ->assertJsonPath('data.0.name', 'Kuas')
            ->assertJsonPath('data.0.unit', 'PCS')
            ->assertJsonPath('data.0.price', '10000.00')
            ->assertJsonPath('data.0.is_base', true)
            ->assertJsonPath('data.1.unit', 'DUS')
            ->assertJsonPath('data.1.price', '110000.00')
            ->assertJsonPath('data.1.is_base', false);
    }

    public function test_a_unit_that_is_not_sold_is_not_offered(): void
    {
        $brush = $this->sellableProduct('Kuas');
        $this->store->run(fn () => ProductUnit::query()->whereKey($this->unitId($brush, 'DUS'))->update(['is_sellable' => false]));

        $this->actingAs($this->admin)->postJson($this->url('units'), ['product_ids' => [$brush->id]])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.unit', 'PCS');
    }

    public function test_the_sheet_holds_each_unit_with_the_copies_asked_for_and_the_day_printed(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(10, 0));
        $brush = $this->sellableProduct('Kuas');

        $this->sheet(['labels' => [$this->unitId($brush, 'PCS') => 3, $this->unitId($brush, 'DUS') => 1], 'width' => 99, 'height' => 15, 'columns' => 3])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Print/PriceLabels')
                ->where('sheet.printed_on', '2026-11-17')
                ->where('sheet.width_mm', 99)
                ->where('sheet.height_mm', 15)
                ->where('sheet.columns', 3)
                ->has('sheet.labels', 2)
                ->where('sheet.labels.0.name', 'Kuas')
                ->where('sheet.labels.0.unit', 'PCS')
                ->where('sheet.labels.0.price', '10000.00')
                ->where('sheet.labels.0.copies', 3)
                ->where('sheet.labels.1.unit', 'DUS')
                ->where('sheet.labels.1.price', '110000.00')
                ->where('sheet.labels.1.copies', 1)
                ->missing('sheet.labels.0.cost_price'));
    }

    public function test_the_sheet_leaves_out_a_unit_that_is_not_sold(): void
    {
        $brush = $this->sellableProduct('Kuas');
        $dusId = $this->unitId($brush, 'DUS');
        $this->store->run(fn () => ProductUnit::query()->whereKey($dusId)->update(['is_sellable' => false]));

        $this->sheet(['labels' => [$this->unitId($brush, 'PCS') => 1, $dusId => 1], 'width' => 99, 'height' => 15])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('sheet.labels', 1)->where('sheet.columns', 1));
    }

    public function test_the_sheet_refuses_too_many_labels_and_bad_sizes(): void
    {
        $brush = $this->sellableProduct('Kuas');
        $pcsId = $this->unitId($brush, 'PCS');
        $dusId = $this->unitId($brush, 'DUS');
        $valid = ['width' => 99, 'height' => 15];

        $this->sheet(['labels' => [$pcsId => PriceLabelService::MAX_COPIES_PER_UNIT + 1], ...$valid])->assertSessionHasErrors('labels.'.$pcsId);
        $this->sheet(['labels' => [$pcsId => PriceLabelService::MAX_COPIES_PER_UNIT, $dusId => 1], ...$valid])->assertSessionHasErrors('labels');
        $this->sheet(['labels' => [$pcsId => 0], ...$valid])->assertSessionHasErrors('labels.'.$pcsId);
        $this->sheet(['labels' => [$pcsId => 1], 'width' => 5, 'height' => 15])->assertSessionHasErrors('width');
        $this->sheet(['labels' => [$pcsId => 1], 'width' => 99, 'height' => 500])->assertSessionHasErrors('height');
        $this->sheet(['labels' => [$pcsId => 1], ...$valid, 'columns' => 0])->assertSessionHasErrors('columns');
        $this->sheet(['labels' => [$pcsId => 1], ...$valid, 'columns' => BarcodeLabelSheetRequest::MAX_COLUMNS + 1])->assertSessionHasErrors('columns');
        $this->sheet($valid)->assertSessionHasErrors('labels');
    }

    public function test_picking_items_requires_at_least_one_and_a_sane_number(): void
    {
        $this->actingAs($this->admin)->postJson($this->url('units'), ['product_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('product_ids');
        $this->actingAs($this->admin)->postJson($this->url('units'), ['product_ids' => range(1, PriceLabelService::MAX_PRODUCTS + 1)])->assertUnprocessable()->assertJsonValidationErrors('product_ids');
    }

    public function test_only_admins_may_print_price_labels(): void
    {
        $brush = $this->sellableProduct('Kuas');
        $cashier = $this->storeUser();

        $this->actingAs($cashier)->postJson($this->url('units'), ['product_ids' => [$brush->id]])->assertForbidden();
        $this->sheet(['labels' => [$this->unitId($brush, 'PCS') => 1], 'width' => 99, 'height' => 15], $cashier)->assertForbidden();
    }

    public function test_the_item_list_carries_what_the_price_label_window_needs(): void
    {
        $this->actingAs($this->admin)
            ->get(route('tenant.products.index', ['tenant' => $this->store->code]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('price_label.urls.units', $this->url('units'))
                ->where('price_label.urls.sheet', $this->url('sheet'))
                ->where('price_label.limits.max_labels', PriceLabelService::MAX_LABELS)
                ->where('price_label.limits.max_copies_per_unit', PriceLabelService::MAX_COPIES_PER_UNIT)
                ->where('price_label.limits.max_columns', BarcodeLabelSheetRequest::MAX_COLUMNS));
    }
}
