<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
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

    private function url(array $query = [], string $name = 'sales'): string
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

    public function test_only_admins_may_see_the_report(): void
    {
        $this->actingAs($this->cashier)->get($this->url())->assertForbidden();
        $this->actingAs($this->cashier)->get($this->url(name: 'sales.sheet'))->assertForbidden();
    }

    public function test_the_report_covers_the_last_week_when_no_dates_are_given(): void
    {
        $this->actingAs($this->admin)->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Reports/Sales')
            ->where('report.from', today()->subDays(6)->toDateString())
            ->where('report.to', today()->toDateString())
            ->has('report.days', 7)
            ->where('report.totals.net_total', '0.00')
            ->where('report.totals.receipt_count', 0)
            ->where('report.products', []));
    }

    public function test_the_totals_are_net_of_returns_and_leave_out_void_receipts(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('report.totals', ['gross_total' => '100000.00', 'returns_total' => '20000.00', 'net_total' => '80000.00', 'receipt_count' => 3, 'gross_profit' => '32000.00']));
    }

    public function test_each_day_has_its_own_figures_and_days_without_sales_are_listed(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('report.days.6', ['date' => today()->toDateString(), 'gross_total' => '70000.00', 'returns_total' => '20000.00', 'net_total' => '50000.00', 'receipt_count' => 2, 'gross_profit' => '20000.00'])
            ->where('report.days.5', ['date' => today()->subDay()->toDateString(), 'gross_total' => '30000.00', 'returns_total' => '0.00', 'net_total' => '30000.00', 'receipt_count' => 1, 'gross_profit' => '12000.00'])
            ->where('report.days.0.net_total', '0.00'));
    }

    public function test_the_period_can_be_narrowed(): void
    {
        $this->sellInAWeek();
        $today = today()->toDateString();

        $this->actingAs($this->admin)->get($this->url(['from' => $today, 'to' => $today]))->assertInertia(fn (Assert $page) => $page
            ->has('report.days', 1)
            ->where('report.totals.gross_total', '70000.00')
            ->where('report.totals.receipt_count', 2));
    }

    public function test_a_start_after_the_end_is_swapped_and_a_huge_period_is_cut_back(): void
    {
        $this->actingAs($this->admin)->get($this->url(['from' => today()->toDateString(), 'to' => today()->subDays(2)->toDateString()]))->assertInertia(fn (Assert $page) => $page
            ->where('report.from', today()->subDays(2)->toDateString())
            ->where('report.to', today()->toDateString())
            ->has('report.days', 3));

        $this->actingAs($this->admin)->get($this->url(['from' => '2000-01-01', 'to' => today()->toDateString()]))->assertInertia(fn (Assert $page) => $page->has('report.days', 366));
    }

    public function test_what_came_in_is_split_by_method_with_cod_apart(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('report.methods', [
                ['method' => 'cash', 'label' => 'Tunai', 'amount' => '50000.00'],
                ['method' => 'transfer', 'label' => 'Transfer', 'amount' => '30000.00'],
                ['method' => 'qris', 'label' => 'QRIS', 'amount' => '0.00'],
                ['method' => 'edc', 'label' => 'EDC', 'amount' => '0.00'],
                ['method' => 'cod', 'label' => 'COD', 'amount' => '20000.00'],
            ]));
    }

    public function test_cash_is_counted_after_the_change_given(): void
    {
        $this->sell(1, [['method' => 'cash', 'amount' => 20000]]);

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page->where('report.methods.0.amount', '10000.00'));
    }

    public function test_sales_are_split_by_cashier(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('report.cashiers', [['cashier_name' => $this->cashier->name, 'receipt_count' => 3, 'total' => '100000.00']]));
    }

    public function test_the_profit_per_product_is_after_returns_with_its_margin(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('report.products', [[
                'product_id' => $this->brush->id,
                'name' => 'Kuas',
                'unit' => 'PCS',
                'quantity' => '8.000',
                'revenue' => '80000.00',
                'cost' => '48000.00',
                'profit' => '32000.00',
                'margin_percent' => '40.0',
            ]]));
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
            ->where('report.products.0.name', 'Cat')
            ->where('report.products.1.name', 'Kuas'));
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
    }
}
