<?php

namespace Tests\Feature\Tenancy;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Enums\UserRole;
use App\Models\OpnameReason;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class StockVarianceReportTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_stock_variance';

    /** Monday 2 to Sunday 15 November 2026, long past, so the counts below are posted inside or outside it for sure. */
    private const PERIOD = ['from' => '2026-11-02', 'to' => '2026-11-15'];

    private Store $store;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_stock_variance', 'receipt_prefix' => 'TSV', 'name' => 'Toko Uji']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
    }

    /**
     * @param  array<string, string|int>  $query
     */
    private function url(array $query = []): string
    {
        return route('tenant.reports.stock.variances', ['tenant' => $this->store->code, ...$query]);
    }

    private function product(string $name): Product
    {
        return $this->store->run(fn (): Product => Product::factory()->create(['name' => $name]));
    }

    /**
     * A count of the shelf (or of what the reason says) posted at a moment, with the items that had a variance: the product, its
     * variance, its price when posted, the name of the reason chosen for it (none for an older count) and what else the item has.
     *
     * @param  list<array{0: Product, 1: string, 2: string, 3: string|null, 4?: array<string, string>}>  $items
     */
    private function postedCount(string $postedAt, array $items = [], StockOpnameReason $reason = StockOpnameReason::Routine, StockOpnameStatus $status = StockOpnameStatus::Posted): StockOpname
    {
        return $this->store->run(function () use ($postedAt, $items, $reason, $status): StockOpname {
            $opname = StockOpname::factory()->create([
                'warehouse_id' => Warehouse::query()->where('code', 'MAIN')->value('id'),
                'reason_code' => $reason,
                'scope' => StockOpnameScope::Selected,
                'status' => $status,
                'snapshot_at' => $postedAt,
                'posted_at' => $status === StockOpnameStatus::Posted ? $postedAt : null,
                'created_by' => $this->admin->id,
            ]);

            foreach ($items as $item) {
                [$product, $variance, $cost, $reasonName] = $item;

                StockOpnameItem::factory()->create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $product->id,
                    'variance' => $variance,
                    'cost_price_at_posting' => $cost,
                    'reason_id' => $reasonName === null ? null : OpnameReason::query()->where('name', $reasonName)->value('id'),
                    ...($item[4] ?? []),
                ]);
            }

            return $opname;
        });
    }

    /**
     * What the tests read, all posted in the period: a count on the 5th (Paku -10 at 1.000 and Baut -5 at 2.000 as damaged
     * with 2 to dead stock, 1 to damaged goods and 2 back on the shelf, Cat +3 at 5.000 found by checking, Semen -4 with no price,
     * Lem even), another on the 10th (Paku -2), a count that came out even on the 12th, and an older count made for the reason
     * Hilang on the 8th (Obeng -1 at 3.000, no reason of its own). And left out: opening stock, an arrival and an inspection of
     * returns with variances, a count posted before the period and one still in review.
     */
    private function postTheCounts(): void
    {
        [$nails, $bolts, $paint, $cement, $glue, $screwdriver] = array_map(fn (string $name): Product => $this->product($name), ['Paku', 'Baut', 'Cat', 'Semen', 'Lem', 'Obeng']);

        $this->postedCount('2026-11-05 10:00:00', [
            [$nails, '-10', '1000', 'Hilang'],
            [$bolts, '-5', '2000', 'Rusak', ['dead_stock_quantity' => '2', 'damaged_goods_quantity' => '1', 'full_price_quantity' => '2']],
            [$paint, '3', '5000', 'Cek stok'],
            [$cement, '-4', '0', 'Hilang'],
            [$glue, '0', '1000', 'Cek stok'],
        ]);
        $this->postedCount('2026-11-10 09:00:00', [[$nails, '-2', '1000', 'Hilang']]);
        $this->postedCount('2026-11-12 09:00:00', [[$glue, '0', '1000', null]]);
        $this->postedCount('2026-11-08 09:00:00', [[$screwdriver, '-1', '3000', null]], StockOpnameReason::Lost);

        $this->postedCount('2026-11-06 09:00:00', [[$nails, '100', '100', null]], StockOpnameReason::OpeningBalance);
        $this->postedCount('2026-11-06 10:00:00', [[$nails, '50', '100', null]], StockOpnameReason::StockIncrease);
        $this->postedCount('2026-11-06 11:00:00', [[$nails, '-7', '100', null]], StockOpnameReason::ReturnsCheck);
        $this->postedCount('2026-10-20 09:00:00', [[$nails, '-9', '1000', 'Hilang']]);
        $this->postedCount('2026-11-07 09:00:00', [[$nails, '-8', '1000', 'Hilang']], status: StockOpnameStatus::Review);
    }

    /**
     * @param  callable(Assert): mixed  $assertions  Run once the deferred parts have loaded.
     * @param  array<string, string|int>  $query
     */
    private function assertReport(callable $assertions, array $query = self::PERIOD): void
    {
        $this->actingAs($this->admin)->get($this->url($query))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Reports/StockVariances')
            ->loadDeferredProps(fn (Assert $reload) => $assertions($reload)));
    }

    public function test_only_users_who_may_see_reports_may_open_the_variance_tab(): void
    {
        $this->actingAs($this->cashier)->get($this->url())->assertForbidden();
    }

    public function test_every_part_loads_in_a_group_of_its_own_and_turning_the_list_asks_for_it_alone(): void
    {
        $this->postTheCounts();

        $response = $this->actingAs($this->admin)->get($this->url(self::PERIOD))->assertOk();

        $this->assertEquals(['summary' => ['summary'], 'reasons' => ['reasons'], 'products' => ['products'], 'opnames' => ['opnames']], $response->viewData('page')['deferredProps']);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('period', self::PERIOD)
            ->where('per_page_options', [10, 25, 50])
            ->where('is_period_cut_back', false)
            ->missing('summary')
            ->missing('opnames')
            ->reloadOnly('opnames', fn (Assert $reload) => $reload
                ->has('opnames.data', 4)
                ->missing('summary')
                ->missing('reasons')
                ->missing('products')));
    }

    public function test_the_summary_values_only_the_shortages_at_the_price_when_posted(): void
    {
        $this->postTheCounts();

        $this->assertReport(fn (Assert $report) => $report->where('summary', [
            'opnames_count' => 4,
            'shortage_items' => 5,
            'shortage_value' => '25000.00',
            'dead_stock_value' => '4000.00',
            'damaged_goods_value' => '2000.00',
            'full_price_value' => '4000.00',
            'unbooked_value' => '15000.00',
            'items_without_cost' => 1,
        ]));
    }

    public function test_only_counts_of_the_shelf_posted_in_the_period_are_variances(): void
    {
        $this->postTheCounts();

        $this->assertReport(fn (Assert $report) => $report
            ->where('opnames.total', 4)
            ->where('summary.shortage_items', 5)
            ->where('products', fn ($products) => ! collect($products)->contains(fn (array $product): bool => $product['shortage_value'] === '700.00' || $product['shortage_value'] === '8000.00')));
    }

    public function test_the_shortages_are_split_by_reason_an_older_count_uses_the_reason_of_its_count_and_a_surplus_has_none(): void
    {
        $this->postTheCounts();

        $this->assertReport(fn (Assert $report) => $report
            ->has('reasons', 2)
            ->where('reasons.0', ['label' => 'Hilang', 'items_count' => 4, 'shortage_value' => '15000.00', 'dead_stock_value' => '0.00', 'damaged_goods_value' => '0.00', 'full_price_value' => '0.00'])
            ->where('reasons.1', ['label' => 'Rusak', 'items_count' => 1, 'shortage_value' => '10000.00', 'dead_stock_value' => '4000.00', 'damaged_goods_value' => '2000.00', 'full_price_value' => '4000.00']));
    }

    public function test_the_products_that_lost_the_most_come_first_and_one_that_lost_nothing_is_left_out(): void
    {
        $this->postTheCounts();

        $this->assertReport(fn (Assert $report) => $report->where('products', fn ($products) => collect($products)->map(fn (array $product): array => [$product['name'], $product['shortage_value']])->all() === [
            ['Paku', '12000.00'],
            ['Baut', '10000.00'],
            ['Obeng', '3000.00'],
        ]));
    }

    public function test_the_counts_are_listed_latest_first_with_what_their_shortages_were_worth_and_an_even_one_too(): void
    {
        $this->postTheCounts();

        $this->assertReport(fn (Assert $report) => $report
            ->where('opnames.total', 4)
            ->where('opnames.data', fn ($opnames) => collect($opnames)->map(fn (array $opname): array => [$opname['posted_on'], $opname['reason'], $opname['warehouse'], $opname['items_count'], $opname['shortage_value']])->all() === [
                ['2026-11-12', 'Opname Rutin', 'Main Warehouse', 0, '0.00'],
                ['2026-11-10', 'Opname Rutin', 'Main Warehouse', 1, '2000.00'],
                ['2026-11-08', 'Hilang', 'Main Warehouse', 1, '3000.00'],
                ['2026-11-05', 'Opname Rutin', 'Main Warehouse', 3, '20000.00'],
            ])
            ->where('opnames.data.0.url', fn (string $url) => str_contains($url, '/stock-opnames/')));
    }

    public function test_the_period_can_be_narrowed_to_leave_out_counts_posted_outside_it(): void
    {
        $this->postTheCounts();

        $this->assertReport(fn (Assert $report) => $report
            ->where('summary.opnames_count', 1)
            ->where('summary.shortage_value', '2000.00')
            ->where('opnames.total', 1)
            ->where('products', fn ($products) => collect($products)->pluck('name')->all() === ['Paku']), ['from' => '2026-11-10', 'to' => '2026-11-10']);
    }

    public function test_the_list_of_counts_is_paged_on_the_server_ten_at_a_time(): void
    {
        foreach (range(1, 12) as $day) {
            $this->postedCount('2026-11-'.str_pad((string) ($day + 2), 2, '0', STR_PAD_LEFT).' 09:00:00');
        }

        $this->assertReport(fn (Assert $report) => $report
            ->has('opnames.data', 10)
            ->where('opnames.total', 12)
            ->where('opnames.last_page', 2)
            ->where('opnames.per_page', 10), self::PERIOD);

        $this->assertReport(fn (Assert $report) => $report
            ->has('opnames.data', 2)
            ->where('opnames.current_page', 2)
            ->where('opnames.from', 11), [...self::PERIOD, 'opnames_page' => 99]);

        foreach (['7', '0', 'abc'] as $notAnOption) {
            $this->assertReport(fn (Assert $report) => $report->where('opnames.per_page', 10)->where('opnames.current_page', 1), [...self::PERIOD, 'opnames_per_page' => $notAnOption, 'opnames_page' => 'abc']);
        }
    }

    public function test_a_period_without_counts_has_a_report_of_zeroes(): void
    {
        $this->assertReport(fn (Assert $report) => $report
            ->where('summary.opnames_count', 0)
            ->where('summary.shortage_items', 0)
            ->where('summary.shortage_value', '0.00')
            ->where('summary.unbooked_value', '0.00')
            ->where('reasons', [])
            ->where('products', [])
            ->where('opnames.total', 0)
            ->where('opnames.data', []));
    }

    public function test_the_tab_covers_this_month_without_dates_and_says_when_it_cut_a_long_period(): void
    {
        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('period.from', today()->startOfMonth()->toDateString())
            ->where('is_period_cut_back', false));

        $this->actingAs($this->admin)->get($this->url(['from' => '2020-01-01', 'to' => today()->toDateString()]))->assertInertia(fn (Assert $page) => $page
            ->where('is_period_cut_back', true)
            ->where('period.from', today()->subDays(365)->toDateString()));
    }
}
