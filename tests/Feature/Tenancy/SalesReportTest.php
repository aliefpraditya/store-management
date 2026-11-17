<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DeliveryNoteStatus;
use App\Enums\SaleReturnStatus;
use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Store;
use App\Models\User;
use App\Services\CodSaleService;
use App\Services\DailyClosingService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\SalesReportService;
use App\Services\SaleVoidService;
use App\Services\StoreDashboardService;
use Carbon\CarbonImmutable;
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
            ->where('report.totals.dead_stock_loss', '18000.00')
            ->where('report.totals.gross_profit', '2000.00')
            ->where('report.days.6.dead_stock_loss', '18000.00')
            ->where('report.days.6.gross_profit', '2000.00')
            ->where('report.days.5.dead_stock_loss', '0.00')
            ->where('report.products.0.cost', '48000.00')
            ->where('report.products.0.profit', '2000.00'));
    }

    public function test_a_product_with_only_dead_stock_in_the_period_is_listed_with_its_loss(): void
    {
        $this->postedOpname(StockOpnameReason::ReturnsCheck, '2', '0');

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('report.totals.gross_profit', '-12000.00')
            ->where('report.products.0.name', 'Kuas')
            ->where('report.products.0.revenue', '0.00')
            ->where('report.products.0.cost', '12000.00')
            ->where('report.products.0.profit', '-12000.00'));
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
            ->where('report.totals', ['gross_total' => '100000.00', 'returns_total' => '20000.00', 'net_total' => '80000.00', 'receipt_count' => 3, 'dead_stock_loss' => '0.00', 'gross_profit' => '32000.00', 'voids_count' => 0, 'voids_total' => '0.00']));
    }

    public function test_each_day_has_its_own_figures_and_days_without_sales_are_listed(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('report.days.6', ['date' => today()->toDateString(), 'gross_total' => '70000.00', 'returns_total' => '20000.00', 'net_total' => '50000.00', 'receipt_count' => 2, 'dead_stock_loss' => '0.00', 'gross_profit' => '20000.00', 'voids_count' => 0, 'voids_total' => '0.00'])
            ->where('report.days.5', ['date' => today()->subDay()->toDateString(), 'gross_total' => '30000.00', 'returns_total' => '0.00', 'net_total' => '30000.00', 'receipt_count' => 1, 'dead_stock_loss' => '0.00', 'gross_profit' => '12000.00', 'voids_count' => 0, 'voids_total' => '0.00'])
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

    public function test_what_came_in_is_split_by_method_with_cod_apart_and_split_by_delivery(): void
    {
        $this->sellInAWeek();

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('report.methods', [
                ['method' => 'cash', 'label' => 'Tunai', 'amount' => '50000.00'],
                ['method' => 'transfer', 'label' => 'Transfer', 'amount' => '30000.00'],
                ['method' => 'qris', 'label' => 'QRIS', 'amount' => '0.00'],
                ['method' => 'edc', 'label' => 'EDC', 'amount' => '0.00'],
                ['method' => 'cod_delivered', 'label' => 'COD (sudah diantar)', 'amount' => '0.00'],
                ['method' => 'cod_undelivered', 'label' => 'COD (belum diantar)', 'amount' => '20000.00'],
            ]));
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
            ->where('report.methods.4', ['method' => 'cod_delivered', 'label' => 'COD (sudah diantar)', 'amount' => '50000.00'])
            ->where('report.methods.5', ['method' => 'cod_undelivered', 'label' => 'COD (belum diantar)', 'amount' => '0.00']));

        $this->assertSame(2, $this->store->run(fn (): int => app(CodSaleService::class)->totalsOfShift($this->shift)['delivered']['count']));
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
                'is_cost_missing' => false,
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

    public function test_a_period_that_had_to_be_cut_back_says_so(): void
    {
        $this->actingAs($this->admin)->get($this->url(['from' => '2000-01-01', 'to' => today()->toDateString()]))->assertInertia(fn (Assert $page) => $page
            ->where('is_period_cut_back', true)
            ->where('max_days', 366)
            ->where('report.from', today()->subDays(365)->toDateString()));

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page->where('is_period_cut_back', false));
        $this->actingAs($this->admin)->get($this->url(['from' => today()->toDateString(), 'to' => today()->subDays(2)->toDateString()]))->assertInertia(fn (Assert $page) => $page->where('is_period_cut_back', false));
    }

    public function test_the_service_works_the_period_out_from_what_is_given(): void
    {
        $service = app(SalesReportService::class);
        $today = CarbonImmutable::today();

        $this->assertSame([$today->subDays(6)->toDateString(), $today->toDateString(), false], $this->periodDates($service->period(null, null)));
        $this->assertSame([$today->subDays(10)->toDateString(), $today->toDateString(), false], $this->periodDates($service->period($today->subDays(10), null)));
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
        foreach (['from=abc', 'from=2026-02-30&to=nonsense', 'from[]=x&to[]=y'] as $query) {
            $this->actingAs($this->admin)->get($this->url().'?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('report.from', today()->subDays(6)->toDateString())
                ->where('report.to', today()->toDateString()));
        }
    }

    public function test_voids_are_counted_on_the_day_they_were_done_and_leave_the_sales_and_the_methods(): void
    {
        $sale = $this->sell(10, [['method' => 'cash', 'amount' => 40000], ['method' => 'cod', 'amount' => 60000]], 1, isCod: true);
        $this->store->run(fn () => app(SaleVoidService::class)->void($sale, $this->cashier, 'Ditolak', null));

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('report.days.5.gross_total', '0.00')
            ->where('report.days.5.voids_count', 0)
            ->where('report.days.6.voids_count', 1)
            ->where('report.days.6.voids_total', '100000.00')
            ->where('report.totals.gross_total', '0.00')
            ->where('report.totals.voids_count', 1)
            ->where('report.totals.voids_total', '100000.00')
            ->where('report.methods.0.amount', '0.00'));
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
            ->where('report.totals.gross_total', '130000.00')
            ->where('report.methods', fn ($methods): bool => collect($methods)->reduce(fn (string $sum, $row): string => bcadd($sum, $row['amount'], 2), '0') === '130000.00')
            ->where('report.methods.6', ['method' => 'order_deposit', 'label' => 'Uang muka pesanan', 'amount' => '20000.00'])
            ->where('report.methods.7', ['method' => 'return_credit', 'label' => 'Kredit retur', 'amount' => '10000.00']));
    }

    public function test_cod_is_split_by_whether_the_goods_were_delivered(): void
    {
        $delivered = $this->sell(2, [['method' => 'cod', 'amount' => 20000]], isCod: true);
        $this->sell(3, [['method' => 'cod', 'amount' => 30000]], isCod: true);
        $this->store->run(fn () => app(CodSaleService::class)->markDelivered($delivered));

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('report.methods.4.amount', '20000.00')
            ->where('report.methods.5.amount', '30000.00'));
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
            ->where('report.days.5.gross_total', '30000.00')
            ->where('report.days.5.returns_total', '0.00')
            ->where('report.days.6.gross_total', '0.00')
            ->where('report.days.6.returns_total', '30000.00')
            ->where('report.days.6.net_total', '-30000.00'));
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
            ->where('report.days.0.returns_total', '20000.00')
            ->where('report.days.0.net_total', '30000.00')
            ->where('report.days.1.returns_total', '0.00'));
    }

    public function test_the_voids_of_the_report_follow_the_business_day_of_the_void(): void
    {
        $sale = $this->sell(1, [['method' => 'cod', 'amount' => 10000]], isCod: true);
        $this->store->run(fn () => Sale::query()->whereKey($sale->id)->update(['status' => 'voided', 'voided_at' => today()->addDay()->setTime(0, 30), 'void_business_date' => today()]));

        $this->actingAs($this->admin)->get($this->url(['from' => today()->toDateString(), 'to' => today()->addDay()->toDateString()]))->assertInertia(fn (Assert $page) => $page
            ->where('report.days.0.voids_count', 1)
            ->where('report.days.1.voids_count', 0));
    }

    public function test_only_the_best_thirty_products_are_listed_and_the_count_says_how_many_there_are(): void
    {
        foreach (range(1, 31) as $number) {
            $product = $this->sellableProduct("Barang {$number}", '10');
            $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, [
                'client_uuid' => (string) Str::uuid(),
                'items' => [['product_unit_id' => $this->unitOf($product, 'PCS')->id, 'quantity' => 1]],
                'payments' => [['method' => 'cash', 'amount' => 10000]],
            ]));
        }

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->has('report.products', 30)
            ->where('report.product_count', 31)
            ->where('report.product_limit', 30));
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
            ->where('report.products_without_cost', 1)
            ->where('report.totals.gross_profit', '14000.00'));
    }

    public function test_the_figures_of_one_day_match_the_day_closing(): void
    {
        $this->sellInAWeek();
        $today = today()->toDateString();
        $closing = $this->store->run(fn (): array => app(DailyClosingService::class)->summarize(today()));

        $this->actingAs($this->admin)->get($this->url(['from' => $today, 'to' => $today]))->assertInertia(fn (Assert $page) => $page
            ->where('report.totals.gross_total', $closing['total_sales'])
            ->where('report.totals.receipt_count', $closing['sales_count'])
            ->where('report.methods.0.amount', $closing['sales_cash'])
            ->where('report.methods.1.amount', $closing['sales_transfer'])
            ->where('report.methods.2.amount', $closing['sales_qris'])
            ->where('report.methods.3.amount', $closing['sales_edc'])
            ->where('report.methods', fn ($methods): bool => bcadd($methods[4]['amount'], $methods[5]['amount'], 2) === $closing['sales_cod']));
    }
}
