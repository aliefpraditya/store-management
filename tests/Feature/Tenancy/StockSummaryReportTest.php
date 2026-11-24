<?php

namespace Tests\Feature\Tenancy;

use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class StockSummaryReportTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_stock_summary';

    private Store $store;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_stock_summary', 'receipt_prefix' => 'TSS', 'name' => 'Toko Uji']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
    }

    /**
     * @param  array<string, string|int>  $query
     */
    private function url(array $query = []): string
    {
        return route('tenant.reports.stock', ['tenant' => $this->store->code, ...$query]);
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

    /**
     * @param  callable(Assert): mixed  $assertions  Run once the deferred parts have loaded.
     * @param  array<string, string|int>  $query
     */
    private function assertReport(callable $assertions, array $query = []): void
    {
        $this->actingAs($this->admin)->get($this->url($query))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Reports/StockSummary')
            ->loadDeferredProps(fn (Assert $reload) => $assertions($reload)));
    }

    public function test_only_users_who_may_see_reports_may_open_the_stock_summary(): void
    {
        $this->actingAs($this->cashier)->get($this->url())->assertForbidden();
    }

    public function test_every_part_loads_in_a_group_of_its_own_and_the_page_says_which_defaults_it_uses(): void
    {
        $response = $this->actingAs($this->admin)->get($this->url())->assertOk();

        $this->assertEquals(
            ['summary' => ['summary'], 'groups' => ['groups'], 'best_sellers' => ['best_sellers'], 'idle' => ['idle'], 'restock' => ['restock'], 'urgent' => ['urgent']],
            $response->viewData('page')['deferredProps'],
        );
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('summary')
            ->missing('groups')
            ->missing('best_sellers')
            ->missing('idle')
            ->missing('restock')
            ->missing('urgent')
            ->where('idle_days', 60)
            ->where('restock_window', 30)
            ->where('restock_cover', 14));
    }

    public function test_a_store_without_stock_has_a_summary_of_zeroes_and_empty_lists(): void
    {
        $this->assertReport(fn (Assert $report) => $report
            ->where('summary.products_count', 0)
            ->where('summary.healthy_count', 0)
            ->where('summary.sellable_value', '0.00')
            ->where('groups.total', 0)
            ->where('groups.data', [])
            ->where('groups.value_total', '0.00')
            ->where('groups.unit', null)
            ->where('groups.units', [])
            ->where('best_sellers.data', [])
            ->where('best_sellers.unit', null)
            ->where('idle.share_percent', '0.0')
            ->where('restock.products_count', 0)
            ->where('urgent', []));
    }

    public function test_active_products_are_split_into_healthy_low_and_out_without_overlap(): void
    {
        $this->putInStock($this->product('Habis tanpa minimum'), '0');
        $this->putInStock($this->product('Habis dengan minimum', '1000', ['min_stock' => 5]), '0');
        $this->putInStock($this->product('Menipis', '1000', ['min_stock' => 5]), '2');
        $this->putInStock($this->product('Sehat'), '7');
        $this->putInStock($this->product('Sehat di atas minimum', '1000', ['min_stock' => 5]), '9');
        $this->putInStock($this->product('Nonaktif', '1000', ['is_active' => false, 'min_stock' => 5]), '0');

        $this->assertReport(fn (Assert $report) => $report
            ->where('summary.products_count', 5)
            ->where('summary.out_of_stock_count', 2)
            ->where('summary.low_stock_count', 1)
            ->where('summary.healthy_count', 2)
            ->where('summary.below_minimum_count', 2));
    }

    public function test_the_stock_value_is_shared_out_per_category_with_the_biggest_first_and_the_total_of_all(): void
    {
        [$tools, $paint] = $this->store->run(fn (): array => [Category::factory()->create(['name' => 'Alat'])->id, Category::factory()->create(['name' => 'Cat'])->id]);
        $this->putInStock($this->product('Palu', '2000', ['category_id' => $tools]), '1');
        $this->putInStock($this->product('Cat Tembok', '6000', ['category_id' => $paint]), '10');

        $this->assertReport(fn (Assert $report) => $report
            ->where('groups.grouping', 'category')
            ->where('groups.total', 2)
            ->where('groups.value_total', '62000.00')
            ->where('groups.metric', 'value')
            ->where('groups.quantity_total', null)
            ->where('groups.data.0', ['id' => $paint, 'name' => 'Cat', 'in_stock_quantity' => null, 'value' => '60000.00', 'share_percent' => '96.8'])
            ->where('groups.data.1', ['id' => $tools, 'name' => 'Alat', 'in_stock_quantity' => null, 'value' => '2000.00', 'share_percent' => '3.2']));
    }

    public function test_products_without_a_brand_are_one_group_of_their_own(): void
    {
        $brandId = $this->store->run(fn (): int => Brand::factory()->create(['name' => 'Dulux'])->id);
        $this->putInStock($this->product('Cat Tembok', '1000', ['brand_id' => $brandId]), '3');
        $this->putInStock($this->product('Palu', '1000'), '1');

        $this->assertReport(fn (Assert $report) => $report
            ->where('groups.grouping', 'brand')
            ->where('groups.data.0', ['id' => $brandId, 'name' => 'Dulux', 'in_stock_quantity' => null, 'value' => '3000.00', 'share_percent' => '75.0'])
            ->where('groups.data.1', ['id' => null, 'name' => '(Tanpa merek)', 'in_stock_quantity' => null, 'value' => '1000.00', 'share_percent' => '25.0']), ['groups_by' => 'brand']);
    }

    /**
     * The base units the products are held in, which the quantities are added up within: the products in a unit are created
     * with it, so they are all the same unit.
     */
    private function unit(string $code): string
    {
        $this->store->run(fn () => Unit::factory()->create(['code' => $code, 'name' => $code]));

        return $code;
    }

    private function sell(Product $product, string $baseQuantity, int $daysAgo, bool $isVoided = false): void
    {
        $this->store->run(function () use ($product, $baseQuantity, $daysAgo, $isVoided): void {
            $sale = Sale::factory()->create(['business_date' => today()->subDays($daysAgo), 'status' => $isVoided ? SaleStatus::Voided : SaleStatus::Completed]);
            SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'base_quantity' => $baseQuantity]);
        });
    }

    /**
     * Three products in pieces (Baut A 10 and Baut B 20 at 100 in Baut, Bor listrik 5 at 10.000 in Bor), a bag of cement in
     * kilograms (Semen 300 at 100 in Bangunan), and in pieces too a Bor habis with nothing left and a Bor minus below zero.
     *
     * @return array{0: int, 1: int, 2: int} The ids of the categories Baut, Bor and Bangunan.
     */
    private function stockInTwoUnits(): array
    {
        $pieces = $this->unit('PCS');
        $kilograms = $this->unit('KG');
        [$screws, $drills, $building] = $this->store->run(fn (): array => [Category::factory()->create(['name' => 'Baut'])->id, Category::factory()->create(['name' => 'Bor'])->id, Category::factory()->create(['name' => 'Bangunan'])->id]);

        $this->putInStock($this->product('Baut A', '100', ['category_id' => $screws, 'base_unit_code' => $pieces]), '10');
        $this->putInStock($this->product('Baut B', '100', ['category_id' => $screws, 'base_unit_code' => $pieces]), '20');
        $this->putInStock($this->product('Bor listrik', '10000', ['category_id' => $drills, 'base_unit_code' => $pieces]), '5');
        $this->putInStock($this->product('Bor habis', '10000', ['category_id' => $drills, 'base_unit_code' => $pieces]), '0');
        $this->putInStock($this->product('Bor minus', '10000', ['category_id' => $drills, 'base_unit_code' => $pieces]), '-2');
        $this->putInStock($this->product('Semen', '100', ['category_id' => $building, 'base_unit_code' => $kilograms]), '300');

        return [$screws, $drills, $building];
    }

    public function test_the_groups_can_be_ranked_by_how_much_stock_there_is_in_one_unit(): void
    {
        [$screws, $drills] = $this->stockInTwoUnits();

        $this->assertReport(fn (Assert $report) => $report
            ->where('groups.metric', 'quantity')
            ->where('groups.unit', 'PCS')
            ->where('groups.units', ['PCS', 'KG'])
            ->where('groups.total', 2)
            ->where('groups.quantity_total', '35.000')
            ->where('groups.data.0', ['id' => $screws, 'name' => 'Baut', 'in_stock_quantity' => '30.000', 'value' => '3000.00', 'share_percent' => '85.7'])
            ->where('groups.data.1', ['id' => $drills, 'name' => 'Bor', 'in_stock_quantity' => '5.000', 'value' => '50000.00', 'share_percent' => '14.3']), ['groups_metric' => 'quantity']);
    }

    public function test_another_unit_counts_only_the_products_held_in_it(): void
    {
        [, , $building] = $this->stockInTwoUnits();

        $this->assertReport(fn (Assert $report) => $report
            ->where('groups.unit', 'KG')
            ->where('groups.total', 1)
            ->where('groups.quantity_total', '300.000')
            ->where('groups.data.0', ['id' => $building, 'name' => 'Bangunan', 'in_stock_quantity' => '300.000', 'value' => '30000.00', 'share_percent' => '100.0']), ['groups_metric' => 'quantity', 'groups_unit' => 'KG']);
    }

    public function test_a_unit_that_holds_no_stock_is_the_one_most_products_are_held_in(): void
    {
        $this->stockInTwoUnits();

        $this->assertReport(fn (Assert $report) => $report->where('groups.unit', 'PCS')->where('groups.total', 2), ['groups_metric' => 'quantity', 'groups_unit' => 'XYZ']);
    }

    public function test_by_value_every_unit_counts_and_no_quantity_is_added_up(): void
    {
        $this->stockInTwoUnits();

        $this->assertReport(fn (Assert $report) => $report
            ->where('groups.metric', 'value')
            ->where('groups.unit', 'PCS')
            ->where('groups.value_total', '83000.00')
            ->where('groups.quantity_total', null)
            ->where('groups.data.0.name', 'Bor')
            ->where('groups.data.0.share_percent', '60.2')
            ->where('groups.data.1.name', 'Bangunan')
            ->where('groups.data.2.name', 'Baut')
            ->where('groups.data.2.in_stock_quantity', null));
    }

    public function test_a_metric_that_is_not_one_of_the_options_is_the_value(): void
    {
        $this->assertReport(fn (Assert $report) => $report->where('groups.metric', 'value'), ['groups_metric' => 'weight']);
    }

    public function test_the_best_sellers_are_the_most_sold_in_one_unit_in_the_last_thirty_days(): void
    {
        $this->stockInTwoUnits();
        $products = $this->store->run(fn () => Product::query()->pluck('id', 'name'));
        $sellable = fn (string $name): Product => $this->store->run(fn () => Product::query()->findOrFail($products[$name]));

        $this->sell($sellable('Baut A'), '50', 3);
        $this->sell($sellable('Baut B'), '80', 20);
        $this->sell($sellable('Baut B'), '15', 1);
        $this->sell($sellable('Bor listrik'), '200', 5, isVoided: true);
        $this->sell($sellable('Bor minus'), '999', 40);
        $this->sell($sellable('Semen'), '120', 2);

        $this->assertReport(fn (Assert $report) => $report
            ->where('best_sellers.unit', 'PCS')
            ->where('best_sellers.units', ['PCS', 'KG'])
            ->where('best_sellers.data', fn ($rows) => collect($rows)->map(fn (array $row): array => [$row['name'], $row['sold_quantity']])->all() === [['Baut B', '95.000'], ['Baut A', '50.000']]));

        $this->assertReport(fn (Assert $report) => $report
            ->where('best_sellers.unit', 'KG')
            ->where('best_sellers.data', fn ($rows) => collect($rows)->map(fn (array $row): array => [$row['name'], $row['sold_quantity']])->all() === [['Semen', '120.000']]), ['sellers_unit' => 'KG']);
    }

    public function test_only_the_biggest_five_groups_are_given_and_the_total_covers_them_all(): void
    {
        foreach (range(1, 7) as $number) {
            $categoryId = $this->store->run(fn (): int => Category::factory()->create(['name' => "Kategori {$number}"])->id);
            $this->putInStock($this->product("Barang {$number}", '1000', ['category_id' => $categoryId]), (string) $number);
        }

        $this->assertReport(fn (Assert $report) => $report
            ->has('groups.data', 5)
            ->where('groups.total', 7)
            ->where('groups.data.0.name', 'Kategori 7')
            ->where('groups.value_total', '28000.00'), ['groups_page' => 2, 'groups_per_page' => 10]);
    }

    public function test_a_grouping_that_is_not_one_of_the_options_is_the_category(): void
    {
        $this->assertReport(fn (Assert $report) => $report->where('groups.grouping', 'category'), ['groups_by' => 'color']);
    }
}
