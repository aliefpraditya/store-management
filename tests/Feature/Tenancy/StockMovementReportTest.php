<?php

namespace Tests\Feature\Tenancy;

use App\Enums\StockMovementType;
use App\Enums\UserRole;
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

class StockMovementReportTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_stock_movements';

    private const PERIOD = ['from' => '2026-11-10', 'to' => '2026-11-20'];

    private Store $store;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_stock_movements', 'receipt_prefix' => 'TSM', 'name' => 'Toko Uji']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
    }

    /**
     * @param  array<string, string|int>  $query
     */
    private function url(array $query = []): string
    {
        return route('tenant.reports.stock.movements', ['tenant' => $this->store->code, ...self::PERIOD, ...$query]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, string $costPrice = '1000', array $attributes = []): Product
    {
        return $this->store->run(fn (): Product => Product::factory()->create(['name' => $name, 'cost_price' => $costPrice, ...$attributes]));
    }

    /**
     * Writes one row of the ledger, as the stock service does: the balance after it is worked out from what was there.
     *
     * @param  'MAIN'|'DEAD'|'RETUR'  $warehouseCode
     */
    private function move(Product $product, StockMovementType $type, string $quantity, string $day, string $warehouseCode = 'MAIN'): void
    {
        $this->store->run(function () use ($product, $type, $quantity, $day, $warehouseCode): void {
            $warehouseId = Warehouse::query()->where('code', $warehouseCode)->value('id');
            $balance = (float) StockMovement::query()->where('product_id', $product->id)->where('warehouse_id', $warehouseId)->sum('quantity') + (float) $quantity;

            StockMovement::factory()->create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'moved_at' => "{$day} 10:00:00",
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $balance,
            ]);
            Stock::query()->updateOrInsert(['product_id' => $product->id, 'warehouse_id' => $warehouseId], ['quantity' => $balance]);
        });
    }

    /**
     * Kuas started with 50 before the period, then sold 8 and 4, got 1 back from a voided receipt and 2 from a return
     * onto the shelf and 3 more from a count (all in the period), and sold 5 after it. Cat only has movements outside
     * the period. Paku moved in the period, but in the dead stock warehouse.
     */
    private function stockTheLedger(): void
    {
        $brush = $this->product('Kuas', '1000');
        $this->move($brush, StockMovementType::OpeningBalance, '50', '2026-10-01');
        $this->move($brush, StockMovementType::Sale, '-8', '2026-11-10');
        $this->move($brush, StockMovementType::Sale, '-4', '2026-11-15');
        $this->move($brush, StockMovementType::SaleVoid, '1', '2026-11-16');
        $this->move($brush, StockMovementType::SaleReturn, '2', '2026-11-18');
        $this->move($brush, StockMovementType::OpnameAdjustment, '3', '2026-11-20');
        $this->move($brush, StockMovementType::Sale, '-5', '2026-11-21');

        $paint = $this->product('Cat');
        $this->move($paint, StockMovementType::OpeningBalance, '9', '2026-10-01');
        $this->move($paint, StockMovementType::Sale, '-1', '2026-11-25');

        $nails = $this->product('Paku');
        $this->move($nails, StockMovementType::OpnameAdjustment, '4', '2026-11-12', 'DEAD');
    }

    /**
     * @param  callable(Assert): mixed  $assertions  Run once the deferred parts have loaded.
     * @param  array<string, string|int>  $query
     */
    private function assertReport(callable $assertions, array $query = []): void
    {
        $this->actingAs($this->admin)->get($this->url($query))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Reports/StockMovements')
            ->loadDeferredProps(fn (Assert $reload) => $assertions($reload)));
    }

    /**
     * @param  list<string>  $names
     */
    private function namesAre(array $names): Closure
    {
        return fn (Collection $rows): bool => $rows->pluck('name')->all() === $names;
    }

    public function test_only_users_who_may_see_reports_may_open_the_stock_movement_report(): void
    {
        $this->actingAs($this->cashier)->get($this->url())->assertForbidden();
    }

    public function test_the_parts_of_the_report_are_loaded_apart_from_the_page(): void
    {
        $this->actingAs($this->admin)->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->missing('summary')
            ->missing('products')
            ->where('period', self::PERIOD)
            ->where('filters', ['search' => '', 'category' => null])
            ->where('is_period_cut_back', false));
    }

    public function test_a_product_shows_where_it_started_what_moved_it_and_where_it_ended(): void
    {
        $this->stockTheLedger();

        $this->assertReport(fn (Assert $report) => $report
            ->where('products.data', $this->namesAre(['Kuas']))
            ->where('products.data.0.opening_quantity', fn ($quantity) => (float) $quantity === 50.0)
            ->where('products.data.0.sold_quantity', fn ($quantity) => (float) $quantity === -12.0)
            ->where('products.data.0.voided_quantity', fn ($quantity) => (float) $quantity === 1.0)
            ->where('products.data.0.returned_quantity', fn ($quantity) => (float) $quantity === 2.0)
            ->where('products.data.0.counted_quantity', fn ($quantity) => (float) $quantity === 3.0)
            ->where('products.data.0.closing_quantity', fn ($quantity) => (float) $quantity === 44.0));
    }

    public function test_what_moves_after_the_period_does_not_change_the_closing_balance(): void
    {
        $this->stockTheLedger();

        $this->assertReport(fn (Assert $report) => $report->where('products.data.0.closing_quantity', fn ($quantity) => (float) $quantity === 44.0), ['to' => '2026-11-20']);
        $this->assertReport(fn (Assert $report) => $report->where('products.data.0.closing_quantity', fn ($quantity) => (float) $quantity === 39.0), ['to' => '2026-11-21']);
    }

    public function test_a_product_that_did_not_move_in_the_period_is_not_listed(): void
    {
        $this->stockTheLedger();

        $this->assertReport(fn (Assert $report) => $report->where('products.total', 1)->where('products.data', fn (Collection $rows): bool => ! $rows->pluck('name')->contains('Cat')));
    }

    public function test_the_movements_of_the_dead_stock_warehouse_are_left_out(): void
    {
        $this->stockTheLedger();

        $this->assertReport(fn (Assert $report) => $report->where('products.data', fn (Collection $rows): bool => ! $rows->pluck('name')->contains('Paku')));
    }

    public function test_the_first_and_last_day_of_the_period_are_part_of_it(): void
    {
        $product = $this->product('Kuas');
        $this->move($product, StockMovementType::Sale, '-1', '2026-11-10');
        $this->move($product, StockMovementType::Sale, '-2', '2026-11-20');
        $this->store->run(fn () => StockMovement::query()->where('quantity', -2)->update(['moved_at' => '2026-11-20 23:59:59']));

        $this->assertReport(fn (Assert $report) => $report->where('products.data.0.sold_quantity', fn ($quantity) => (float) $quantity === -3.0));
    }

    public function test_the_summary_values_the_movements_at_the_purchase_price(): void
    {
        $this->stockTheLedger();
        $paint = $this->product('Palu', '2000');
        $this->move($paint, StockMovementType::Sale, '-3', '2026-11-12');
        $this->move($paint, StockMovementType::OpnameAdjustment, '-2', '2026-11-13');

        // Kuas: (12 sold - 1 voided - 2 returned) = 9 out at 1.000; Palu: 3 out at 2.000. Counts: Kuas +3 at 1.000, Palu -2 at 2.000.
        $this->assertReport(fn (Assert $report) => $report->where('summary', ['products_count' => 2, 'sold_cost' => '15000.00', 'counted_cost' => '-1000.00']));
    }

    public function test_a_period_without_movements_has_a_report_of_zeroes(): void
    {
        $this->assertReport(fn (Assert $report) => $report
            ->where('summary', ['products_count' => 0, 'sold_cost' => '0.00', 'counted_cost' => '0.00'])
            ->where('products.total', 0)
            ->where('products.data', []));
    }

    public function test_the_table_can_be_searched_and_narrowed_by_category(): void
    {
        $paintId = $this->store->run(fn (): int => Category::factory()->create(['name' => 'Cat'])->id);
        $this->move($this->product('Cat Tembok', '1000', ['sku' => 'CT-1', 'category_id' => $paintId]), StockMovementType::Sale, '-1', '2026-11-12');
        $this->move($this->product('Palu', '1000', ['sku' => 'PL-1']), StockMovementType::Sale, '-1', '2026-11-12');

        $this->assertReport(fn (Assert $report) => $report->where('products.data', $this->namesAre(['Cat Tembok']))->where('summary.products_count', 2), ['category' => $paintId]);
        $this->assertReport(fn (Assert $report) => $report->where('products.data', $this->namesAre(['Palu'])), ['search' => 'PL-1']);
    }

    public function test_a_period_that_is_not_given_is_this_month_and_one_that_is_too_long_is_cut_back(): void
    {
        $this->actingAs($this->admin)->get(route('tenant.reports.stock.movements', ['tenant' => $this->store->code]))->assertInertia(fn (Assert $page) => $page
            ->where('period.from', today()->startOfMonth()->toDateString())
            ->where('period.to', today()->endOfMonth()->toDateString()));

        $this->actingAs($this->admin)->get($this->url(['from' => '2020-01-01']))->assertInertia(fn (Assert $page) => $page->where('is_period_cut_back', true));
    }

    public function test_the_table_can_be_ordered_by_each_of_its_columns_in_either_direction(): void
    {
        $this->stockTheLedger();
        // Palu sold 3 from nothing (closing -3), Tali was found 10 by a count.
        $this->move($this->product('Palu'), StockMovementType::Sale, '-3', '2026-11-12');
        $this->move($this->product('Tali'), StockMovementType::OpnameAdjustment, '10', '2026-11-12');

        // Kuas: 50 → 44 (sold 12, void 1, return 2, count +3). Products that tie keep their order by name.
        $expected = [
            ['sort' => 'name', 'direction' => 'desc', 'names' => ['Tali', 'Palu', 'Kuas']],
            ['sort' => 'opening_quantity', 'direction' => 'desc', 'names' => ['Kuas', 'Palu', 'Tali']],
            ['sort' => 'opening_quantity', 'direction' => 'asc', 'names' => ['Palu', 'Tali', 'Kuas']],
            ['sort' => 'sold_quantity', 'direction' => 'desc', 'names' => ['Kuas', 'Palu', 'Tali']],
            ['sort' => 'sold_quantity', 'direction' => 'asc', 'names' => ['Tali', 'Palu', 'Kuas']],
            ['sort' => 'voided_quantity', 'direction' => 'desc', 'names' => ['Kuas', 'Palu', 'Tali']],
            ['sort' => 'returned_quantity', 'direction' => 'desc', 'names' => ['Kuas', 'Palu', 'Tali']],
            ['sort' => 'counted_quantity', 'direction' => 'desc', 'names' => ['Tali', 'Kuas', 'Palu']],
            ['sort' => 'counted_quantity', 'direction' => 'asc', 'names' => ['Palu', 'Kuas', 'Tali']],
            ['sort' => 'closing_quantity', 'direction' => 'desc', 'names' => ['Kuas', 'Tali', 'Palu']],
            ['sort' => 'closing_quantity', 'direction' => 'asc', 'names' => ['Palu', 'Tali', 'Kuas']],
        ];

        foreach ($expected as $order) {
            $this->assertReport(fn (Assert $report) => $report
                ->where('products.sort', $order['sort'])
                ->where('products.direction', $order['direction'])
                ->where('products.data', $this->namesAre($order['names'])), ['sort' => $order['sort'], 'direction' => $order['direction']]);
        }
    }

    public function test_the_table_starts_with_names_a_to_z_and_a_column_with_the_biggest_figure_first(): void
    {
        $this->stockTheLedger();
        $this->move($this->product('Palu'), StockMovementType::Sale, '-3', '2026-11-12');

        $this->assertReport(fn (Assert $report) => $report
            ->where('products.sort', 'name')
            ->where('products.direction', 'asc')
            ->where('products.data', $this->namesAre(['Kuas', 'Palu'])));
        $this->assertReport(fn (Assert $report) => $report
            ->where('products.direction', 'desc')
            ->where('products.data', $this->namesAre(['Kuas', 'Palu'])), ['sort' => 'sold_quantity']);
    }

    public function test_an_order_that_is_not_an_option_is_the_default_one(): void
    {
        $this->stockTheLedger();
        $this->move($this->product('Palu'), StockMovementType::Sale, '-3', '2026-11-12');

        $this->assertReport(fn (Assert $report) => $report
            ->where('products.sort', 'name')
            ->where('products.direction', 'asc')
            ->where('products.data', $this->namesAre(['Kuas', 'Palu'])), ['sort' => 'nonsense', 'direction' => 'sideways']);
    }

    public function test_the_order_holds_across_pages(): void
    {
        foreach (range(1, 16) as $number) {
            $this->move($this->product("Barang {$number}"), StockMovementType::Sale, "-{$number}", '2026-11-12');
        }

        $this->assertReport(fn (Assert $report) => $report
            ->where('products.current_page', 2)
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Barang 1'), ['sort' => 'sold_quantity', 'page' => 2]);
    }

    public function test_a_page_past_the_last_gives_the_last_one(): void
    {
        foreach (range(1, 16) as $number) {
            $this->move($this->product("Barang {$number}"), StockMovementType::Sale, '-1', '2026-11-12');
        }

        $this->assertReport(fn (Assert $report) => $report
            ->where('products.current_page', 2)
            ->where('products.from', 16)
            ->has('products.data', 1), ['page' => 9]);
    }
}
