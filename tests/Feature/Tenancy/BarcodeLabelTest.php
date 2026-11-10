<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Http\Requests\Tenant\BarcodeLabelSheetRequest;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\BarcodeLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class BarcodeLabelTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_barcode_labels';

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_barcode_labels', 'receipt_prefix' => 'TBL']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
    }

    private function url(string $route): string
    {
        return route("tenant.barcode-labels.{$route}", ['tenant' => $this->store->code]);
    }

    private function item(string $name, string $sku): Product
    {
        return $this->sellableProduct($name, '10', ['sku' => $sku]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function sheet(array $query, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->get($this->url('sheet').'?'.http_build_query($query));
    }

    public function test_the_picked_items_are_listed_with_their_sku(): void
    {
        $chair = $this->item('Kursi Ekspana', 'MU0496');
        $bad = $this->item('Aneh', 'MU_01');

        $this->actingAs($this->admin)->postJson($this->url('products'), ['product_ids' => [$chair->id, $bad->id]])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Aneh')
            ->assertJsonPath('data.0.is_printable', false)
            ->assertJsonPath('data.1.name', 'Kursi Ekspana')
            ->assertJsonPath('data.1.sku', 'MU0496')
            ->assertJsonPath('data.1.is_printable', true);
    }

    public function test_a_sku_with_characters_code_39_cannot_carry_is_not_printable(): void
    {
        $this->assertTrue(BarcodeLabelService::isPrintable('MU0496'));
        $this->assertTrue(BarcodeLabelService::isPrintable('mu-01.5 /+%$'));
        $this->assertFalse(BarcodeLabelService::isPrintable('MU_01'));
        $this->assertFalse(BarcodeLabelService::isPrintable('*MU*'));
        $this->assertFalse(BarcodeLabelService::isPrintable(''));
    }

    public function test_the_sheet_holds_each_item_with_the_copies_asked_for(): void
    {
        $chair = $this->item('Kursi Ekspana', 'MU0496');
        $brush = $this->item('Kuas', 'MU0001');

        $this->sheet(['labels' => [$chair->id => 3, $brush->id => 1], 'width' => 99, 'height' => 15, 'columns' => 3])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Print/BarcodeLabels')
                ->where('sheet.width_mm', 99)
                ->where('sheet.height_mm', 15)
                ->where('sheet.columns', 3)
                ->has('sheet.labels', 2)
                ->where('sheet.labels.0.name', 'Kuas')
                ->where('sheet.labels.0.sku', 'MU0001')
                ->where('sheet.labels.0.copies', 1)
                ->where('sheet.labels.1.name', 'Kursi Ekspana')
                ->where('sheet.labels.1.sku', 'MU0496')
                ->where('sheet.labels.1.copies', 3)
                ->missing('sheet.labels.0.selling_price'));
    }

    public function test_an_item_that_cannot_be_printed_is_left_off_the_sheet(): void
    {
        $chair = $this->item('Kursi Ekspana', 'MU0496');
        $bad = $this->item('Aneh', 'MU_01');

        $this->sheet(['labels' => [$chair->id => 1, $bad->id => 1], 'width' => 99, 'height' => 15])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('sheet.labels', 1)->where('sheet.columns', 1));
    }

    public function test_the_sheet_refuses_too_many_stickers_and_bad_sizes(): void
    {
        $chair = $this->item('Kursi Ekspana', 'MU0496');
        $brush = $this->item('Kuas', 'MU0001');
        $valid = ['width' => 99, 'height' => 15];

        $this->sheet(['labels' => [$chair->id => BarcodeLabelService::MAX_COPIES_PER_PRODUCT + 1], ...$valid])->assertSessionHasErrors('labels.'.$chair->id);
        $this->sheet(['labels' => [$chair->id => BarcodeLabelService::MAX_COPIES_PER_PRODUCT, $brush->id => 1], ...$valid])->assertSessionHasErrors('labels');
        $this->sheet(['labels' => [$chair->id => 0], ...$valid])->assertSessionHasErrors('labels.'.$chair->id);
        $this->sheet(['labels' => [$chair->id => 1], 'width' => 5, 'height' => 15])->assertSessionHasErrors('width');
        $this->sheet(['labels' => [$chair->id => 1], 'width' => 99, 'height' => 500])->assertSessionHasErrors('height');
        $this->sheet(['labels' => [$chair->id => 1], ...$valid, 'columns' => 0])->assertSessionHasErrors('columns');
        $this->sheet(['labels' => [$chair->id => 1], ...$valid, 'columns' => BarcodeLabelSheetRequest::MAX_COLUMNS + 1])->assertSessionHasErrors('columns');
        $this->sheet($valid)->assertSessionHasErrors('labels');
    }

    public function test_picking_items_requires_at_least_one_and_a_sane_number(): void
    {
        $this->actingAs($this->admin)->postJson($this->url('products'), ['product_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('product_ids');
        $this->actingAs($this->admin)->postJson($this->url('products'), ['product_ids' => range(1, BarcodeLabelService::MAX_PRODUCTS + 1)])->assertUnprocessable()->assertJsonValidationErrors('product_ids');
    }

    public function test_only_admins_may_print_barcodes(): void
    {
        $chair = $this->item('Kursi Ekspana', 'MU0496');
        $cashier = $this->storeUser();

        $this->actingAs($cashier)->postJson($this->url('products'), ['product_ids' => [$chair->id]])->assertForbidden();
        $this->sheet(['labels' => [$chair->id => 1], 'width' => 99, 'height' => 15], $cashier)->assertForbidden();
    }

    public function test_the_item_list_carries_what_the_barcode_window_needs(): void
    {
        $this->actingAs($this->admin)
            ->get(route('tenant.products.index', ['tenant' => $this->store->code]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('barcode_label.urls.products', $this->url('products'))
                ->where('barcode_label.urls.sheet', $this->url('sheet'))
                ->where('barcode_label.limits.max_labels', BarcodeLabelService::MAX_LABELS)
                ->where('barcode_label.limits.max_columns', BarcodeLabelSheetRequest::MAX_COLUMNS));
    }

    public function test_an_item_unit_has_no_barcode_of_its_own(): void
    {
        $this->assertFalse($this->store->run(fn (): bool => Schema::hasColumn('product_units', 'barcode')));
    }
}
