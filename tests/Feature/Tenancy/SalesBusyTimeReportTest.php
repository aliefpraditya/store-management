<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class SalesBusyTimeReportTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_sales_busy';

    /** Monday 2 to Sunday 15 November 2026, two whole weeks, long past: every weekday comes twice. */
    private const PERIOD = ['from' => '2026-11-02', 'to' => '2026-11-15'];

    private Store $store;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_sales_busy', 'receipt_prefix' => 'TSB', 'name' => 'Toko Uji']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
    }

    /**
     * @param  array<string, string|int>  $query
     */
    private function url(array $query = []): string
    {
        return route('tenant.reports.sales.busy', ['tenant' => $this->store->code, ...$query]);
    }

    private function receipt(string $businessDate, string $soldAt, int $total, bool $isVoided = false): void
    {
        $this->store->run(function () use ($businessDate, $soldAt, $total, $isVoided): void {
            $factory = Sale::factory()->state(['business_date' => $businessDate, 'sold_at' => $soldAt, 'subtotal' => $total, 'total' => $total, 'paid_total' => $total]);
            ($isVoided ? $factory->voided() : $factory)->create();
        });
    }

    /**
     * Four receipts on Mondays (the 2nd at 10.15 and 10.45, the 9th at 10.05 and 14.30), one on Tuesday the 3rd at 10.20 and
     * one rung up at 00.40 on the 4th in the shift of Tuesday the 3rd; and left out: a voided Monday receipt at 10.30 and a
     * Monday one before the period. The store traded on three days of the fourteen.
     */
    private function ringUpTheWeek(): void
    {
        $this->receipt('2026-11-02', '2026-11-02 10:15:00', 100000);
        $this->receipt('2026-11-02', '2026-11-02 10:45:00', 50000);
        $this->receipt('2026-11-09', '2026-11-09 10:05:00', 30000);
        $this->receipt('2026-11-09', '2026-11-09 14:30:00', 20000);
        $this->receipt('2026-11-03', '2026-11-03 10:20:00', 10000);
        $this->receipt('2026-11-03', '2026-11-04 00:40:00', 10000);
        $this->receipt('2026-11-09', '2026-11-09 10:30:00', 999999, isVoided: true);
        $this->receipt('2026-10-26', '2026-10-26 10:00:00', 777777);
    }

    /**
     * @param  callable(Assert): mixed  $assertions  Run once the deferred part has loaded.
     * @param  array<string, string|int>  $query
     */
    private function assertReport(callable $assertions, array $query = self::PERIOD): void
    {
        $this->actingAs($this->admin)->get($this->url($query))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Reports/SalesBusy')
            ->loadDeferredProps(fn (Assert $reload) => $assertions($reload)));
    }

    public function test_only_users_who_may_see_reports_may_open_the_busy_time_tab(): void
    {
        $this->actingAs($this->cashier)->get($this->url())->assertForbidden();
    }

    public function test_the_figures_load_apart_from_the_page(): void
    {
        $response = $this->actingAs($this->admin)->get($this->url(self::PERIOD))->assertOk();

        $this->assertEquals(['busy' => ['busy']], $response->viewData('page')['deferredProps']);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('period', self::PERIOD)
            ->where('is_period_cut_back', false)
            ->where('max_days', 366)
            ->missing('busy'));
    }

    public function test_the_receipts_are_counted_per_hour_and_per_weekday_of_their_business_day(): void
    {
        $this->ringUpTheWeek();

        $this->assertReport(fn (Assert $report) => $report
            ->where('busy.days', 3)
            ->where('busy.days_without_receipts', 11)
            ->where('busy.receipts', 6)
            ->where('busy.sales', '220000.00')
            ->where('busy.cells', fn ($cells) => collect($cells)->map(fn (array $cell): array => [$cell['weekday'], $cell['hour'], $cell['receipts']])->sort()->values()->all() === [[1, 10, 3], [1, 14, 1], [2, 0, 1], [2, 10, 1]])
            ->where('busy.weekdays.0', ['weekday' => 1, 'receipts' => 4, 'sales' => '200000.00', 'days' => 2, 'days_without_receipts' => 0, 'receipts_per_day' => '2.0', 'sales_per_day' => '100000.00'])
            ->where('busy.weekdays.1', ['weekday' => 2, 'receipts' => 2, 'sales' => '20000.00', 'days' => 1, 'days_without_receipts' => 1, 'receipts_per_day' => '2.0', 'sales_per_day' => '20000.00'])
            ->where('busy.hours.10', ['hour' => 10, 'receipts' => 4, 'sales' => '190000.00', 'days' => 3, 'days_without_receipts' => 11, 'receipts_per_day' => '1.3', 'sales_per_day' => '63333.33'])
            ->where('busy.hours.0.receipts', 1));
    }

    public function test_a_cell_averages_over_the_days_of_its_weekday_that_had_a_receipt(): void
    {
        $this->ringUpTheWeek();

        $this->assertReport(fn (Assert $report) => $report
            ->where('busy.cells', fn ($cells) => collect($cells)->contains(fn (array $cell): bool => $cell === ['weekday' => 1, 'hour' => 10, 'receipts' => 3, 'sales' => '180000.00', 'days' => 2, 'days_without_receipts' => 0, 'receipts_per_day' => '1.5', 'sales_per_day' => '90000.00'])));
    }

    public function test_the_days_without_a_receipt_are_told_per_weekday_and_do_not_pull_the_average_down(): void
    {
        $this->ringUpTheWeek();

        $this->assertReport(fn (Assert $report) => $report
            ->where('busy.weekdays.1.days_without_receipts', 1)
            ->where('busy.weekdays.1.receipts_per_day', '2.0')
            ->where('busy.weekdays.2', ['weekday' => 3, 'receipts' => 0, 'sales' => '0.00', 'days' => 0, 'days_without_receipts' => 2, 'receipts_per_day' => '0.0', 'sales_per_day' => '0.00'])
            ->where('busy.weekdays.6.days_without_receipts', 2));
    }

    public function test_every_hour_and_every_weekday_is_there_the_quiet_ones_too(): void
    {
        $this->ringUpTheWeek();

        $this->assertReport(fn (Assert $report) => $report
            ->has('busy.hours', 24)
            ->has('busy.weekdays', 7)
            ->where('busy.hours.3', ['hour' => 3, 'receipts' => 0, 'sales' => '0.00', 'days' => 3, 'days_without_receipts' => 11, 'receipts_per_day' => '0.0', 'sales_per_day' => '0.00']));
    }

    public function test_a_past_period_without_receipts_is_all_days_without_a_receipt(): void
    {
        $this->assertReport(fn (Assert $report) => $report
            ->where('busy.days', 0)
            ->where('busy.days_without_receipts', 14)
            ->where('busy.receipts', 0)
            ->where('busy.sales', '0.00')
            ->where('busy.cells', [])
            ->has('busy.hours', 24)
            ->where('busy.hours.10.receipts_per_day', '0.0'));
    }

    public function test_today_is_not_a_day_without_receipts_while_it_has_none_and_days_to_come_are_not_counted(): void
    {
        $today = today()->toDateString();

        $this->assertReport(fn (Assert $report) => $report->where('busy.days', 0)->where('busy.days_without_receipts', 0), ['from' => $today, 'to' => $today]);
        $this->assertReport(fn (Assert $report) => $report->where('busy.days_without_receipts', 0), ['from' => today()->addDays(3)->toDateString(), 'to' => today()->addDays(9)->toDateString()]);

        $this->receipt($today, now()->toDateTimeString(), 40000);

        $this->assertReport(fn (Assert $report) => $report->where('busy.days', 1)->where('busy.days_without_receipts', 0), ['from' => $today, 'to' => $today]);
    }

    public function test_the_period_can_be_narrowed_and_leaves_out_the_days_before_it(): void
    {
        $this->ringUpTheWeek();

        $this->assertReport(fn (Assert $report) => $report
            ->where('busy.receipts', 2)
            ->where('busy.days', 1)
            ->where('busy.days_without_receipts', 0)
            ->where('busy.weekdays.0.receipts', 2), ['from' => '2026-11-09', 'to' => '2026-11-09']);
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
