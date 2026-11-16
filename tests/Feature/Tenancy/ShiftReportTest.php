<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use App\Services\SaleService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

/**
 * The end-of-shift report: the cashier prints a blind one (counts and the cashier's own count), the Admin prints the full one.
 */
class ShiftReportTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_shift_report';

    private Store $store;

    private User $admin;

    private User $cashier;

    private Product $brush;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_shift_report', 'receipt_prefix' => 'TSP', 'address' => 'Jl. Uji 1', 'phone' => '021-123']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
        $this->brush = $this->sellableProduct('Kuas', '100');
    }

    private function cashierUrl(CashShift $shift): string
    {
        return route('tenant.cashier.shifts.report', ['tenant' => $this->store->code, 'shift' => $shift->id]);
    }

    private function adminUrl(CashShift $shift): string
    {
        return route('tenant.shifts.sheet', ['tenant' => $this->store->code, 'shift' => $shift->id]);
    }

    /**
     * The cashier sells five brushes (50.000 cash), then counts the given amount and closes.
     */
    private function closedShift(string $countedCash): CashShift
    {
        $shift = $this->openShiftFor($this->cashier);

        $this->store->run(fn () => app(SaleService::class)->create($shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($this->brush, 'PCS')->id, 'quantity' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 50000]],
        ]));

        return $this->store->run(fn (): CashShift => app(ShiftService::class)->close($shift, $countedCash, 'Kembalian kurang', '0'));
    }

    public function test_the_cashier_gets_a_report_with_counts_and_their_own_count_but_no_amount_of_the_system(): void
    {
        $shift = $this->closedShift('45000');

        $this->actingAs($this->cashier)->get($this->cashierUrl($shift))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Print/ShiftReport')
            ->where('report.is_blind', true)
            ->where('report.label', $shift->label())
            ->where('report.store.name', $this->store->name)
            ->where('report.store.address', 'Jl. Uji 1')
            ->where('report.paper_width', 80)
            ->where('report.sale_count', 1)
            ->where('report.status', 'pending_review')
            ->where('report.counted_cash', '45000.00')
            ->where('report.deposit_amount', '45000.00')
            ->where('report.drawer_left_cash', '0.00')
            ->where('report.closing_note', 'Kembalian kurang')
            ->where('report.cod', ['delivered_count' => 0, 'undelivered_count' => 0])
            ->missing('report.expected_cash')
            ->missing('report.cash_difference')
            ->missing('report.total_sales')
            ->missing('report.payments'));
    }

    public function test_a_matching_shift_prints_its_report_too_and_a_shift_still_running_has_none(): void
    {
        $matching = $this->closedShift('50000');

        $this->actingAs($this->cashier)->get($this->cashierUrl($matching))->assertOk()->assertInertia(fn (Assert $page) => $page->where('report.status', 'closed')->whereNot('report.closed_at', null));

        $running = $this->openShiftFor($this->storeUser(UserRole::Cashier), $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id])));
        $this->actingAs(User::query()->findOrFail($running->cashier_id))->get($this->cashierUrl($running))->assertNotFound();
    }

    public function test_only_the_cashier_of_the_shift_prints_it_from_the_till_even_an_admin(): void
    {
        $shift = $this->closedShift('45000');

        $this->actingAs($this->storeUser())->get($this->cashierUrl($shift))->assertForbidden();
        $this->actingAs($this->admin)->get($this->cashierUrl($shift))->assertForbidden();
    }

    public function test_an_admin_prints_the_full_report_from_the_shift_recap(): void
    {
        $shift = $this->closedShift('45000');

        $this->actingAs($this->admin)->get($this->adminUrl($shift))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Print/ShiftReport')
            ->where('report.is_blind', false)
            ->where('report.store.name', $this->store->name)
            ->where('report.paper_width', 80)
            ->where('report.total_sales', '50000.00')
            ->where('report.payments.0', ['method' => 'Tunai', 'amount' => '50000.00'])
            ->where('report.expected_cash', '50000.00')
            ->where('report.counted_cash', '45000.00')
            ->where('report.cash_difference', '-5000.00')
            ->where('report.void_deposits', '0.00')
            ->where('report.void_cash_refunds', '0.00')
            ->where('report.order_deposit_cash', '0.00')
            ->where('report.cod', ['delivered' => ['count' => 0, 'amount' => '0.00'], 'undelivered' => ['count' => 0, 'amount' => '0.00']])
            ->where('report.difference_status', 'pending_approval')
            ->missing('report.can_decide')
            ->missing('report.is_own_shift'));
    }

    public function test_the_full_report_is_for_admins_and_not_for_a_shift_that_is_still_running(): void
    {
        $shift = $this->closedShift('45000');
        $running = $this->openShiftFor($this->storeUser(), $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id])));

        $this->actingAs($this->cashier)->get($this->adminUrl($shift))->assertForbidden();
        $this->actingAs($this->admin)->get($this->adminUrl($running))->assertNotFound();
    }

    public function test_the_shift_page_and_the_till_know_where_the_report_is(): void
    {
        $shift = $this->closedShift('45000');

        $this->actingAs($this->admin)->get(route('tenant.shifts.show', ['tenant' => $this->store->code, 'shift' => $shift->id]))
            ->assertInertia(fn (Assert $page) => $page->where('shift.urls.sheet', $this->adminUrl($shift)));
        $this->actingAs($this->cashier)->get(route('tenant.cashier.index', ['tenant' => $this->store->code]))
            ->assertInertia(fn (Assert $page) => $page->where('shift.is_awaiting_review', true)->where('urls.shift_report', $this->cashierUrl($shift)));
    }
}
