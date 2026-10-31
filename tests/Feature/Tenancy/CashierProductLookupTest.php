<?php

namespace Tests\Feature\Tenancy;

use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

/**
 * The item lookup of the cashier screen: what a typed or scanned term finds, with its units, prices and the stock
 * of the warehouse of the cashier's own open shift.
 */
class CashierProductLookupTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_cashier_lookup';

    private Store $store;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_cashier_lookup']);
        $this->cashier = $this->storeUser();
        $this->openShiftFor($this->cashier);
    }

    private function lookup(string $term, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->cashier)->getJson(route('tenant.cashier.products', ['tenant' => $this->store->code, 'q' => $term]));
    }

    public function test_a_typed_term_finds_the_item_with_its_units_prices_and_stock(): void
    {
        $this->sellableProduct('Kuas Cat', '40');

        $this->lookup('kuas')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kuas Cat')
            ->assertJsonPath('data.0.base_unit', 'PCS')
            ->assertJsonPath('data.0.stock', '40.000')
            ->assertJsonPath('data.0.matched_unit_id', null)
            ->assertJsonPath('data.0.units.0.code', 'PCS')
            ->assertJsonPath('data.0.units.0.price', '10000.00')
            ->assertJsonPath('data.0.units.1.code', 'DUS')
            ->assertJsonPath('data.0.units.1.conversion_factor', '12.000');
    }

    public function test_a_blank_term_finds_nothing(): void
    {
        $this->sellableProduct('Kuas');

        $this->lookup('   ')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_a_scanned_barcode_finds_that_item_alone_and_tells_which_unit_was_scanned(): void
    {
        $brush = $this->sellableProduct('Kuas');
        $this->sellableProduct('Kuas Besar');
        $dozen = $this->unitOf($brush, 'DUS');
        $this->store->run(fn () => $dozen->update(['barcode' => '8991234567890']));

        $this->lookup('8991234567890')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $brush->id)
            ->assertJsonPath('data.0.matched_unit_id', $dozen->id);
    }

    public function test_inactive_items_and_units_not_for_sale_are_left_out(): void
    {
        $this->sellableProduct('Kuas Lama', '10', ['is_active' => false]);
        $brush = $this->sellableProduct('Kuas Baru');
        $this->store->run(fn () => $this->unitOf($brush, 'DUS')->update(['is_sellable' => false]));

        $this->lookup('kuas')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kuas Baru')
            ->assertJsonCount(1, 'data.0.units');
    }

    public function test_an_item_with_no_unit_for_sale_is_left_out(): void
    {
        $brush = $this->sellableProduct('Kuas');
        $this->store->run(fn () => $brush->productUnits()->update(['is_sellable' => false]));

        $this->lookup('kuas')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_stock_shown_is_that_of_the_warehouse_of_the_open_shift(): void
    {
        $brush = $this->sellableProduct('Kuas', '40');
        $this->store->run(function () use ($brush): void {
            $otherWarehouse = Warehouse::factory()->create();
            Stock::query()->updateOrInsert(['product_id' => $brush->id, 'warehouse_id' => $otherWarehouse->id], ['quantity' => 500]);
        });

        $this->lookup('kuas')->assertOk()->assertJsonPath('data.0.stock', '40.000');
    }

    public function test_an_item_without_a_stock_row_shows_zero_stock(): void
    {
        $brush = $this->sellableProduct('Kuas', '5');
        $this->store->run(fn () => Stock::query()->where('product_id', $brush->id)->delete());

        $this->lookup('kuas')->assertOk()->assertJsonPath('data.0.stock', '0.000');
    }

    public function test_the_lookup_is_refused_with_the_standard_error_body_without_an_open_shift(): void
    {
        $this->sellableProduct('Kuas');

        $this->lookup('kuas', as: $this->storeUser())
            ->assertStatus(409)
            ->assertJsonStructure(['code', 'message', 'more_info'])
            ->assertJsonPath('message', 'Buka shift dulu sebelum mencari barang.');
    }
}
