<?php

namespace Tests\Feature\Tenancy;

use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RestockReportService;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class RestockReportTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_restock';

    private Store $store;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_restock', 'receipt_prefix' => 'TRS', 'name' => 'Toko Uji']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
    }

    /**
     * @param  array<string, string|int>  $query
     */
    private function url(array $query = []): string
    {
        return route('tenant.reports.stock.restock', ['tenant' => $this->store->code, ...$query]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, string $costPrice = '1000', array $attributes = []): Product
    {
        return $this->store->run(fn (): Product => Product::factory()->create(['name' => $name, 'cost_price' => $costPrice, ...$attributes]));
    }

    private function putInStock(Product $product, string $quantity): void
    {
        $this->store->run(fn () => Stock::query()->updateOrInsert(
            ['product_id' => $product->id, 'warehouse_id' => Warehouse::query()->where('code', 'MAIN')->value('id')],
            ['quantity' => $quantity],
        ));
    }

    private function sell(Product $product, string $baseQuantity, int $daysAgo, bool $isVoided = false): void
    {
        $this->store->run(function () use ($product, $baseQuantity, $daysAgo, $isVoided): void {
            $sale = Sale::factory()->create(['business_date' => today()->subDays($daysAgo), 'status' => $isVoided ? SaleStatus::Voided : SaleStatus::Completed]);
            SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'base_quantity' => $baseQuantity]);
        });
    }

    /**
     * With the pace read from 30 days and 14 days wanted:
     * Kuas has 5 and sold 30 (1 a day, so 5 days left and 14 wanted: buy 9 at 1.000), Cat has 100 and sold 30 (plenty),
     * Palu has 8 against a minimum of 20 and sold nothing (buy 12 at 2.000), Tali is out and sold 15 (buy 7, no price),
     * Ember is at -3 with no minimum and Habis at 0 with none (nothing wanted), Lama is inactive with a minimum, a DG
     * product has a minimum, Void sold only on a voided receipt, and Kari sold 90 but 40 days ago (outside 30 days).
     */
    private function stockTheShelves(): void
    {
        $brush = $this->product('Kuas');
        $this->putInStock($brush, '5');
        $this->sell($brush, '30', 10);

        $paint = $this->product('Cat');
        $this->putInStock($paint, '100');
        $this->sell($paint, '30', 10);

        $this->putInStock($this->product('Palu', '2000', ['min_stock' => 20]), '8');

        $rope = $this->product('Tali', '0');
        $this->putInStock($rope, '0');
        $this->sell($rope, '15', 3);

        $this->putInStock($this->product('Ember'), '-3');
        $this->putInStock($this->product('Habis'), '0');
        $this->putInStock($this->product('Lama', '1000', ['is_active' => false, 'min_stock' => 10]), '0');
        $this->putInStock($this->product('Kuas DG', '1000', ['is_damaged_goods' => true, 'min_stock' => 5]), '0');

        $voided = $this->product('Void');
        $this->putInStock($voided, '1');
        $this->sell($voided, '100', 5, isVoided: true);

        $old = $this->product('Kari');
        $this->putInStock($old, '1');
        $this->sell($old, '90', 40);
    }

    /**
     * @param  callable(Assert): mixed  $assertions  Run once the deferred parts have loaded.
     * @param  array<string, string|int>  $query
     */
    private function assertReport(callable $assertions, array $query = []): void
    {
        $this->actingAs($this->admin)->get($this->url($query))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Reports/Restock')
            ->loadDeferredProps(fn (Assert $reload) => $assertions($reload)));
    }

    /**
     * @param  list<string>  $names
     */
    private function namesAre(array $names): Closure
    {
        return fn (Collection $rows): bool => $rows->pluck('name')->all() === $names;
    }

    public function test_the_products_that_run_out_first_come_soonest_first_and_leave_out_those_that_do_not_sell(): void
    {
        $this->stockTheShelves();
        $restock = app(RestockReportService::class);

        $urgent = $this->store->run(fn (): array => $restock->mostUrgent(30, 14, 5));

        $this->assertSame(['Tali', 'Kuas'], array_column($urgent, 'name'));
        $this->assertSame(['0.0', '5.0'], array_column($urgent, 'days_left'));
        $this->assertSame(['Tali'], array_column($this->store->run(fn (): array => $restock->mostUrgent(30, 14, 1)), 'name'));
    }

    public function test_only_users_who_may_see_reports_may_open_the_restock_report(): void
    {
        $this->actingAs($this->cashier)->get($this->url())->assertForbidden();
    }

    public function test_the_parts_of_the_report_are_loaded_apart_from_the_page(): void
    {
        $this->actingAs($this->admin)->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->missing('summary')
            ->missing('products')
            ->where('filters', ['window' => 30, 'cover' => 14, 'category' => null])
            ->where('window_options', [30, 7, 14, 60, 90])
            ->where('cover_options', [14, 7, 30]));
    }

    public function test_the_table_lists_what_will_not_last_the_most_urgent_first(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report
            ->where('products.total', 3)
            ->where('products.sort', 'days_left')
            ->where('products.direction', 'asc')
            ->where('products.data', $this->namesAre(['Tali', 'Kuas', 'Palu']))
            ->where('products.data.0.days_left', fn ($days) => (float) $days === 0.0)
            ->where('products.data.0.suggested_quantity', '7')
            ->where('products.data.1.days_left', fn ($days) => (float) $days === 5.0)
            ->where('products.data.1.suggested_quantity', '9')
            ->where('products.data.1.suggested_cost', '9000.00')
            ->where('products.data.2.days_left', null)
            ->where('products.data.2.suggested_quantity', '12')
            ->where('products.data.2.suggested_cost', '24000.00')
            ->where('products.data.2.is_below_minimum', true));
    }

    public function test_the_summary_counts_what_to_buy_and_what_it_costs(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report->where('summary', [
            'products_count' => 3,
            'purchase_cost' => '33000.00',
            'out_of_stock_selling_count' => 1,
            'below_minimum_count' => 1,
            'products_without_cost' => 1,
        ]));
    }

    public function test_a_product_that_is_not_bought_is_left_out(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report->where('products.data', fn (Collection $rows): bool => $rows->pluck('name')->intersect(['Cat', 'Ember', 'Habis', 'Lama', 'Kuas DG', 'Void', 'Kari'])->isEmpty()));
    }

    public function test_a_longer_period_of_sales_sets_a_different_pace(): void
    {
        $this->stockTheShelves();

        // Kari sold 90 in 60 days: 21 wanted over 14 days, 1 on the shelf.
        $this->assertReport(fn (Assert $report) => $report
            ->where('products.data', fn (Collection $rows): bool => $rows->firstWhere('name', 'Kari')['suggested_quantity'] === '20'), ['window' => 60]);
    }

    public function test_the_days_the_stock_should_last_change_what_is_suggested(): void
    {
        $this->stockTheShelves();

        // 7 days: Kuas wants 7 and has 5, Tali wants 3.5 (4 when rounded up), Palu still wants its minimum of 20.
        $this->assertReport(fn (Assert $report) => $report
            ->where('products.data', fn (Collection $rows): bool => $rows->firstWhere('name', 'Kuas')['suggested_quantity'] === '2'
                && $rows->firstWhere('name', 'Tali')['suggested_quantity'] === '4'
                && $rows->firstWhere('name', 'Palu')['suggested_quantity'] === '12'), ['cover' => 7]);
    }

    public function test_options_that_are_not_options_are_the_defaults(): void
    {
        $this->actingAs($this->admin)->get($this->url(['window' => 45, 'cover' => 5]))->assertInertia(fn (Assert $page) => $page
            ->where('filters.window', 30)
            ->where('filters.cover', 14));
    }

    public function test_the_table_can_be_ordered_by_each_of_its_columns_in_either_direction(): void
    {
        $this->stockTheShelves();

        // Products without days left (no sales) go after the others whichever way the table runs.
        $expected = [
            ['sort' => 'days_left', 'direction' => 'desc', 'names' => ['Kuas', 'Tali', 'Palu']],
            ['sort' => 'name', 'direction' => 'asc', 'names' => ['Kuas', 'Palu', 'Tali']],
            ['sort' => 'name', 'direction' => 'desc', 'names' => ['Tali', 'Palu', 'Kuas']],
            ['sort' => 'sellable_stock', 'direction' => 'asc', 'names' => ['Tali', 'Kuas', 'Palu']],
            ['sort' => 'sold_quantity', 'direction' => 'desc', 'names' => ['Kuas', 'Tali', 'Palu']],
            ['sort' => 'suggested_quantity', 'direction' => 'desc', 'names' => ['Palu', 'Kuas', 'Tali']],
            ['sort' => 'suggested_cost', 'direction' => 'desc', 'names' => ['Palu', 'Kuas', 'Tali']],
            ['sort' => 'suggested_cost', 'direction' => 'asc', 'names' => ['Tali', 'Kuas', 'Palu']],
        ];

        foreach ($expected as $order) {
            $this->assertReport(fn (Assert $report) => $report
                ->where('products.sort', $order['sort'])
                ->where('products.direction', $order['direction'])
                ->where('products.data', $this->namesAre($order['names'])), ['sort' => $order['sort'], 'direction' => $order['direction']]);
        }
    }

    public function test_a_column_starts_with_the_most_urgent_or_names_a_to_z_and_figures_biggest_first(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report->where('products.direction', 'asc')->where('products.data.0.name', 'Kuas'), ['sort' => 'name']);
        $this->assertReport(fn (Assert $report) => $report->where('products.direction', 'desc')->where('products.data.0.name', 'Palu'), ['sort' => 'suggested_quantity']);
    }

    public function test_an_order_that_is_not_an_option_is_the_default_one(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report
            ->where('products.sort', 'days_left')
            ->where('products.direction', 'asc')
            ->where('products.data', $this->namesAre(['Tali', 'Kuas', 'Palu'])), ['sort' => 'nonsense', 'direction' => 'sideways']);
    }

    public function test_the_table_can_be_narrowed_to_a_category(): void
    {
        $this->stockTheShelves();
        $toolsId = $this->store->run(fn (): int => Category::factory()->create(['name' => 'Alat'])->id);
        $this->store->run(fn () => Product::query()->where('name', 'Palu')->update(['category_id' => $toolsId]));

        $this->assertReport(fn (Assert $report) => $report->where('products.data', $this->namesAre(['Palu']))->where('summary.products_count', 3), ['category' => $toolsId]);
    }

    public function test_a_store_with_nothing_to_buy_has_a_report_of_zeroes(): void
    {
        $this->assertReport(fn (Assert $report) => $report
            ->where('summary', ['products_count' => 0, 'purchase_cost' => '0.00', 'out_of_stock_selling_count' => 0, 'below_minimum_count' => 0, 'products_without_cost' => 0])
            ->where('products.total', 0)
            ->where('products.data', []));
    }

    public function test_a_page_past_the_last_gives_the_last_one(): void
    {
        foreach (range(1, 16) as $number) {
            $this->putInStock($this->product("Barang {$number}", attributes: ['min_stock' => 5]), '0');
        }

        $this->assertReport(fn (Assert $report) => $report
            ->where('products.current_page', 2)
            ->where('products.from', 16)
            ->has('products.data', 1), ['page' => 9]);
    }
}
