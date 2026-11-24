<?php

namespace Tests\Feature\Tenancy;

use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class StockReportTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_stock_report';

    private Store $store;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_stock_report', 'receipt_prefix' => 'TSK', 'name' => 'Toko Uji']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
    }

    /**
     * @param  array<string, string|int|float>  $query
     */
    private function url(array $query = []): string
    {
        return route('tenant.reports.stock.positions', ['tenant' => $this->store->code, ...$query]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, string $costPrice = '1000', array $attributes = []): Product
    {
        return $this->store->run(fn (): Product => Product::factory()->create(['name' => $name, 'cost_price' => $costPrice, ...$attributes]));
    }

    /**
     * @param  'MAIN'|'DEAD'|'RETUR'  $warehouseCode
     */
    private function putInStock(Product $product, string $quantity, string $warehouseCode = 'MAIN'): void
    {
        $this->store->run(fn () => Stock::query()->updateOrInsert(
            ['product_id' => $product->id, 'warehouse_id' => Warehouse::query()->where('code', $warehouseCode)->value('id')],
            ['quantity' => $quantity],
        ));
    }

    /**
     * Five active products â€” Kuas 10 at 6.000 with a minimum of 20, Cat with none left, Ember at -3, Paku 4 in the
     * shelf with 2 in dead stock and 6 waiting in the returns warehouse at 500, Tali 3 without a purchase price â€” and
     * an inactive Cat Lama that still holds 5 at 100 and an inactive Kaleng that holds nothing.
     */
    private function stockTheShelves(): void
    {
        $this->putInStock($this->product('Kuas', '6000', ['min_stock' => 20]), '10');
        $this->putInStock($this->product('Cat'), '0');
        $this->putInStock($this->product('Ember'), '-3');

        $nails = $this->product('Paku', '500');
        $this->putInStock($nails, '4');
        $this->putInStock($nails, '2', 'DEAD');
        $this->putInStock($nails, '6', 'RETUR');

        $this->putInStock($this->product('Tali', '0'), '3');
        $this->putInStock($this->product('Cat Lama', '100', ['is_active' => false]), '5');
        $this->product('Kaleng', '100', ['is_active' => false]);
    }

    /**
     * @param  callable(Assert): mixed  $assertions  Run once the deferred parts have loaded.
     * @param  array<string, string|int|float>  $query
     */
    private function assertReport(callable $assertions, array $query = []): void
    {
        $this->actingAs($this->admin)->get($this->url($query))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Reports/Stock')
            ->loadDeferredProps(fn (Assert $reload) => $assertions($reload)));
    }

    /**
     * Whether the rows of the table, in this order, are the products of these names.
     *
     * @param  list<string>  $names
     */
    private function namesAre(array $names): Closure
    {
        return fn (Collection $rows): bool => $rows->pluck('name')->all() === $names;
    }

    public function test_only_users_who_may_see_reports_may_open_the_stock_report(): void
    {
        $this->actingAs($this->cashier)->get($this->url())->assertForbidden();
    }

    public function test_the_parts_of_the_report_are_loaded_apart_from_the_page(): void
    {
        $this->actingAs($this->admin)->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->missing('summary')
            ->missing('positions')
            ->has('statuses', 5)
            ->where('filters', ['search' => '', 'category' => null, 'brand' => null, 'status' => '', 'as_of' => null])
            ->where('first_recorded_on', null));
    }

    private function recordOn(Product $product, string $quantity, string $movedAt, string $warehouseCode = 'MAIN'): void
    {
        $this->store->run(fn () => StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => Warehouse::query()->where('code', $warehouseCode)->value('id'),
            'type' => StockMovementType::OpnameAdjustment,
            'quantity' => $quantity,
            'balance_after' => $quantity,
            'moved_at' => $movedAt,
        ]));
    }

    public function test_the_stock_can_be_shown_as_it_was_on_an_earlier_day(): void
    {
        $nails = $this->product('Paku', '500');
        $this->recordOn($nails, '10', '2025-11-05 09:00:00');
        $this->recordOn($nails, '-4', '2025-11-10 09:00:00');
        $this->recordOn($nails, '4', '2025-11-10 09:00:00', 'DEAD');
        $this->recordOn($nails, '-4', '2025-12-02 09:00:00', 'DEAD');

        $this->assertReport(fn (Assert $report) => $report
            ->where('summary.dead_stock', ['products_count' => 1, 'value' => '2000.00'])
            ->where('summary.sellable_value', '3000.00')
            ->where('positions.data.0.sellable_stock', '6.000')
            ->where('positions.data.0.dead_stock', '4.000'), ['as_of' => '2025-11-30']);

        $this->assertReport(fn (Assert $report) => $report
            ->where('summary.dead_stock', ['products_count' => 0, 'value' => '0.00'])
            ->where('positions.data.0.dead_stock', '0.000'), ['as_of' => '2025-12-02']);
    }

    public function test_a_day_that_is_not_a_past_day_shows_the_stock_of_now(): void
    {
        foreach (['2026-02-30', 'tomorrow', now()->toDateString(), now()->addDay()->toDateString()] as $asOf) {
            $this->actingAs($this->admin)->get($this->url(['as_of' => $asOf]))->assertInertia(fn (Assert $page) => $page
                ->where('filters.as_of', null)
                ->where('first_recorded_on', null));
        }
    }

    public function test_the_day_the_ledger_starts_is_told_for_a_past_day(): void
    {
        $this->recordOn($this->product('Paku'), '10', '2025-11-05 09:00:00');

        $this->actingAs($this->admin)->get($this->url(['as_of' => '2025-10-01']))->assertInertia(fn (Assert $page) => $page
            ->where('filters.as_of', '2025-10-01')
            ->where('first_recorded_on', '2025-11-05'));
    }

    public function test_the_conditions_and_the_inactive_products_are_those_of_the_day_asked_for(): void
    {
        $brush = $this->product('Kuas', '6000', ['min_stock' => 20]);
        $this->recordOn($brush, '30', '2025-11-01 09:00:00');
        $this->recordOn($brush, '-15', '2025-11-20 09:00:00');
        $this->recordOn($brush, '-20', '2025-12-05 09:00:00');

        $oldPaint = $this->product('Cat Lama', '100', ['is_active' => false]);
        $this->recordOn($oldPaint, '5', '2025-11-01 09:00:00');
        $this->recordOn($oldPaint, '-5', '2025-11-25 09:00:00');

        $this->assertReport(fn (Assert $report) => $report
            ->where('summary.products_count', 1)
            ->where('summary.below_minimum_count', 0)
            ->where('summary.negative_count', 0)
            ->where('positions.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Cat Lama', 'Kuas']), ['as_of' => '2025-11-15']);

        $this->assertReport(fn (Assert $report) => $report
            ->where('summary.below_minimum_count', 1)
            ->where('summary.negative_count', 0)
            ->where('positions.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Kuas']), ['as_of' => '2025-11-30']);

        $this->assertReport(fn (Assert $report) => $report
            ->where('summary.below_minimum_count', 1)
            ->where('summary.negative_count', 1), ['as_of' => '2025-12-31']);
    }

    public function test_the_summary_counts_and_values_the_stock_that_is_there(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report
            ->where('summary', [
                'products_count' => 5,
                'below_minimum_count' => 1,
                'out_of_stock_count' => 1,
                'low_stock_count' => 1,
                'healthy_count' => 3,
                'negative_count' => 1,
                'sellable_value' => '62500.00',
                'damaged_goods' => ['products_count' => 0, 'value' => '0.00'],
                'dead_stock' => ['products_count' => 1, 'value' => '1000.00'],
                'returns_waiting' => ['products_count' => 1, 'value' => '3000.00'],
                'products_without_cost' => 1,
            ]));
    }

    public function test_damaged_goods_are_told_apart_inside_the_sellable_value(): void
    {
        $this->putInStock($this->product('Kuas'), '10');
        $this->putInStock($this->product('Kuas DG', '500', ['is_damaged_goods' => true]), '4');
        $this->putInStock($this->product('Cat DG habis', '500', ['is_damaged_goods' => true]), '0');

        $this->assertReport(fn (Assert $report) => $report
            ->where('summary.sellable_value', '12000.00')
            ->where('summary.damaged_goods', ['products_count' => 1, 'value' => '2000.00']));
    }

    public function test_a_store_without_products_has_a_report_of_zeroes(): void
    {
        $this->assertReport(fn (Assert $report) => $report
            ->where('summary.products_count', 0)
            ->where('summary.sellable_value', '0.00')
            ->where('summary.healthy_count', 0)
            ->where('positions.total', 0)
            ->where('positions.data', []));
    }

    public function test_the_table_lists_products_by_name_with_their_stock_per_kind_of_warehouse(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report
            ->where('positions.total', 6)
            ->where('positions.data', $this->namesAre(['Cat', 'Cat Lama', 'Ember', 'Kuas', 'Paku', 'Tali']))
            ->where('positions.data.4.sellable_stock', fn ($quantity) => (float) $quantity === 4.0)
            ->where('positions.data.4.dead_stock', fn ($quantity) => (float) $quantity === 2.0)
            ->where('positions.data.4.returns_stock', fn ($quantity) => (float) $quantity === 6.0)
            ->where('positions.data.4.sellable_value', '2000.00')
            ->where('positions.data.3.is_below_minimum', true)
            ->where('positions.data.2.has_negative_stock', true)
            ->where('positions.data.2.sellable_value', '0.00')
            ->where('positions.data.1.is_active', false));
    }

    public function test_an_inactive_product_without_stock_is_not_in_the_report(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report->where('positions.data', fn ($rows) => ! $rows->pluck('name')->contains('Kaleng')));
    }

    public function test_each_stock_condition_narrows_the_table_to_its_products(): void
    {
        $this->stockTheShelves();

        $expected = [
            'below_minimum' => ['Kuas'],
            'out_of_stock' => ['Cat'],
            'negative' => ['Ember'],
            'dead_stock' => ['Paku'],
            'returns_waiting' => ['Paku'],
        ];

        foreach ($expected as $status => $names) {
            $this->assertReport(fn (Assert $report) => $report->where('positions.data', $this->namesAre($names))->where('positions.total', count($names)), ['status' => $status]);
        }
    }

    public function test_the_table_can_be_ordered_by_each_of_its_columns_in_either_direction(): void
    {
        $this->stockTheShelves();

        // Products that tie keep their order by name; Ember's negative balance is worth nothing, so it ties with Cat and Tali at 0.
        $expected = [
            ['sort' => 'name', 'direction' => 'desc', 'names' => ['Tali', 'Paku', 'Kuas', 'Ember', 'Cat Lama', 'Cat']],
            ['sort' => 'sellable_stock', 'direction' => 'desc', 'names' => ['Kuas', 'Cat Lama', 'Paku', 'Tali', 'Cat', 'Ember']],
            ['sort' => 'sellable_stock', 'direction' => 'asc', 'names' => ['Ember', 'Cat', 'Tali', 'Paku', 'Cat Lama', 'Kuas']],
            ['sort' => 'dead_stock', 'direction' => 'desc', 'names' => ['Paku', 'Cat', 'Cat Lama', 'Ember', 'Kuas', 'Tali']],
            ['sort' => 'returns_stock', 'direction' => 'desc', 'names' => ['Paku', 'Cat', 'Cat Lama', 'Ember', 'Kuas', 'Tali']],
            ['sort' => 'cost_price', 'direction' => 'desc', 'names' => ['Kuas', 'Cat', 'Ember', 'Paku', 'Cat Lama', 'Tali']],
            ['sort' => 'cost_price', 'direction' => 'asc', 'names' => ['Tali', 'Cat Lama', 'Paku', 'Cat', 'Ember', 'Kuas']],
            ['sort' => 'sellable_value', 'direction' => 'desc', 'names' => ['Kuas', 'Paku', 'Cat Lama', 'Cat', 'Ember', 'Tali']],
            ['sort' => 'sellable_value', 'direction' => 'asc', 'names' => ['Cat', 'Ember', 'Tali', 'Cat Lama', 'Paku', 'Kuas']],
        ];

        foreach ($expected as $order) {
            $this->assertReport(fn (Assert $report) => $report
                ->where('positions.sort', $order['sort'])
                ->where('positions.direction', $order['direction'])
                ->where('positions.data', $this->namesAre($order['names'])), ['positions_sort' => $order['sort'], 'positions_direction' => $order['direction']]);
        }
    }

    public function test_a_column_starts_with_names_a_to_z_and_figures_biggest_first(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report
            ->where('positions.sort', 'name')
            ->where('positions.direction', 'asc')
            ->where('positions.data', $this->namesAre(['Cat', 'Cat Lama', 'Ember', 'Kuas', 'Paku', 'Tali'])));
        $this->assertReport(fn (Assert $report) => $report
            ->where('positions.direction', 'desc')
            ->where('positions.data.0.name', 'Kuas'), ['positions_sort' => 'sellable_value']);
    }

    public function test_an_order_that_is_not_an_option_is_the_default_one(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report
            ->where('positions.sort', 'name')
            ->where('positions.direction', 'asc')
            ->where('positions.data', $this->namesAre(['Cat', 'Cat Lama', 'Ember', 'Kuas', 'Paku', 'Tali'])), ['positions_sort' => 'nonsense', 'positions_direction' => 'sideways']);
    }

    public function test_the_order_holds_across_pages_and_with_the_filters(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report
            ->where('positions.total', 1)
            ->where('positions.data', $this->namesAre(['Kuas'])), ['positions_sort' => 'sellable_stock', 'status' => 'below_minimum']);

        foreach (range(1, 16) as $number) {
            $this->putInStock($this->product("Barang {$number}", '10'), (string) $number);
        }

        $this->assertReport(fn (Assert $report) => $report
            ->where('positions.current_page', 2)
            ->where('positions.data.0.sellable_stock', fn ($quantity) => (float) $quantity === 4.0), ['positions_sort' => 'sellable_stock', 'positions_direction' => 'desc', 'positions_page' => 2]);
    }

    public function test_an_inactive_product_is_neither_low_nor_out_of_stock(): void
    {
        $this->putInStock($this->product('Cat Lama', '100', ['is_active' => false, 'min_stock' => 50]), '0');
        $this->putInStock($this->product('Kuas Lama', '100', ['is_active' => false, 'min_stock' => 50]), '5');

        $this->assertReport(fn (Assert $report) => $report->where('positions.total', 1)->where('positions.data.0.name', 'Kuas Lama'));
        $this->assertReport(fn (Assert $report) => $report->where('positions.total', 0), ['status' => 'below_minimum']);
        $this->assertReport(fn (Assert $report) => $report->where('positions.total', 0), ['status' => 'out_of_stock']);
    }

    public function test_the_table_can_be_searched_and_narrowed_by_category_and_brand(): void
    {
        $this->store->run(function (): void {
            $paint = Category::factory()->create(['name' => 'Cat']);
            $tools = Category::factory()->create(['name' => 'Alat']);
            $brand = Brand::factory()->create(['name' => 'Dulux']);

            Product::factory()->create(['name' => 'Cat Tembok', 'sku' => 'CT-1', 'category_id' => $paint->id, 'brand_id' => $brand->id]);
            Product::factory()->create(['name' => 'Cat Kayu', 'sku' => 'CK-1', 'category_id' => $paint->id]);
            Product::factory()->create(['name' => 'Palu', 'sku' => 'PL-1', 'category_id' => $tools->id]);
        });
        [$paintId, $brandId] = $this->store->run(fn (): array => [Category::query()->where('name', 'Cat')->value('id'), Brand::query()->where('name', 'Dulux')->value('id')]);

        $this->assertReport(fn (Assert $report) => $report->where('positions.data', $this->namesAre(['Cat Kayu', 'Cat Tembok'])), ['category' => $paintId]);
        $this->assertReport(fn (Assert $report) => $report->where('positions.data', $this->namesAre(['Cat Tembok'])), ['category' => $paintId, 'brand' => $brandId]);
        $this->assertReport(fn (Assert $report) => $report->where('positions.data', $this->namesAre(['Cat Kayu', 'Cat Tembok'])), ['search' => 'cat']);
        $this->assertReport(fn (Assert $report) => $report->where('positions.data', $this->namesAre(['Palu'])), ['search' => 'PL-1']);
    }

    public function test_a_typed_percent_in_the_search_is_not_a_wildcard(): void
    {
        $this->product('Kuas');
        $this->product('Diskon 50% Cat');

        $this->assertReport(fn (Assert $report) => $report->where('positions.data', $this->namesAre(['Diskon 50% Cat'])), ['search' => '%']);
    }

    public function test_a_page_past_the_last_gives_the_last_one(): void
    {
        $this->store->run(fn () => Product::factory()->count(16)->create());

        $this->assertReport(fn (Assert $report) => $report
            ->where('positions.current_page', 2)
            ->where('positions.last_page', 2)
            ->where('positions.from', 16)
            ->where('positions.to', 16)
            ->where('positions.total', 16)
            ->has('positions.data', 1), ['positions_page' => 9]);
    }

    public function test_a_page_size_that_is_not_an_option_is_the_first_option_and_a_bad_filter_is_left_out(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report
            ->where('positions.per_page', 15)
            ->where('positions.total', 6), ['positions_per_page' => 7, 'status' => 'bogus', 'category' => 'abc', 'brand' => '-4']);
    }
}
