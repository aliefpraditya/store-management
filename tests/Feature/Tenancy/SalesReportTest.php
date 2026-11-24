<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DeliveryNoteStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleReturnStatus;
use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\CashShift;
use App\Models\Category;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\Stock;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Store;
use App\Models\User;
use App\Services\CodSaleService;
use App\Services\DailyClosingService;
use App\Services\SaleReturnService;
use App\Services\SalesDetailSheetRows;
use App\Services\SaleService;
use App\Services\SalesReportService;
use App\Services\SaleVoidService;
use App\Services\StoreDashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_sales_report';

    private Store $store;

    private User $admin;

    private User $cashier;

    private Product $brush;

    private CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_sales_report', 'receipt_prefix' => 'TSR', 'name' => 'Toko Uji', 'address' => 'Jl. Uji 1']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
        $this->brush = $this->sellableProduct('Kuas', '100');
        $this->shift = $this->openShiftFor($this->cashier);
    }

    /**
     * The report of the last seven days, today the last one, unless the query says otherwise: the tests count days
     * from there (`summary.days.6` is today).
     */
    private function url(array $query = [], string $name = 'sales'): string
    {
        return $this->urlWithoutPeriod(['from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString(), ...$query], $name);
    }

    private function urlWithoutPeriod(array $query = [], string $name = 'sales'): string
    {
        return route("tenant.reports.{$name}", ['tenant' => $this->store->code, ...$query]);
    }

    /**
     * @param  list<array{method: string, amount: int}>  $payments
     */
    private function sell(int $pieces, array $payments, ?int $daysAgo = null, bool $isCod = false): Sale
    {
        $sale = $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($this->brush, 'PCS')->id, 'quantity' => $pieces]],
            'payments' => $payments,
            ...($isCod ? ['delivery_note' => ['recipient_name' => 'Pak Budi', 'recipient_address' => 'Jl. Mawar 5']] : []),
        ]));

        if ($daysAgo !== null) {
            $this->store->run(fn () => Sale::query()->whereKey($sale->id)->update(['business_date' => today()->subDays($daysAgo), 'sold_at' => now()->subDays($daysAgo)]));
        }

        return $sale;
    }

    /**
     * Today: five brushes for cash (50.000) of which two come back for a cash refund, and two on COD (20.000).
     * Yesterday: three brushes by transfer (30.000). A COD sale that was voided stays out of everything. A brush
     * costs 6.000 and sells for 10.000.
     */
    private function sellInAWeek(): void
    {
        $cashSale = $this->sell(5, [['method' => 'cash', 'amount' => 50000]]);
        $this->sell(3, [['method' => 'transfer', 'amount' => 30000]], 1);
        $this->sell(2, [['method' => 'cod', 'amount' => 20000]], isCod: true);
        $voided = $this->sell(1, [['method' => 'cod', 'amount' => 10000]], isCod: true);
        $this->store->run(fn () => Sale::query()->whereKey($voided->id)->update(['status' => 'voided']));

        $this->store->run(fn () => app(SaleReturnService::class)->createApproved($cashSale, $this->shift, $this->admin, [
            'client_uuid' => (string) Str::uuid(),
            'refund_method' => 'cash',
            'items' => [['sale_item_id' => $cashSale->items[0]->id, 'quantity' => 2, 'disposition' => 'resellable']],
        ]));
    }

    /**
     * An opname already posted that sent units to dead stock, valued at the cost it was posted with (6.000 a brush).
     */
    private function postedOpname(StockOpnameReason $reason, string $deadStock, string $variance, ?CarbonImmutable $postedAt = null): void
    {
        $this->store->run(function () use ($reason, $deadStock, $variance, $postedAt): void {
            $opname = StockOpname::factory()->create([
                'warehouse_id' => $this->mainWarehouse()->id,
                'reason_code' => $reason,
                'scope' => StockOpnameScope::Selected,
                'status' => StockOpnameStatus::Posted,
                'snapshot_at' => now(),
                'posted_at' => $postedAt ?? now(),
                'created_by' => $this->admin->id,
            ]);
            StockOpnameItem::factory()->create([
                'stock_opname_id' => $opname->id,
                'product_id' => $this->brush->id,
                'snapshot_quantity' => '10',
                'counted_quantity' => '10',
                'dead_stock_quantity' => $deadStock,
                'cost_price_at_posting' => '6000',
                'variance' => $variance,
            ]);
        });
    }

    public function test_dead_stock_found_by_an_inspection_or_a_damaged_count_is_a_loss_of_its_cost_in_the_profit(): void
    {
        $this->sell(5, [['method' => 'cash', 'amount' => 50000]]);
        $this->postedOpname(StockOpnameReason::ReturnsCheck, '2', '0');
        $this->postedOpname(StockOpnameReason::Routine, '1', '-3');
        $this->postedOpname(StockOpnameReason::Routine, '4', '0');
        $this->postedOpname(StockOpnameReason::ReturnsCheck, '9', '0', CarbonImmutable::now()->subDays(10));

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.totals.dead_stock_loss', '18000.00')
                ->where('summary.totals.gross_profit', '2000.00')
                ->where('summary.days.6.dead_stock_loss', '18000.00')
                ->where('summary.days.6.gross_profit', '2000.00')
                ->where('summary.days.5.dead_stock_loss', '0.00')
                ->where('products.data.0.cost', '48000.00')
                ->where('products.data.0.profit', '2000.00')));
    }

    public function test_a_product_with_only_dead_stock_in_the_period_is_listed_with_its_loss(): void
    {
        $this->postedOpname(StockOpnameReason::ReturnsCheck, '2', '0');

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.totals.gross_profit', '-12000.00')
                ->where('products.data.0.name', 'Kuas')
                ->where('products.data.0.revenue', '0.00')
                ->where('products.data.0.cost', '12000.00')
                ->where('products.data.0.profit', '-12000.00')));
    }

    public function test_only_admins_may_see_the_report(): void
    {
        $this->actingAs($this->cashier)->get($this->url())->assertForbidden();
        $this->actingAs($this->cashier)->get($this->url(name: 'sales.sheet'))->assertForbidden();
    }

    public function test_the_report_covers_the_whole_of_this_month_when_no_dates_are_given(): void
    {
        $this->actingAs($this->admin)->get($this->urlWithoutPeriod())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Reports/Sales')
            ->where('period.from', today()->startOfMonth()->toDateString())
            ->where('period.to', today()->endOfMonth()->toDateString())
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('summary.days', today()->daysInMonth)
                ->where('summary.totals.net_total', '0.00')
                ->where('summary.totals.receipt_count', 0)
                ->where('products.data', [])
                ->where('products.total', 0)));
    }

    public function test_the_totals_are_net_of_returns_and_leave_out_void_receipts(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.totals', ['gross_total' => '100000.00', 'returns_total' => '20000.00', 'net_total' => '80000.00', 'receipt_count' => 3, 'dead_stock_loss' => '0.00', 'gross_profit' => '32000.00', 'voids_count' => 0, 'voids_total' => '0.00', 'margin_percent' => '40.0'])));
    }

    public function test_each_day_has_its_own_figures_and_days_without_sales_are_listed(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.days.6', ['date' => today()->toDateString(), 'gross_total' => '70000.00', 'returns_total' => '20000.00', 'net_total' => '50000.00', 'receipt_count' => 2, 'dead_stock_loss' => '0.00', 'gross_profit' => '20000.00', 'voids_count' => 0, 'voids_total' => '0.00'])
                ->where('summary.days.5', ['date' => today()->subDay()->toDateString(), 'gross_total' => '30000.00', 'returns_total' => '0.00', 'net_total' => '30000.00', 'receipt_count' => 1, 'dead_stock_loss' => '0.00', 'gross_profit' => '12000.00', 'voids_count' => 0, 'voids_total' => '0.00'])
                ->where('summary.days.0.net_total', '0.00')));
    }

    public function test_the_period_can_be_narrowed(): void
    {
        $this->sellInAWeek();
        $today = today()->toDateString();

        $this->actingAs($this->admin)->get($this->url(['from' => $today, 'to' => $today]))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('summary.days', 1)
                ->where('summary.totals.gross_total', '70000.00')
                ->where('summary.totals.receipt_count', 2)));
    }

    public function test_a_start_after_the_end_is_swapped_and_a_huge_period_is_cut_back(): void
    {
        $this->actingAs($this->admin)->get($this->url(['from' => today()->toDateString(), 'to' => today()->subDays(2)->toDateString()]))->assertInertia(fn (Assert $page) => $page
            ->where('period.from', today()->subDays(2)->toDateString())
            ->where('period.to', today()->toDateString())
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('summary.days', 3)));

        $this->actingAs($this->admin)->get($this->url(['from' => '2000-01-01', 'to' => today()->toDateString()]))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('summary.days', 366)));
    }

    public function test_what_came_in_is_split_by_method_with_cod_apart_and_split_by_delivery(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('payments.methods', [
                    ['method' => 'cash', 'label' => 'Tunai', 'amount' => '50000.00'],
                    ['method' => 'transfer', 'label' => 'Transfer', 'amount' => '30000.00'],
                    ['method' => 'qris', 'label' => 'QRIS', 'amount' => '0.00'],
                    ['method' => 'edc', 'label' => 'EDC', 'amount' => '0.00'],
                    ['method' => 'cod_delivered', 'label' => 'COD (sudah diantar)', 'amount' => '0.00'],
                    ['method' => 'cod_undelivered', 'label' => 'COD (belum diantar)', 'amount' => '20000.00'],
                ])));
    }

    public function test_cod_of_a_receipt_whose_goods_were_delivered_on_a_combined_note_counts_as_delivered(): void
    {
        $owner = $this->sell(2, [['method' => 'cod', 'amount' => 20000]], isCod: true);
        $other = $this->sell(3, [['method' => 'cod', 'amount' => 30000]], isCod: true);

        $this->store->run(function () use ($owner, $other): void {
            DeliveryNote::query()->where('sale_id', $other->id)->get()->each->delete();

            $combinedNote = DeliveryNote::query()->where('sale_id', $owner->id)->sole();
            $combinedNote->sales()->attach($other->id);
            $combinedNote->forceFill(['status' => DeliveryNoteStatus::Delivered])->save();
        });

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('payments.methods.4', ['method' => 'cod_delivered', 'label' => 'COD (sudah diantar)', 'amount' => '50000.00'])
                ->where('payments.methods.5', ['method' => 'cod_undelivered', 'label' => 'COD (belum diantar)', 'amount' => '0.00'])));

        $this->assertSame(2, $this->store->run(fn (): int => app(CodSaleService::class)->totalsOfShift($this->shift)['delivered']['count']));
    }

    public function test_cash_is_counted_after_the_change_given(): void
    {
        $this->sell(1, [['method' => 'cash', 'amount' => 20000]]);

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('payments.methods.0.amount', '10000.00')));
    }

    public function test_neither_the_screen_nor_the_print_sheet_splits_the_sales_by_cashier(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('payments.methods')
                ->missing('payments.cashiers')));

        $this->actingAs($this->admin)->get($this->url(name: 'sales.sheet'))->assertInertia(fn (Assert $page) => $page
            ->has('report.methods')
            ->missing('report.cashiers'));
    }

    public function test_the_totals_say_the_margin_and_nothing_sold_has_none(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('summary.totals.margin_percent', '40.0')));

        $this->actingAs($this->admin)->get($this->url(['from' => today()->subDays(30)->toDateString(), 'to' => today()->subDays(20)->toDateString()]))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.totals.margin_percent', '0.0')
                ->where('summary.receipts', ['median_total' => '0.00', 'median_lines' => '0.0', 'average_total' => '0.00'])));
    }

    /**
     * One receipt with a line for each of the products given, a piece of each at 10.000.
     *
     * @param  list<Product>  $products
     */
    private function sellOneOfEach(array $products): void
    {
        $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => array_map(fn (Product $product): array => ['product_unit_id' => $this->unitOf($product, 'PCS')->id, 'quantity' => 1], $products),
            'payments' => [['method' => 'cash', 'amount' => count($products) * 10000]],
        ]));
    }

    public function test_a_typical_receipt_is_the_median_so_one_big_receipt_does_not_pull_it_away(): void
    {
        $pencil = $this->sellableProduct('Pensil', '50');
        $pen = $this->sellableProduct('Pulpen', '50');
        $ruler = $this->sellableProduct('Penggaris', '50');

        $this->sellOneOfEach([$pencil]);
        $this->sellOneOfEach([$pen]);
        $this->sellOneOfEach([$pencil, $pen, $ruler]);

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.totals.gross_total', '50000.00')
                ->where('summary.receipts', ['median_total' => '10000.00', 'median_lines' => '1.0', 'average_total' => '16666.67'])));
    }

    public function test_with_an_even_number_of_receipts_the_median_is_the_mean_of_the_two_in_the_middle(): void
    {
        $pencil = $this->sellableProduct('Pensil', '50');
        $pen = $this->sellableProduct('Pulpen', '50');

        $this->sellOneOfEach([$pencil]);
        $this->sellOneOfEach([$pencil, $pen]);

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('summary.receipts', ['median_total' => '15000.00', 'median_lines' => '1.5', 'average_total' => '15000.00'])));
    }

    public function test_the_median_receipt_leaves_out_voided_receipts_and_those_outside_the_period(): void
    {
        $pencil = $this->sellableProduct('Pensil', '50');

        $this->sellOneOfEach([$pencil]);
        $this->sell(9, [['method' => 'cash', 'amount' => 90000]], 20);
        $voided = $this->sell(8, [['method' => 'cash', 'amount' => 80000]]);
        $this->store->run(fn () => Sale::query()->whereKey($voided->id)->update(['status' => 'voided']));

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('summary.receipts', ['median_total' => '10000.00', 'median_lines' => '1.0', 'average_total' => '10000.00'])));
    }

    public function test_the_period_is_compared_with_the_one_of_the_same_length_just_before_it(): void
    {
        $this->sell(3, [['method' => 'cash', 'amount' => 30000]]);
        $this->sell(2, [['method' => 'cash', 'amount' => 20000]], 8);

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->missing('comparison')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('comparison.from', today()->subDays(13)->toDateString())
                ->where('comparison.to', today()->subDays(7)->toDateString())
                ->where('comparison.totals.net_total', '20000.00')
                ->where('comparison.totals.receipt_count', 1)
                ->where('comparison.change_percent', ['net_total' => '50.0', 'receipt_count' => '0.0', 'gross_profit' => '50.0'])));
    }

    public function test_a_fall_is_a_negative_percent_and_a_period_after_nothing_has_no_percent(): void
    {
        $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);
        $this->sell(4, [['method' => 'cash', 'amount' => 40000]], 8);

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('comparison.change_percent.net_total', '-75.0')));

        $this->actingAs($this->admin)->get($this->url(['from' => today()->subDays(30)->toDateString(), 'to' => today()->subDays(24)->toDateString()]))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('comparison.change_percent', ['net_total' => null, 'receipt_count' => null, 'gross_profit' => null])));
    }

    public function test_a_period_that_is_not_over_yet_is_compared_by_the_days_that_have_come(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 10:00'));
        $service = app(SalesReportService::class);

        $comparison = $this->store->run(fn (): array => $service->comparison(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30')));

        $this->assertSame(['2026-10-13', '2026-10-31'], [$comparison['from'], $comparison['to']]);

        $past = $this->store->run(fn (): array => $service->comparison(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31')));

        $this->assertSame(['2026-08-31', '2026-09-30'], [$past['from'], $past['to']]);
    }

    /**
     * Stationery: four pencils of the brand Faber (40.000) and one pen without a brand (10.000). Paint: two cans
     * without a brand (20.000). Each piece sells for 10.000 at a cost of 6.000, so every group has a margin of 40%.
     */
    private function sellInCategoriesAndBrands(): void
    {
        $stationery = $this->store->run(fn (): Category => Category::factory()->create(['name' => 'Alat Tulis']));
        $paint = $this->store->run(fn (): Category => Category::factory()->create(['name' => 'Cat']));
        $faber = $this->store->run(fn (): Brand => Brand::factory()->create(['name' => 'Faber']));

        foreach ([
            [$this->sellableProduct('Pensil', '50', ['category_id' => $stationery->id, 'brand_id' => $faber->id]), 4],
            [$this->sellableProduct('Pulpen', '50', ['category_id' => $stationery->id, 'brand_id' => null]), 1],
            [$this->sellableProduct('Cat Tembok', '50', ['category_id' => $paint->id, 'brand_id' => null]), 2],
        ] as [$product, $pieces]) {
            $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, [
                'client_uuid' => (string) Str::uuid(),
                'items' => [['product_unit_id' => $this->unitOf($product, 'PCS')->id, 'quantity' => $pieces]],
                'payments' => [['method' => 'cash', 'amount' => $pieces * 10000]],
            ]));
        }
    }

    public function test_the_sales_are_grouped_by_category_with_the_biggest_first_to_begin_with(): void
    {
        $this->sellInCategoriesAndBrands();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->missing('groups')
            ->where('group_per_page_options', [5, 10])
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('groups.grouping', 'category')
                ->where('groups.sort', 'revenue')
                ->where('groups.direction', 'desc')
                ->where('groups.total', 2)
                ->where('groups.per_page', 5)
                ->where('groups.data.0.name', 'Alat Tulis')
                ->where('groups.data.0.product_count', 2)
                ->where('groups.data.0.revenue', '50000.00')
                ->where('groups.data.0.cost', '30000.00')
                ->where('groups.data.0.profit', '20000.00')
                ->where('groups.data.0.margin_percent', '40.0')
                ->where('groups.data.0.share_percent', '71.4')
                ->where('groups.revenue_total', '70000.00')
                ->where('groups.data.1.name', 'Cat')
                ->where('groups.data.1.share_percent', '28.6')));
    }

    public function test_the_sales_can_be_grouped_by_brand_with_the_products_of_no_brand_together(): void
    {
        $this->sellInCategoriesAndBrands();

        $this->actingAs($this->admin)->get($this->url(['groups_by' => 'brand']))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('groups.grouping', 'brand')
                ->where('groups.total', 2)
                ->where('groups.data.0.name', 'Faber')
                ->where('groups.data.0.revenue', '40000.00')
                ->where('groups.data.1.id', null)
                ->where('groups.data.1.name', '(Tanpa merek)')
                ->where('groups.data.1.product_count', 2)
                ->where('groups.data.1.revenue', '30000.00')));
    }

    public function test_the_groups_can_be_ordered_by_name_or_by_a_figure_either_way(): void
    {
        $this->sellInCategoriesAndBrands();

        $orders = [
            ['groups_sort' => 'name', 'expected' => ['Alat Tulis', 'Cat']],
            ['groups_sort' => 'name', 'groups_direction' => 'desc', 'expected' => ['Cat', 'Alat Tulis']],
            ['groups_sort' => 'profit', 'groups_direction' => 'asc', 'expected' => ['Cat', 'Alat Tulis']],
            ['groups_sort' => 'profit', 'expected' => ['Alat Tulis', 'Cat']],
            ['groups_sort' => 'margin', 'expected' => ['Alat Tulis', 'Cat']],
            ['groups_sort' => 'nonsense', 'groups_direction' => 'sideways', 'expected' => ['Alat Tulis', 'Cat']],
        ];

        foreach ($orders as $order) {
            $expected = $order['expected'];
            unset($order['expected']);

            $this->actingAs($this->admin)->get($this->url($order))->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('groups.data', fn ($groups): bool => collect($groups)->pluck('name')->all() === $expected)));
        }
    }

    public function test_the_groups_are_paged_on_the_server_five_or_ten_at_a_time(): void
    {
        $this->sellDifferentProducts(12);

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('groups.data', 5)
                ->where('groups.total', 12)
                ->where('groups.last_page', 3)
                ->where('groups.from', 1)
                ->where('groups.to', 5)
                ->where('groups.revenue_total', '120000.00')));

        $this->actingAs($this->admin)->get($this->url(['groups_per_page' => 10, 'groups_page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('groups.data', 2)
                ->where('groups.per_page', 10)
                ->where('groups.current_page', 2)
                ->where('groups.from', 11)
                ->where('groups.to', 12)));

        foreach (['7', '0', 'abc'] as $notAnOption) {
            $this->actingAs($this->admin)->get($this->url(['groups_per_page' => $notAnOption, 'groups_page' => 99]))->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->where('groups.per_page', 5)->where('groups.current_page', 3)));
        }
    }

    /**
     * Four completed receipts of five brushes (50.000 each), each with one brush returned (10.000, a cost of 6.000).
     */
    private function sellAndReturn(): void
    {
        foreach (range(1, 4) as $unused) {
            $sale = $this->sell(5, [['method' => 'cash', 'amount' => 50000]]);

            $this->store->run(fn () => app(SaleReturnService::class)->createApproved($sale, $this->shift, $this->admin, [
                'client_uuid' => (string) Str::uuid(),
                'refund_method' => 'cash',
                'items' => [['sale_item_id' => $sale->items[0]->id, 'quantity' => 1, 'disposition' => 'resellable']],
            ]));
        }
    }

    /**
     * An inspection of the returns warehouse: for each product how many units went back on the shelf, to damaged goods
     * and to dead stock, at the cost the inspection was posted with.
     *
     * @param  list<array{0: Product, 1: string, 2: string, 3: string, 4: string}>  $items  Product, full price, damaged goods, dead stock, cost.
     */
    private function inspectReturns(array $items, ?CarbonImmutable $postedAt = null, StockOpnameStatus $status = StockOpnameStatus::Posted, StockOpnameReason $reason = StockOpnameReason::ReturnsCheck): void
    {
        $this->store->run(function () use ($items, $postedAt, $status, $reason): void {
            $opname = StockOpname::factory()->create([
                'warehouse_id' => $this->returnsWarehouse()->id,
                'reason_code' => $reason,
                'scope' => StockOpnameScope::Selected,
                'status' => $status,
                'snapshot_at' => now(),
                'posted_at' => $postedAt ?? now(),
                'created_by' => $this->admin->id,
            ]);

            foreach ($items as [$product, $fullPrice, $damaged, $deadStock, $cost]) {
                StockOpnameItem::factory()->create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $product->id,
                    'full_price_quantity' => $fullPrice,
                    'damaged_goods_quantity' => $damaged,
                    'dead_stock_quantity' => $deadStock,
                    'cost_price_at_posting' => $cost,
                    'variance' => '0',
                ]);
            }
        });
    }

    public function test_the_units_an_inspection_decided_are_split_into_sellable_damaged_and_dead_stock_at_cost(): void
    {
        $paint = $this->sellableProduct('Cat', '10');

        $this->inspectReturns([[$this->brush, '3', '1', '2', '6000'], [$paint, '0', '0', '5', '2000']]);

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->missing('return_details')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('return_details.outcomes', [
                    ['outcome' => 'resellable', 'label' => 'Bisa dijual lagi', 'products_count' => 1, 'cost_total' => '18000.00', 'share_percent' => '39.1'],
                    ['outcome' => 'damaged_goods', 'label' => 'Barang rusak (DG)', 'products_count' => 1, 'cost_total' => '6000.00', 'share_percent' => '13.0'],
                    ['outcome' => 'dead_stock', 'label' => 'Dead stock', 'products_count' => 2, 'cost_total' => '22000.00', 'share_percent' => '47.8'],
                ])));
    }

    public function test_only_inspections_of_returns_posted_in_the_period_count(): void
    {
        $this->inspectReturns([[$this->brush, '1', '0', '0', '6000']]);
        $this->inspectReturns([[$this->brush, '9', '9', '9', '6000']], CarbonImmutable::now()->subDays(10));
        $this->inspectReturns([[$this->brush, '9', '9', '9', '6000']], status: StockOpnameStatus::Counting);
        $this->inspectReturns([[$this->brush, '9', '9', '9', '6000']], reason: StockOpnameReason::Routine);

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('return_details.outcomes.0.cost_total', '6000.00')
                ->where('return_details.outcomes.1.cost_total', '0.00')
                ->where('return_details.outcomes.2.cost_total', '0.00')
                ->where('return_details.outcomes.0.share_percent', '100.0')));
    }

    public function test_what_came_back_in_the_period_is_valued_at_cost(): void
    {
        $this->sellAndReturn();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('return_details.returned', ['products_count' => 1, 'cost_total' => '24000.00'])));
    }

    public function test_what_waits_in_the_returns_warehouse_is_counted_whatever_the_period(): void
    {
        $paint = $this->sellableProduct('Cat', '10', ['cost_price' => 2000]);
        $this->store->run(function () use ($paint): void {
            Stock::query()->updateOrInsert(['product_id' => $this->brush->id, 'warehouse_id' => $this->returnsWarehouse()->id], ['quantity' => '5']);
            Stock::query()->updateOrInsert(['product_id' => $paint->id, 'warehouse_id' => $this->returnsWarehouse()->id], ['quantity' => '3']);
            Stock::query()->updateOrInsert(['product_id' => $this->brush->id, 'warehouse_id' => $this->mainWarehouse()->id], ['quantity' => '100']);
        });

        $this->actingAs($this->admin)->get($this->url(['from' => today()->subDays(30)->toDateString(), 'to' => today()->subDays(20)->toDateString()]))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('return_details.waiting', ['products_count' => 2, 'cost_total' => '36000.00'])));
    }

    public function test_the_products_returned_most_often_come_with_their_share_of_what_was_sold(): void
    {
        $this->sellAndReturn();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('return_details.products', [[
                    'product_id' => $this->brush->id,
                    'name' => 'Kuas',
                    'unit' => 'PCS',
                    'returns_count' => 4,
                    'quantity' => '4.000',
                    'total' => '40000.00',
                    'rate_percent' => '20.0',
                ]])));
    }

    public function test_a_product_returned_from_an_earlier_period_has_no_share(): void
    {
        $old = $this->sell(3, [['method' => 'cash', 'amount' => 30000]], 40);
        $this->store->run(fn () => app(SaleReturnService::class)->createApproved($old, $this->shift, $this->admin, [
            'client_uuid' => (string) Str::uuid(),
            'refund_method' => 'cash',
            'items' => [['sale_item_id' => $old->items[0]->id, 'quantity' => 1, 'disposition' => 'resellable']],
        ]));

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('return_details.products.0.rate_percent', null)));
    }

    public function test_the_return_details_leave_out_the_reasons_and_who_handled_the_returns(): void
    {
        $this->sellAndReturn();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('return_details.outcomes')
                ->has('return_details.products')
                ->missing('return_details.reasons')
                ->missing('return_details.cashiers')));
    }

    public function test_nothing_returned_leaves_the_figures_at_nothing(): void
    {
        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('return_details.returned', ['products_count' => 0, 'cost_total' => '0.00'])
                ->where('return_details.waiting', ['products_count' => 0, 'cost_total' => '0.00'])
                ->where('return_details.outcomes.0.share_percent', '0.0')
                ->where('return_details.products', [])));
    }

    public function test_the_groups_add_up_to_the_products(): void
    {
        $this->sellInCategoriesAndBrands();
        $this->sell(2, [['method' => 'cash', 'amount' => 20000]]);

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('groups.data', fn ($groups): bool => collect($groups)->reduce(fn (string $sum, $group): string => bcadd($sum, $group['revenue'], 2), '0') === '90000.00')
                ->where('products.total', 4)));
    }

    public function test_the_profit_per_product_is_after_returns_with_its_margin(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('products.data', [[
                    'product_id' => $this->brush->id,
                    'name' => 'Kuas',
                    'unit' => 'PCS',
                    'quantity' => '8.000',
                    'revenue' => '80000.00',
                    'cost' => '48000.00',
                    'profit' => '32000.00',
                    'margin_percent' => '40.0',
                ]])));
    }

    public function test_products_are_listed_best_profit_first(): void
    {
        $paint = $this->sellableProduct('Cat', '50', ['cost_price' => 1000]);
        $this->store->run(fn () => $paint->productUnits()->update(['cost_price' => 1000]));
        $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);
        $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($paint, 'PCS')->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10000]],
        ]));

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('products.data.0.name', 'Cat')
                ->where('products.data.1.name', 'Kuas')));
    }

    public function test_the_print_sheet_has_the_same_report_with_the_letterhead(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url(name: 'sales.sheet'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Print/SalesReport')
            ->where('report.store.name', 'Toko Uji')
            ->where('report.totals.net_total', '80000.00')
            ->has('report.days', 7));

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('urls.sheet', $this->url(['from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString()], 'sales.sheet')));

        $this->actingAs($this->admin)->get($this->urlWithoutPeriod())->assertInertia(fn (Assert $page) => $page
            ->where('urls.sheet', $this->urlWithoutPeriod(['from' => today()->startOfMonth()->toDateString(), 'to' => today()->endOfMonth()->toDateString()], 'sales.sheet')));
    }

    public function test_a_period_that_had_to_be_cut_back_says_so(): void
    {
        $this->actingAs($this->admin)->get($this->url(['from' => '2000-01-01', 'to' => today()->toDateString()]))->assertInertia(fn (Assert $page) => $page
            ->where('is_period_cut_back', true)
            ->where('max_days', 366)
            ->where('period.from', today()->subDays(365)->toDateString()));

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page->where('is_period_cut_back', false));
        $this->actingAs($this->admin)->get($this->url(['from' => today()->toDateString(), 'to' => today()->subDays(2)->toDateString()]))->assertInertia(fn (Assert $page) => $page->where('is_period_cut_back', false));
    }

    public function test_the_service_works_the_period_out_from_what_is_given(): void
    {
        $service = app(SalesReportService::class);
        $today = CarbonImmutable::today();

        $this->assertSame([$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString(), false], $this->periodDates($service->period(null, null)));
        $this->assertSame([$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString(), false], $this->periodDates($service->period($today->subDays(10), null)));
        $this->assertSame([$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString(), false], $this->periodDates($service->period(null, $today->subDays(10))));
        $this->assertSame([$today->subDays(2)->toDateString(), $today->toDateString(), false], $this->periodDates($service->period($today, $today->subDays(2))));
        $this->assertSame([$today->subDays(365)->toDateString(), $today->toDateString(), false], $this->periodDates($service->period($today->subDays(365), $today)));
        $this->assertSame([$today->subDays(365)->toDateString(), $today->toDateString(), true], $this->periodDates($service->period($today->subDays(400), $today)));
    }

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, is_cut_back: bool}  $period
     * @return array{0: string, 1: string, 2: bool}
     */
    private function periodDates(array $period): array
    {
        return [$period['from']->toDateString(), $period['to']->toDateString(), $period['is_cut_back']];
    }

    public function test_dates_that_are_not_dates_are_left_out_as_if_they_were_not_sent(): void
    {
        foreach (['from=abc', 'from=2026-02-30&to=nonsense', 'from[]=x&to[]=y', 'from=2026-01-01', 'to=2026-01-01', 'from=&to='] as $query) {
            $this->actingAs($this->admin)->get($this->urlWithoutPeriod().'?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('period.from', today()->startOfMonth()->toDateString())
                ->where('period.to', today()->endOfMonth()->toDateString()));
        }
    }

    public function test_voids_are_counted_on_the_day_they_were_done_and_leave_the_sales_and_the_methods(): void
    {
        $sale = $this->sell(10, [['method' => 'cash', 'amount' => 40000], ['method' => 'cod', 'amount' => 60000]], 1, isCod: true);
        $this->store->run(fn () => app(SaleVoidService::class)->void($sale, $this->cashier, 'Ditolak', null));

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.days.5.gross_total', '0.00')
                ->where('summary.days.5.voids_count', 0)
                ->where('summary.days.6.voids_count', 1)
                ->where('summary.days.6.voids_total', '100000.00')
                ->where('summary.totals.gross_total', '0.00')
                ->where('summary.totals.voids_count', 1)
                ->where('summary.totals.voids_total', '100000.00')
                ->where('payments.methods.0.amount', '0.00')));
    }

    public function test_the_methods_add_up_to_the_sales_of_the_period(): void
    {
        $this->sellInAWeek();
        $depositSale = $this->sell(2, [['method' => 'cash', 'amount' => 20000]]);
        $creditSale = $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);
        $this->store->run(function () use ($depositSale, $creditSale): void {
            SalePayment::query()->where('sale_id', $depositSale->id)->update(['method' => 'order_deposit']);
            SalePayment::query()->where('sale_id', $creditSale->id)->update(['method' => 'return_credit']);
        });

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.totals.gross_total', '130000.00')
                ->where('payments.methods', fn ($methods): bool => collect($methods)->reduce(fn (string $sum, $row): string => bcadd($sum, $row['amount'], 2), '0') === '130000.00')
                ->where('payments.methods.6', ['method' => 'order_deposit', 'label' => 'Uang muka pesanan', 'amount' => '20000.00'])
                ->where('payments.methods.7', ['method' => 'return_credit', 'label' => 'Kredit retur', 'amount' => '10000.00'])));
    }

    public function test_cod_is_split_by_whether_the_goods_were_delivered(): void
    {
        $delivered = $this->sell(2, [['method' => 'cod', 'amount' => 20000]], isCod: true);
        $this->sell(3, [['method' => 'cod', 'amount' => 30000]], isCod: true);
        $this->store->run(fn () => app(CodSaleService::class)->markDelivered($delivered));

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('payments.methods.4.amount', '20000.00')
                ->where('payments.methods.5.amount', '30000.00')));
    }

    public function test_a_return_takes_the_day_it_was_made_and_one_not_approved_is_not_counted(): void
    {
        $old = $this->sell(3, [['method' => 'cash', 'amount' => 30000]], 1);
        $this->store->run(function () use ($old): void {
            app(SaleReturnService::class)->createApproved($old, $this->shift, $this->admin, [
                'client_uuid' => (string) Str::uuid(),
                'refund_method' => 'cash',
                'items' => [['sale_item_id' => $old->items[0]->id, 'quantity' => 3, 'disposition' => 'resellable']],
            ]);
            SaleReturn::factory()->create(['sale_id' => $old->id, 'status' => SaleReturnStatus::Pending, 'credit_amount' => 5000, 'returned_at' => now()]);
            SaleReturn::factory()->create(['sale_id' => $old->id, 'status' => SaleReturnStatus::Rejected, 'credit_amount' => 7000, 'returned_at' => now()]);
        });

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.days.5.gross_total', '30000.00')
                ->where('summary.days.5.returns_total', '0.00')
                ->where('summary.days.6.gross_total', '0.00')
                ->where('summary.days.6.returns_total', '30000.00')
                ->where('summary.days.6.net_total', '-30000.00')));
    }

    public function test_a_return_after_midnight_in_a_shift_of_the_day_before_counts_on_the_day_of_the_shift_everywhere(): void
    {
        $sale = $this->sell(5, [['method' => 'cash', 'amount' => 50000]]);
        $shiftDay = today();

        $this->travelTo($shiftDay->copy()->addDay()->setTime(0, 30));
        $this->store->run(fn () => app(SaleReturnService::class)->createApproved($sale, $this->shift, $this->admin, [
            'client_uuid' => (string) Str::uuid(),
            'refund_method' => 'cash',
            'items' => [['sale_item_id' => $sale->items[0]->id, 'quantity' => 2, 'disposition' => 'resellable']],
        ]));

        $this->store->run(function () use ($shiftDay): void {
            $this->assertSame('20000.00', app(DailyClosingService::class)->summarize($shiftDay)['total_returns']);
            $this->assertSame('0.00', app(DailyClosingService::class)->summarize($shiftDay->copy()->addDay())['total_returns']);
            $this->assertSame('20000.00', app(StoreDashboardService::class)->summary($shiftDay)['sales']['returns_total']);
            $this->assertSame('0.00', app(StoreDashboardService::class)->summary($shiftDay->copy()->addDay())['sales']['returns_total']);
        });

        $this->actingAs($this->admin)->get($this->url(['from' => $shiftDay->toDateString(), 'to' => $shiftDay->copy()->addDay()->toDateString()]))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.days.0.returns_total', '20000.00')
                ->where('summary.days.0.net_total', '30000.00')
                ->where('summary.days.1.returns_total', '0.00')));
    }

    public function test_the_voids_of_the_report_follow_the_business_day_of_the_void(): void
    {
        $sale = $this->sell(1, [['method' => 'cod', 'amount' => 10000]], isCod: true);
        $this->store->run(fn () => Sale::query()->whereKey($sale->id)->update(['status' => 'voided', 'voided_at' => today()->addDay()->setTime(0, 30), 'void_business_date' => today()]));

        $this->actingAs($this->admin)->get($this->url(['from' => today()->toDateString(), 'to' => today()->addDay()->toDateString()]))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.days.0.voids_count', 1)
                ->where('summary.days.1.voids_count', 0)));
    }

    private function sellDifferentProducts(int $count): void
    {
        foreach (range(1, $count) as $number) {
            $product = $this->sellableProduct("Barang {$number}", '10');
            $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, [
                'client_uuid' => (string) Str::uuid(),
                'items' => [['product_unit_id' => $this->unitOf($product, 'PCS')->id, 'quantity' => 1]],
                'payments' => [['method' => 'cash', 'amount' => 10000]],
            ]));
        }
    }

    public function test_the_print_sheet_lists_only_the_best_thirty_products_and_the_count_says_how_many_there_are(): void
    {
        $this->sellDifferentProducts(31);

        $this->actingAs($this->admin)->get($this->url(name: 'sales.sheet'))->assertInertia(fn (Assert $page) => $page
            ->has('report.products', 30)
            ->where('report.product_count', 31)
            ->where('report.product_limit', 30)
            ->where('report.products_without_cost', 0));
    }

    public function test_the_screen_pages_the_products_on_the_server_seven_at_a_time_to_begin_with(): void
    {
        $this->sellDifferentProducts(16);

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('per_page_options', [7, 15, 30])
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('products.data', 7)
                ->where('products.total', 16)
                ->where('products.per_page', 7)
                ->where('products.current_page', 1)
                ->where('products.last_page', 3)
                ->where('products.from', 1)
                ->where('products.to', 7)));

        $this->actingAs($this->admin)->get($this->url(['products_page' => 3]))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('products.data', 2)
                ->where('products.current_page', 3)
                ->where('products.from', 15)
                ->where('products.to', 16)));
    }

    public function test_the_products_page_size_is_one_of_the_options_and_anything_else_is_the_first_option(): void
    {
        $this->sellDifferentProducts(16);

        $this->actingAs($this->admin)->get($this->url(['products_per_page' => 15]))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('products.data', 15)
                ->where('products.per_page', 15)
                ->where('products.last_page', 2)));

        foreach (['30', '5', '100', 'abc', '0'] as $perPage) {
            $this->actingAs($this->admin)->get($this->url(['products_per_page' => $perPage]))->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->where('products.per_page', $perPage === '30' ? 30 : 7)));
        }
    }

    public function test_a_page_past_the_last_gives_the_last_and_one_that_is_not_a_number_gives_the_first(): void
    {
        $this->sellDifferentProducts(9);

        $this->actingAs($this->admin)->get($this->url(['products_page' => 99]))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('products.current_page', 2)->has('products.data', 2)));

        foreach (['abc', '0', '-3'] as $notAPage) {
            $this->actingAs($this->admin)->get($this->url(['products_page' => $notAPage]))->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->where('products.current_page', 1)));
        }
    }

    public function test_the_parts_of_the_screen_are_deferred_and_turning_a_products_page_asks_for_that_part_only(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->has('period')
            ->missing('summary')
            ->missing('comparison')
            ->missing('payments')
            ->missing('products')
            ->missing('groups')
            ->missing('return_details')
            ->reloadOnly('products', fn (Assert $reload) => $reload
                ->has('products.data', 1)
                ->missing('summary')
                ->missing('payments')
                ->missing('groups')
                ->missing('return_details'))
            ->reloadOnly('groups', fn (Assert $reload) => $reload
                ->has('groups.data', 1)
                ->missing('summary')
                ->missing('products')
                ->missing('return_details'))
            ->reloadOnly('return_details', fn (Assert $reload) => $reload
                ->has('return_details.outcomes', 3)
                ->missing('summary')
                ->missing('products')
                ->missing('groups')));
    }

    public function test_every_part_of_the_screen_loads_in_a_group_of_its_own_so_they_load_side_by_side(): void
    {
        $response = $this->actingAs($this->admin)->get($this->url())->assertOk();

        $this->assertEquals(
            ['summary' => ['summary'], 'comparison' => ['comparison'], 'payments' => ['payments'], 'products' => ['products'], 'groups' => ['groups'], 'return_details' => ['return_details']],
            $response->viewData('page')['deferredProps'],
        );
    }

    public function test_a_product_sold_without_a_cost_is_told(): void
    {
        $free = $this->sellableProduct('Contoh', '10', ['cost_price' => 0]);
        $this->store->run(fn () => $free->productUnits()->update(['cost_price' => 0]));
        $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($free, 'PCS')->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10000]],
        ]));
        $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.products_without_cost', 1)
                ->where('summary.totals.gross_profit', '14000.00')));
    }

    public function test_the_figures_of_one_day_match_the_day_closing(): void
    {
        $this->sellInAWeek();
        $today = today()->toDateString();
        $closing = $this->store->run(fn (): array => app(DailyClosingService::class)->summarize(today()));

        $this->actingAs($this->admin)->get($this->url(['from' => $today, 'to' => $today]))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.totals.gross_total', $closing['total_sales'])
                ->where('summary.totals.receipt_count', $closing['sales_count'])
                ->where('payments.methods.0.amount', $closing['sales_cash'])
                ->where('payments.methods.1.amount', $closing['sales_transfer'])
                ->where('payments.methods.2.amount', $closing['sales_qris'])
                ->where('payments.methods.3.amount', $closing['sales_edc'])
                ->where('payments.methods', fn ($methods): bool => bcadd($methods[4]['amount'], $methods[5]['amount'], 2) === $closing['sales_cod'])));
    }

    public function test_the_detail_sheet_lists_every_receipt_with_its_goods_and_payments_day_by_day(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url(name: 'sales.detail-sheet'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Print/SalesDetailReport')
            ->where('report.store.name', 'Toko Uji')
            ->where('report.totals.net_total', '80000.00')
            ->has('report.days', 2)
            ->where('report.days.0.date', today()->subDay()->toDateString())
            ->where('report.days.0.receipt_count', 1)
            ->where('report.days.0.receipts.0.payments.0.method', 'transfer')
            ->where('report.days.0.receipts.0.payments.0.amount', '30000.00')
            ->where('report.days.0.receipts.0.items.0.name', 'Kuas')
            ->where('report.days.0.receipts.0.items.0.subtotal', '30000.00')
            ->where('report.days.0.net_total', '30000.00')
            ->where('report.days.1.receipt_count', 2)
            ->where('report.days.1.net_total', '50000.00')
            ->where('report.days.1.methods.0.method', 'cash')
            ->where('report.days.1.methods.0.amount', '50000.00')
            ->where('report.days.1.methods.1.method', 'cod')
            ->where('report.days.1.methods.1.amount', '20000.00'));
    }

    public function test_the_detail_sheet_lists_the_returns_of_the_period_with_their_goods(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url(name: 'sales.detail-sheet'))->assertInertia(fn (Assert $page) => $page
            ->has('report.returns', 1)
            ->where('report.returns.0.credit_amount', '20000.00')
            ->where('report.returns.0.refund_method', 'Tunai')
            ->where('report.returns.0.items.0.name', 'Kuas')
            ->where('report.returns.0.items.0.refund_subtotal', '20000.00'));
    }

    public function test_the_detail_sheet_lists_voided_receipts_apart_and_not_among_the_days(): void
    {
        $this->sell(2, [['method' => 'cash', 'amount' => 20000]]);
        $voided = $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);
        $this->store->run(fn () => Sale::query()->whereKey($voided->id)->update(['status' => 'voided', 'void_reason' => 'Salah input', 'void_business_date' => today()]));

        $this->actingAs($this->admin)->get($this->url(name: 'sales.detail-sheet'))->assertInertia(fn (Assert $page) => $page
            ->has('report.days', 1)
            ->where('report.days.0.receipt_count', 1)
            ->has('report.voids', 1)
            ->where('report.voids.0.number', $voided->number)
            ->where('report.voids.0.reason', 'Salah input')
            ->where('report.voids.0.total', '10000.00'));
    }

    public function test_the_detail_sheet_is_for_admins_only_and_leaves_out_who_rang_the_receipts_up(): void
    {
        $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);

        $this->actingAs($this->cashier)->get($this->url(name: 'sales.detail-sheet'))->assertForbidden();
        $this->actingAs($this->admin)->get($this->url(name: 'sales.detail-sheet'))->assertInertia(fn (Assert $page) => $page
            ->missing('report.days.0.receipts.0.cashier_name'));
    }

    /**
     * One sheet of the spreadsheet as the writer reads it: every row it yields, the heading first, and the row of totals it
     * returns. The period is the last `$days` days, today the last one.
     *
     * @return array{rows: list<list<string|float|null>>, totals: list<string|float|null>|null}
     */
    private function sheet(string $name, ?PaymentMethod $method = null, int $days = 7): array
    {
        return $this->store->run(function () use ($name, $method, $days): array {
            $sheet = app(SalesDetailSheetRows::class)->sheets(CarbonImmutable::today()->subDays($days - 1), CarbonImmutable::today(), $method)[$name];
            $rows = [];

            while ($sheet->valid()) {
                $rows[] = $sheet->current();
                $sheet->next();
            }

            return ['rows' => $rows, 'totals' => $sheet->getReturn()];
        });
    }

    public function test_the_spreadsheet_has_a_sheet_each_for_receipts_goods_returns_and_voids(): void
    {
        $sheets = $this->store->run(fn (): array => app(SalesDetailSheetRows::class)->sheets(CarbonImmutable::today(), CarbonImmutable::today()));

        $this->assertSame(['Nota', 'Barang', 'Retur', 'Void'], array_keys($sheets));
    }

    public function test_a_receipt_is_one_row_with_what_was_paid_split_by_method_and_cash_after_the_change(): void
    {
        // 50.000 paid with 20.000 by transfer and 40.000 in cash: 10.000 goes back as change, so the cash that stayed is 30.000.
        $sale = $this->sell(5, [['method' => 'transfer', 'amount' => 20000], ['method' => 'cash', 'amount' => 40000]]);
        $this->store->run(fn () => SalePayment::query()->where('sale_id', $sale->id)->where('method', 'transfer')->update(['reference' => 'TRX-77']));

        $sheet = $this->sheet('Nota');
        $columns = array_flip($sheet['rows'][0]);
        $row = $sheet['rows'][1];

        $this->assertCount(2, $sheet['rows']);
        $this->assertSame(today()->toDateString(), $row[$columns['Tanggal']]);
        $this->assertSame($sale->number, $row[$columns['No. nota']]);
        $this->assertSame('Ambil di toko', $row[$columns['Pengambilan']]);
        $this->assertSame(50000.0, $row[$columns['Total']]);
        $this->assertSame(30000.0, $row[$columns['Tunai']]);
        $this->assertSame(20000.0, $row[$columns['Transfer']]);
        $this->assertSame(0.0, $row[$columns['COD']]);
        $this->assertSame('Transfer: TRX-77', $row[$columns['Referensi pembayaran']]);
        $this->assertSame(50000.0, $row[$columns['Tunai']] + $row[$columns['Transfer']]);
    }

    public function test_the_receipt_sheet_closes_with_how_many_receipts_there_are_and_the_sums_of_its_amounts(): void
    {
        $this->sellInAWeek();

        $sheet = $this->sheet('Nota');
        $columns = array_flip($sheet['rows'][0]);

        $this->assertCount(4, $sheet['rows']);
        $this->assertSame('JUMLAH (3 nota)', $sheet['totals'][0]);
        $this->assertSame(100000.0, $sheet['totals'][$columns['Total']]);
        $this->assertSame(50000.0, $sheet['totals'][$columns['Tunai']]);
        $this->assertSame(30000.0, $sheet['totals'][$columns['Transfer']]);
        $this->assertSame(20000.0, $sheet['totals'][$columns['COD']]);
        $this->assertNull($sheet['totals'][$columns['Pelanggan']]);
        $this->assertNull($sheet['totals'][$columns['Referensi pembayaran']]);
    }

    public function test_every_goods_line_is_a_row_and_only_the_subtotals_are_summed(): void
    {
        $this->sellInAWeek();

        $sheet = $this->sheet('Barang');

        $this->assertCount(4, $sheet['rows']);
        $this->assertSame(['Tanggal', 'No. nota', 'Barang', 'Jumlah', 'Satuan', 'Harga satuan', 'Subtotal'], $sheet['rows'][0]);
        $this->assertSame([today()->subDay()->toDateString(), 'Kuas', 3.0, 'PCS', 10000.0, 30000.0], [$sheet['rows'][1][0], $sheet['rows'][1][2], $sheet['rows'][1][3], $sheet['rows'][1][4], $sheet['rows'][1][5], $sheet['rows'][1][6]]);
        $this->assertSame(['JUMLAH', null, null, null, null, null, 100000.0], $sheet['totals']);
    }

    public function test_the_return_and_void_sheets_list_their_lines_and_sum_their_amounts(): void
    {
        $this->sellInAWeek();
        $voided = $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);
        $this->store->run(fn () => Sale::query()->whereKey($voided->id)->update(['status' => 'voided', 'void_reason' => 'Salah input', 'void_business_date' => today()]));

        $returns = $this->sheet('Retur');
        $voids = $this->sheet('Void');

        $this->assertCount(2, $returns['rows']);
        $this->assertSame([today()->toDateString(), 'Tunai', 'Kuas', 2.0, 'PCS', 20000.0], [$returns['rows'][1][0], $returns['rows'][1][4], $returns['rows'][1][6], $returns['rows'][1][7], $returns['rows'][1][8], $returns['rows'][1][9]]);
        $this->assertSame(20000.0, $returns['totals'][9]);
        $this->assertCount(2, $voids['rows']);
        $this->assertSame([today()->toDateString(), $voided->number, 'Salah input', 10000.0], [$voids['rows'][1][0], $voids['rows'][1][1], $voids['rows'][1][3], $voids['rows'][1][4]]);
        $this->assertSame(10000.0, $voids['totals'][4]);
    }

    public function test_a_period_without_transactions_leaves_only_the_headings_and_no_row_of_totals(): void
    {
        foreach (['Nota', 'Barang', 'Retur', 'Void'] as $name) {
            $sheet = $this->sheet($name);

            $this->assertCount(1, $sheet['rows']);
            $this->assertNull($sheet['totals']);
        }
    }

    public function test_the_sums_of_cents_are_not_thrown_off_by_floating_point_error(): void
    {
        $first = $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);
        $second = $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);
        $this->store->run(function () use ($first, $second): void {
            SaleItem::query()->where('sale_id', $first->id)->update(['subtotal' => '0.10']);
            SaleItem::query()->where('sale_id', $second->id)->update(['subtotal' => '0.20']);
        });

        $this->assertSame(0.3, $this->sheet('Barang')['totals'][6]);
    }

    public function test_a_sheet_narrowed_to_one_method_has_only_the_receipts_paid_by_it_and_no_returns_or_voids(): void
    {
        $this->sellInAWeek();

        $this->assertCount(2, $this->sheet('Nota', PaymentMethod::Transfer)['rows']);
        $this->assertSame('JUMLAH (1 nota)', $this->sheet('Nota', PaymentMethod::Transfer)['totals'][0]);
        $this->assertCount(2, $this->sheet('Barang', PaymentMethod::Transfer)['rows']);
        $this->assertSame(30000.0, $this->sheet('Barang', PaymentMethod::Transfer)['totals'][6]);
        $this->assertCount(1, $this->sheet('Retur', PaymentMethod::Transfer)['rows']);
        $this->assertCount(1, $this->sheet('Void', PaymentMethod::Transfer)['rows']);
    }

    public function test_the_receipts_come_in_the_order_of_their_business_day_then_their_time(): void
    {
        $today = $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);
        $yesterday = $this->sell(1, [['method' => 'cash', 'amount' => 10000]], 1);
        // A shift opened yesterday that runs past midnight: rung up after today's receipt, yet booked on yesterday.
        $lateOfYesterday = $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);
        $this->store->run(fn () => Sale::query()->whereKey($lateOfYesterday->id)->update(['business_date' => today()->subDay(), 'sold_at' => now()->addHour()]));

        $numbers = array_column(array_slice($this->sheet('Nota')['rows'], 1), 2);

        $this->assertSame([$yesterday->number, $lateOfYesterday->number, $today->number], $numbers);
    }

    public function test_the_printed_detail_sheet_covers_at_most_31_days_but_the_spreadsheet_a_whole_year(): void
    {
        $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);
        $today = today()->toDateString();

        $this->actingAs($this->admin)->get($this->url(['from' => today()->subDays(30)->toDateString(), 'to' => $today], 'sales.detail-sheet'))->assertOk();
        $this->actingAs($this->admin)->get($this->url(['from' => today()->subDays(31)->toDateString(), 'to' => $today], 'sales.detail-sheet'))->assertStatus(422);

        $year = $this->actingAs($this->admin)->get($this->url(['from' => today()->subDays(365)->toDateString(), 'to' => $today], 'sales.detail-export'));
        $year->assertOk()->assertDownload('Laporan Penjualan Toko Uji '.today()->subDays(365)->toDateString().' - '.$today.'.xlsx');
        $this->assertNotSame('', $year->streamedContent());
    }

    public function test_the_receipt_list_says_when_the_period_is_too_long_to_print(): void
    {
        $today = today()->toDateString();

        $this->actingAs($this->admin)->get($this->url(['from' => today()->subDays(30)->toDateString(), 'to' => $today], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->where('is_sheet_too_long', false)
            ->where('max_sheet_days', 31));
        $this->actingAs($this->admin)->get($this->url(['from' => today()->subDays(31)->toDateString(), 'to' => $today], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->where('is_sheet_too_long', true));
    }

    public function test_the_receipt_list_gives_the_addresses_of_the_detail_sheet_and_export_for_its_period(): void
    {
        $period = ['from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString()];

        $this->actingAs($this->admin)->get($this->url([], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Reports/SalesReceipts')
            ->where('urls.detail_sheet', $this->url($period, 'sales.detail-sheet'))
            ->where('urls.detail_export', $this->url($period, 'sales.detail-export'))
            ->where('payment_method_options.1.value', 'transfer')
            ->where('per_page_options', [15, 30, 50]));
    }

    public function test_the_receipt_list_shows_every_receipt_newest_first_with_its_goods_and_payments(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url([], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->missing('receipts')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('receipts.total', 4)
                ->where('receipts.per_page', 15)
                ->has('receipts.data', 4)
                ->where('receipts.data', fn ($rows): bool => collect($rows)->pluck('sold_at')->sortDesc()->values()->all() === collect($rows)->pluck('sold_at')->all())
                ->where('receipts.data.3.payments.0.label', 'Transfer')
                ->where('receipts.data.3.items.0.name', 'Kuas')
                ->where('receipts.data.3.items.0.subtotal', '30000.00')
                ->where('receipts.data.3.status', 'completed')
                ->missing('receipts.data.0.cashier_name')));
    }

    public function test_the_receipt_list_counts_only_receipts_that_stand_and_lists_the_voided_ones_marked(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url([], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.receipt_count', 3)
                ->where('summary.total', '100000.00')
                ->where('receipts.data', fn ($rows): bool => collect($rows)->where('status', 'voided')->count() === 1)));
    }

    public function test_the_receipt_list_can_be_searched_by_number_or_customer(): void
    {
        $this->sellInAWeek();
        $sale = $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);
        $this->store->run(fn () => Sale::query()->whereKey($sale->id)->update(['customer_name' => 'Bu Siti']));

        foreach ([$sale->number, 'siti'] as $term) {
            $this->actingAs($this->admin)->get($this->url(['search' => $term], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', $term)
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('receipts.total', 1)
                    ->where('receipts.data.0.number', $sale->number)
                    ->where('summary.receipt_count', 1)));
        }
    }

    public function test_the_receipt_list_can_be_narrowed_to_one_payment_method(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url(['method' => 'transfer'], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->where('filters.method', 'transfer')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('receipts.total', 1)
                ->where('receipts.data.0.payments.0.label', 'Transfer')
                ->where('summary.total', '30000.00')));

        foreach (['nonsense', 'return_credit', ['transfer']] as $notAMethod) {
            $this->actingAs($this->admin)->get($this->url(['method' => $notAMethod], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
                ->where('filters.method', '')
                ->loadDeferredProps(fn (Assert $reload) => $reload->where('receipts.total', 4)));
        }
    }

    public function test_the_receipt_list_is_paged_and_a_page_past_the_last_gives_the_last(): void
    {
        $this->sellDifferentProducts(17);

        $this->actingAs($this->admin)->get($this->url([], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('receipts.data', 15)
                ->where('receipts.last_page', 2)
                ->where('receipts.from', 1)
                ->where('receipts.to', 15)));

        $this->actingAs($this->admin)->get($this->url(['page' => 99], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('receipts.data', 2)
                ->where('receipts.current_page', 2)
                ->where('receipts.from', 16)));

        foreach (['7', '0', 'abc'] as $notAnOption) {
            $this->actingAs($this->admin)->get($this->url(['per_page' => $notAnOption, 'page' => 'abc'], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->where('receipts.per_page', 15)->where('receipts.current_page', 1)));
        }

        $this->actingAs($this->admin)->get($this->url(['per_page' => 30], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('receipts.data', 17)->where('receipts.last_page', 1)));
    }

    public function test_the_receipt_list_gives_the_figures_of_each_day_for_the_day_headings(): void
    {
        $this->sellInAWeek();
        $today = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();

        $this->actingAs($this->admin)->get($this->url([], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('summary.days', 2)
                ->where("summary.days.{$today}.receipt_count", 2)
                ->where("summary.days.{$today}.gross_total", '70000.00')
                ->where("summary.days.{$today}.returns_total", '20000.00')
                ->where("summary.days.{$today}.net_total", '50000.00')
                ->where("summary.days.{$today}.voids_count", 0)
                ->where("summary.days.{$yesterday}.receipt_count", 1)
                ->where("summary.days.{$yesterday}.returns_total", '0.00')
                ->where("summary.days.{$yesterday}.net_total", '30000.00')));
    }

    public function test_the_day_figures_of_the_list_match_the_summary_of_the_sales_report(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url([], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summary.days', fn ($days): bool => collect($days)->every(fn (array $day, string $date): bool => $this->store->run(fn (): bool => collect(app(SalesReportService::class)->summary(CarbonImmutable::parse($date), CarbonImmutable::parse($date))['days'])
                    ->contains(fn (array $reportDay): bool => $reportDay['net_total'] === $day['net_total'] && $reportDay['receipt_count'] === $day['receipt_count']))))));
    }

    public function test_a_narrowed_list_has_only_the_count_and_total_of_the_matching_receipts_on_each_day(): void
    {
        $this->sellInAWeek();
        $yesterday = today()->subDay()->toDateString();

        $this->actingAs($this->admin)->get($this->url(['method' => 'transfer'], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('summary.days', 1)
                ->where("summary.days.{$yesterday}.receipt_count", 1)
                ->where("summary.days.{$yesterday}.gross_total", '30000.00')
                ->where("summary.days.{$yesterday}.returns_total", null)
                ->where("summary.days.{$yesterday}.net_total", null)
                ->where("summary.days.{$yesterday}.voids_count", null)));
    }

    public function test_the_receipts_carry_their_business_day_and_are_grouped_by_it_not_by_the_time_they_were_rung_up(): void
    {
        $standing = $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);
        $lateOfYesterday = $this->sell(1, [['method' => 'cash', 'amount' => 10000]]);

        // A shift opened yesterday that runs past midnight: sold after today's receipt, yet booked on yesterday.
        $this->store->run(fn () => Sale::query()->whereKey($lateOfYesterday->id)->update(['business_date' => today()->subDay(), 'sold_at' => now()->addHour()]));

        $this->actingAs($this->admin)->get($this->url([], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('receipts.data.0.number', $standing->number)
                ->where('receipts.data.0.business_date', today()->toDateString())
                ->where('receipts.data.1.number', $lateOfYesterday->number)
                ->where('receipts.data.1.business_date', today()->subDay()->toDateString())));
    }

    public function test_a_page_that_opens_in_the_middle_of_a_day_says_so_and_one_that_opens_on_a_new_day_does_not(): void
    {
        $this->sellDifferentProducts(17);

        $this->actingAs($this->admin)->get($this->url([], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('receipts.continues_previous_day', false)));
        $this->actingAs($this->admin)->get($this->url(['page' => 2], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('receipts.continues_previous_day', true)));
    }

    public function test_a_page_whose_first_receipt_starts_a_new_day_does_not_continue_the_one_before(): void
    {
        $this->sellDifferentProducts(15);
        $this->sell(1, [['method' => 'cash', 'amount' => 10000]], 1);
        $this->sell(1, [['method' => 'cash', 'amount' => 10000]], 1);

        $this->actingAs($this->admin)->get($this->url(['page' => 2], 'sales.receipts'))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('receipts.current_page', 2)
                ->has('receipts.data', 2)
                ->where('receipts.data.0.business_date', today()->subDay()->toDateString())
                ->where('receipts.continues_previous_day', false)));
    }

    public function test_the_receipt_list_is_for_admins_only_and_covers_this_month_without_dates(): void
    {
        $this->actingAs($this->cashier)->get($this->url([], 'sales.receipts'))->assertForbidden();

        $this->actingAs($this->admin)->get($this->urlWithoutPeriod([], 'sales.receipts'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('period.from', today()->startOfMonth()->toDateString())
            ->where('period.to', today()->endOfMonth()->toDateString()));
    }

    public function test_the_detail_sheet_adds_up_what_was_sold_per_product_and_says_when_it_was_made(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url(name: 'sales.detail-sheet'))->assertInertia(fn (Assert $page) => $page
            ->has('printed_at')
            ->where('report.filter', null)
            ->has('report.goods', 1)
            ->where('report.goods.0.name', 'Kuas')
            ->where('report.goods.0.unit', 'PCS')
            ->where('report.goods.0.quantity', '10.000')
            ->where('report.goods.0.subtotal', '100000.00'));
    }

    public function test_the_detail_sheet_can_be_narrowed_to_the_receipts_paid_by_one_method(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url(['method' => 'transfer'], 'sales.detail-sheet'))->assertInertia(fn (Assert $page) => $page
            ->where('report.filter', 'Transfer')
            ->where('report.totals.receipt_count', 1)
            ->where('report.totals.gross_total', '30000.00')
            ->has('report.methods', 1)
            ->has('report.days', 1)
            ->where('report.days.0.gross_total', '30000.00')
            ->where('report.days.0.net_total', null)
            ->where('report.days.0.returns_total', null)
            ->where('report.goods.0.quantity', '3.000')
            ->has('report.returns', 0)
            ->has('report.voids', 0));
    }

    public function test_a_method_that_is_not_one_a_sale_is_paid_by_is_left_out(): void
    {
        $this->sellInAWeek();

        foreach (['nonsense', 'return_credit', 'order_deposit', ['transfer']] as $method) {
            $this->actingAs($this->admin)->get($this->url(['method' => $method], 'sales.detail-sheet'))->assertInertia(fn (Assert $page) => $page
                ->where('report.filter', null)
                ->where('report.totals.net_total', '80000.00'));
        }
    }

    public function test_the_export_is_an_xlsx_with_a_sheet_each_for_receipts_goods_returns_and_voids(): void
    {
        $this->sellInAWeek();

        $response = $this->actingAs($this->admin)->get($this->url(name: 'sales.detail-export'));

        $response->assertOk()->assertDownload('Laporan Penjualan Toko Uji '.today()->subDays(6)->toDateString().' - '.today()->toDateString().'.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'sales-export').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $reader = new XlsxReader;
        $reader->open($path);

        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = array_map(fn ($row): array => $row->toArray(), iterator_to_array($sheet->getRowIterator(), false));
        }

        $reader->close();
        unlink($path);

        $this->assertSame(['Nota', 'Barang', 'Retur', 'Void'], array_keys($sheets));
        $this->assertCount(5, $sheets['Nota']);
        $this->assertCount(5, $sheets['Barang']);
        $this->assertCount(3, $sheets['Retur']);
        $this->assertCount(1, $sheets['Void']);
        $this->assertSame(30000.0, (float) $sheets['Nota'][1][array_search('Transfer', $sheets['Nota'][0], true)]);
        $this->assertSame('JUMLAH (3 nota)', $sheets['Nota'][4][0]);
        $this->assertSame(100000.0, (float) $sheets['Nota'][4][array_search('Total', $sheets['Nota'][0], true)]);
        $this->assertSame(100000.0, (float) $sheets['Barang'][4][array_search('Subtotal', $sheets['Barang'][0], true)]);
    }

    public function test_the_export_file_name_drops_characters_a_file_name_cannot_hold(): void
    {
        $this->store->update(['name' => 'Toko A/B: Pusat']);

        $this->actingAs($this->admin)->get($this->url(name: 'sales.detail-export'))
            ->assertDownload('Laporan Penjualan Toko A B Pusat '.today()->subDays(6)->toDateString().' - '.today()->toDateString().'.xlsx');
    }

    public function test_the_export_can_be_narrowed_to_one_method_and_is_for_admins_only(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->cashier)->get($this->url(name: 'sales.detail-export'))->assertForbidden();

        $response = $this->actingAs($this->admin)->get($this->url(['method' => 'transfer'], 'sales.detail-export'));
        $path = tempnam(sys_get_temp_dir(), 'sales-export').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $reader = new XlsxReader;
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() === 'Nota') {
                $this->assertCount(3, iterator_to_array($sheet->getRowIterator(), false));
            }
        }

        $reader->close();
        unlink($path);
    }
}
