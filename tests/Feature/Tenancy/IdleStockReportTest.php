<?php

namespace Tests\Feature\Tenancy;

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
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

class IdleStockReportTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_idle_stock';

    private Store $store;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_idle_stock', 'receipt_prefix' => 'TID', 'name' => 'Toko Uji']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
    }

    /**
     * @param  array<string, string|int>  $query
     */
    private function url(array $query = []): string
    {
        return route('tenant.reports.stock.idle', ['tenant' => $this->store->code, ...$query]);
    }

    /**
     * A product added long before any period the report asks about, unless the attributes say otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, string $costPrice = '1000', array $attributes = []): Product
    {
        return $this->store->run(fn (): Product => Product::factory()->create(['name' => $name, 'cost_price' => $costPrice, 'created_at' => now()->subDays(400), ...$attributes]));
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

    private function sell(Product $product, int $daysAgo, bool $isVoided = false): void
    {
        $this->store->run(function () use ($product, $daysAgo, $isVoided): void {
            $sale = Sale::factory()->create(['business_date' => today()->subDays($daysAgo), 'status' => $isVoided ? SaleStatus::Voided : SaleStatus::Completed]);
            SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id]);
        });
    }

    /**
     * Kuas (10 at 1.000) sold last 90 days ago, Cat (10 at 1.000) sold 10 days ago, Palu (2 at 1.000) never sold, Baru
     * (3 at 1.000) added today and never sold, Void (2 at 100) whose only sale was voided, Tali (6 without a purchase
     * price) sold 100 days ago, Habis with nothing left, Lama inactive with stock, and Hanya Dead whose stock is all
     * dead stock; every one of them added long ago except Baru.
     */
    private function stockTheShelves(): void
    {
        $brush = $this->product('Kuas');
        $this->putInStock($brush, '10');
        $this->sell($brush, 90);

        $paint = $this->product('Cat');
        $this->putInStock($paint, '10');
        $this->sell($paint, 10);

        $this->putInStock($this->product('Palu'), '2');
        $this->putInStock($this->product('Baru', attributes: ['created_at' => now()]), '3');

        $voided = $this->product('Void', '100');
        $this->putInStock($voided, '2');
        $this->sell($voided, 5, isVoided: true);

        $rope = $this->product('Tali', '0');
        $this->putInStock($rope, '6');
        $this->sell($rope, 100);

        $this->putInStock($this->product('Habis'), '0');
        $this->putInStock($this->product('Lama', attributes: ['is_active' => false]), '5');
        $this->putInStock($this->product('Hanya Dead'), '4', 'DEAD');
    }

    /**
     * @param  callable(Assert): mixed  $assertions  Run once the deferred parts have loaded.
     * @param  array<string, string|int>  $query
     */
    private function assertReport(callable $assertions, array $query = []): void
    {
        $this->actingAs($this->admin)->get($this->url($query))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Reports/IdleStock')
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

    public function test_only_users_who_may_see_reports_may_open_the_idle_stock_report(): void
    {
        $this->actingAs($this->cashier)->get($this->url())->assertForbidden();
    }

    public function test_the_parts_of_the_report_are_loaded_apart_from_the_page(): void
    {
        $this->actingAs($this->admin)->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->missing('summary')
            ->missing('products')
            ->where('filters', ['days' => 60, 'category' => null])
            ->where('days_options', [30, 60, 90, 180]));
    }

    public function test_the_table_lists_stock_that_did_not_sell_with_the_most_capital_first(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report
            ->where('products.total', 5)
            ->where('products.data', $this->namesAre(['Kuas', 'Baru', 'Palu', 'Void', 'Tali']))
            ->where('products.data.0.stock_value', '10000.00')
            ->where('products.data.0.last_sold_on', today()->subDays(90)->toDateString())
            ->where('products.data.0.days_since_sold', 90)
            ->where('products.data.1.last_sold_on', null)
            ->where('products.data.1.days_since_sold', null));
    }

    public function test_the_summary_counts_the_idle_products_and_the_capital_they_tie_up(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report->where('summary', [
            'products_count' => 5,
            'stock_value' => '15200.00',
            'share_percent' => '60.3',
            'never_sold_count' => 3,
            'products_without_cost' => 1,
            'first_sale_on' => today()->subDays(100)->toDateString(),
            'history_days' => 101,
        ]));
    }

    public function test_the_summary_tells_how_far_back_the_sales_go(): void
    {
        $this->assertReport(fn (Assert $report) => $report->where('summary.first_sale_on', null)->where('summary.history_days', null));

        $brush = $this->product('Kuas');
        $this->sell($brush, 3);
        $this->sell($brush, 20, isVoided: true);

        $this->assertReport(fn (Assert $report) => $report
            ->where('summary.first_sale_on', today()->subDays(3)->toDateString())
            ->where('summary.history_days', 4));
    }

    public function test_a_receipt_that_was_voided_is_not_a_sale(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report
            ->where('products.data', fn (Collection $rows): bool => $rows->firstWhere('name', 'Void')['last_sold_on'] === null));
    }

    public function test_a_product_that_was_never_sold_is_listed_however_recently_it_was_added(): void
    {
        $this->stockTheShelves();

        $this->assertReport(fn (Assert $report) => $report->where('products.data', fn (Collection $rows): bool => $rows->firstWhere('name', 'Baru')['last_sold_on'] === null));
    }

    public function test_counting_the_stock_does_not_make_a_product_sold(): void
    {
        $this->stockTheShelves();
        $this->store->run(fn () => StockMovement::factory()->create([
            'product_id' => Product::query()->where('name', 'Palu')->value('id'),
            'warehouse_id' => Warehouse::query()->where('code', 'MAIN')->value('id'),
            'type' => StockMovementType::OpnameAdjustment,
            'quantity' => 2,
            'balance_after' => 2,
            'moved_at' => now(),
        ]));

        $this->assertReport(fn (Assert $report) => $report->where('products.data', fn (Collection $rows): bool => $rows->pluck('name')->contains('Palu')));
    }

    public function test_the_number_of_days_decides_what_is_idle(): void
    {
        $brush = $this->product('Kuas');
        $this->putInStock($brush, '10');
        $this->sell($brush, 45);

        $this->assertReport(fn (Assert $report) => $report->where('products.total', 1)->where('summary.products_count', 1), ['days' => 30]);
        $this->assertReport(fn (Assert $report) => $report->where('products.total', 0)->where('summary.products_count', 0), ['days' => 60]);
    }

    public function test_a_number_of_days_that_is_not_an_option_is_the_default(): void
    {
        $this->actingAs($this->admin)->get($this->url(['days' => 45]))->assertInertia(fn (Assert $page) => $page->where('filters.days', 60));
    }

    public function test_the_table_can_be_narrowed_to_a_category(): void
    {
        $paintId = $this->store->run(fn (): int => Category::factory()->create(['name' => 'Cat'])->id);
        $this->putInStock($this->product('Cat Tembok', attributes: ['category_id' => $paintId]), '3');
        $this->putInStock($this->product('Palu'), '3');

        $this->assertReport(fn (Assert $report) => $report->where('products.data', $this->namesAre(['Cat Tembok']))->where('summary.products_count', 2), ['category' => $paintId]);
    }

    public function test_a_store_without_idle_stock_has_a_report_of_zeroes(): void
    {
        $this->assertReport(fn (Assert $report) => $report
            ->where('summary', ['products_count' => 0, 'stock_value' => '0.00', 'share_percent' => '0.0', 'never_sold_count' => 0, 'products_without_cost' => 0, 'first_sale_on' => null, 'history_days' => null])
            ->where('products.total', 0)
            ->where('products.data', []));
    }

    public function test_a_page_past_the_last_gives_the_last_one(): void
    {
        foreach (range(1, 16) as $number) {
            $this->putInStock($this->product("Barang {$number}"), '1');
        }

        $this->assertReport(fn (Assert $report) => $report
            ->where('products.current_page', 2)
            ->where('products.last_page', 2)
            ->where('products.from', 16)
            ->has('products.data', 1), ['page' => 9]);
    }
}
