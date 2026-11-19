<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Register;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class ShiftRecapTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_shift_recap';

    private Store $store;

    private User $admin;

    private User $cashier;

    private Product $brush;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_shift_recap', 'receipt_prefix' => 'TSH']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
        $this->brush = $this->sellableProduct('Kuas', '100');
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.shifts.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

    private function sellBrushes(CashShift $shift, int $pieces): Sale
    {
        $pcs = $this->unitOf($this->brush, 'PCS');

        return $this->store->run(fn (): Sale => app(SaleService::class)->create($shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $pcs->id, 'quantity' => $pieces]],
            'payments' => [['method' => 'cash', 'amount' => $pieces * 10000]],
        ]));
    }

    /**
     * The cashier sells five brushes (50.000 cash), counts 45.000 and closes: a difference of -5.000 keeps the shift waiting for an admin's review.
     */
    private function shiftClosedWithShortage(): CashShift
    {
        $shift = $this->openShiftFor($this->cashier);
        $this->sellBrushes($shift, 5);

        return $this->store->run(fn (): CashShift => app(ShiftService::class)->close($shift, '45000', 'Uang kembalian kurang'));
    }

    private function shift(int $id): CashShift
    {
        return $this->store->run(fn (): CashShift => CashShift::query()->findOrFail($id));
    }

    public function test_only_admins_may_use_the_shift_recap(): void
    {
        $shift = $this->shiftClosedWithShortage();

        $this->actingAs($this->cashier)->get($this->url('index'))->assertForbidden();
        $this->actingAs($this->cashier)->get($this->url('show', ['shift' => $shift->id]))->assertForbidden();
        $this->actingAs($this->cashier)->post($this->url('approve', ['shift' => $shift->id]), ['note' => 'ok'])->assertForbidden();
        $this->actingAs($this->cashier)->post($this->url('reject', ['shift' => $shift->id]), ['note' => 'ok'])->assertForbidden();
        $this->assertSame('pending_approval', $this->shift($shift->id)->difference_status->value);
    }

    public function test_the_list_shows_shifts_newest_first_with_their_receipts_and_difference(): void
    {
        $closed = $this->shiftClosedWithShortage();
        $other = $this->storeUser();
        $second = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));
        $open = $this->openShiftFor($other, $second);

        $this->actingAs($this->admin)->get($this->url('index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Shifts/Index')
            ->has('shifts.data', 2)
            ->where('shifts.data.0.label', $open->label())
            ->where('shifts.data.0.status', 'open')
            ->where('shifts.data.0.sale_count', 0)
            ->where('shifts.data.0.sales_total', '0.00')
            ->where('shifts.data.0.register_name', 'Kasir 2')
            ->where('shifts.data.1.label', $closed->label())
            ->where('shifts.data.1.cashier_name', $this->cashier->name)
            ->where('shifts.data.1.sale_count', 1)
            ->where('shifts.data.1.sales_total', '50000.00')
            ->where('shifts.data.1.cash_difference', '-5000.00')
            ->where('shifts.data.1.difference_status_label', 'Menunggu persetujuan')
            ->where('pending_difference_count', 1)
            ->has('statuses', 3)
            ->has('differences', 4));
    }

    public function test_the_list_can_be_filtered(): void
    {
        $closed = $this->shiftClosedWithShortage();
        $other = $this->storeUser();
        $second = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));
        $this->openShiftFor($other, $second);

        $this->actingAs($this->admin)->get($this->url('index', ['difference' => 'pending_approval']))->assertInertia(fn (Assert $page) => $page->has('shifts.data', 1)->where('shifts.data.0.id', $closed->id));
        $this->actingAs($this->admin)->get($this->url('index', ['status' => 'open']))->assertInertia(fn (Assert $page) => $page->has('shifts.data', 1)->where('shifts.data.0.status', 'open'));
        $this->actingAs($this->admin)->get($this->url('index', ['search' => $other->name]))->assertInertia(fn (Assert $page) => $page->has('shifts.data', 1)->where('shifts.data.0.cashier_name', $other->name));
        $this->actingAs($this->admin)->get($this->url('index', ['from' => today()->addDay()->toDateString()]))->assertInertia(fn (Assert $page) => $page->has('shifts.data', 0));
        $this->actingAs($this->admin)->get($this->url('index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]))->assertInertia(fn (Assert $page) => $page->has('shifts.data', 2));
    }

    public function test_the_shift_page_shows_how_it_went_and_how_it_ended(): void
    {
        $shift = $this->shiftClosedWithShortage();

        $this->actingAs($this->admin)->get($this->url('show', ['shift' => $shift->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Shifts/Show')
            ->where('shift.label', $shift->label())
            ->where('shift.status', 'pending_review')
            ->where('shift.status_label', 'Menunggu review')
            ->where('shift.sale_count', 1)
            ->where('shift.total_sales', '50000.00')
            ->where('shift.payments.0', ['method' => 'Tunai', 'amount' => '50000.00'])
            ->where('shift.expected_cash', '50000.00')
            ->where('shift.counted_cash', '45000.00')
            ->where('shift.cash_difference', '-5000.00')
            ->where('shift.deposit_amount', '45000.00')
            ->where('shift.closing_note', 'Uang kembalian kurang')
            ->where('shift.can_decide', true)
            ->where('shift.urls.approve', $this->url('approve', ['shift' => $shift->id])));
    }

    public function test_the_shift_page_lists_its_returns_and_counts_the_cash_ones_in_the_drawer(): void
    {
        $shift = $this->openShiftFor($this->cashier);
        $sale = $this->sellBrushes($shift, 5);
        $this->store->run(fn () => app(SaleReturnService::class)->createApproved($sale, $shift, $this->admin, [
            'client_uuid' => (string) Str::uuid(),
            'refund_method' => 'cash',
            'items' => [['sale_item_id' => $sale->items[0]->id, 'quantity' => 2, 'disposition' => 'resellable']],
        ]));
        $this->store->run(fn () => app(ShiftService::class)->close($shift, '30000', null, '0'));

        $this->actingAs($this->admin)->get($this->url('show', ['shift' => $shift->id]))->assertInertia(fn (Assert $page) => $page
            ->where('shift.status', 'closed')
            ->where('shift.expected_cash', '30000.00')
            ->where('shift.returns_total', '20000.00')
            ->where('shift.can_decide', false)
            ->has('shift.returns', 1)
            ->where('shift.returns.0.status_label', 'Selesai')
            ->where('shift.returns.0.refund_amount', '20000.00'));
    }

    public function test_the_shift_page_lists_the_expenses_taken_from_its_drawer(): void
    {
        $shift = $this->openShiftFor($this->cashier);
        $this->sellBrushes($shift, 5);
        $this->store->run(function () use ($shift): void {
            $category = ExpenseCategory::query()->where('name', 'Operasional')->firstOrFail();
            app(ExpenseService::class)->recordFromDrawer($this->cashier, $shift, ['client_uuid' => (string) Str::uuid(), 'expense_category_id' => $category->id, 'description' => 'Beli galon', 'amount' => 15000]);
            app(ShiftService::class)->close($shift, '35000', null, '0');
        });

        $this->actingAs($this->admin)->get($this->url('show', ['shift' => $shift->id]))->assertInertia(fn (Assert $page) => $page
            ->where('shift.drawer_expenses', '15000.00')
            ->where('shift.expected_cash', '35000.00')
            ->has('shift.expenses', 1)
            ->where('shift.expenses.0.description', 'Beli galon')
            ->where('shift.expenses.0.category_name', 'Operasional')
            ->where('shift.expenses.0.amount', '15000.00')
            ->where('shift.expenses.0.status_label', 'Tercatat'));
    }

    public function test_an_admin_can_approve_the_difference_with_a_note(): void
    {
        $shift = $this->shiftClosedWithShortage();

        $this->actingAs($this->admin)->post($this->url('approve', ['shift' => $shift->id]), ['note' => 'Sudah dicek rekaman'])->assertSessionHas('success', 'Selisih kas disetujui.');

        $decided = $this->shift($shift->id);
        $this->assertSame(['approved', $this->admin->id, 'Sudah dicek rekaman'], [$decided->difference_status->value, $decided->difference_approved_by, $decided->approval_note]);
        $this->assertNotNull($decided->difference_approved_at);
        $this->assertSame('closed', $decided->status->value);

        $this->actingAs($this->admin)->get($this->url('show', ['shift' => $shift->id]))->assertInertia(fn (Assert $page) => $page
            ->where('shift.can_decide', false)
            ->where('shift.difference_status_label', 'Disetujui')
            ->where('shift.decided_by_name', $this->admin->name)
            ->where('shift.approval_note', 'Sudah dicek rekaman'));
    }

    public function test_an_admin_can_leave_the_difference_to_the_cashier(): void
    {
        $shift = $this->shiftClosedWithShortage();

        $this->actingAs($this->admin)->post($this->url('reject', ['shift' => $shift->id]), ['note' => 'Tagih ke kasir'])->assertSessionHas('success', 'Selisih kas dicatat ditanggung kasir dan shift ditutup. Berita acara dan penggantian uangnya dicatat di luar sistem.');

        $this->assertSame(['rejected', 'Tagih ke kasir', 'closed'], [$this->shift($shift->id)->difference_status->value, $this->shift($shift->id)->approval_note, $this->shift($shift->id)->status->value]);
    }

    public function test_a_note_is_needed_and_a_decided_difference_cannot_be_decided_again(): void
    {
        $shift = $this->shiftClosedWithShortage();

        $this->actingAs($this->admin)->post($this->url('approve', ['shift' => $shift->id]), ['note' => ''])->assertSessionHasErrors('note');
        $this->assertSame('pending_approval', $this->shift($shift->id)->difference_status->value);

        $this->actingAs($this->admin)->post($this->url('approve', ['shift' => $shift->id]), ['note' => 'ok']);
        $this->actingAs($this->admin)->post($this->url('reject', ['shift' => $shift->id]), ['note' => 'ubah pikiran'])
            ->assertSessionHas('error', 'Selisih kas shift ini berstatus Disetujui, jadi tidak bisa diputuskan lagi.');
        $this->assertSame('approved', $this->shift($shift->id)->difference_status->value);
    }

    public function test_a_shift_without_a_difference_has_nothing_to_decide(): void
    {
        $shift = $this->openShiftFor($this->cashier);
        $closed = $this->store->run(fn (): CashShift => app(ShiftService::class)->close($shift, '0', null));

        $this->actingAs($this->admin)->post($this->url('approve', ['shift' => $closed->id]), ['note' => 'ok'])
            ->assertSessionHas('error', 'Selisih kas shift ini berstatus Tidak ada selisih, jadi tidak bisa diputuskan lagi.');
        $this->actingAs($this->admin)->get($this->url('show', ['shift' => $closed->id]))->assertInertia(fn (Assert $page) => $page->where('shift.can_decide', false));
    }

    public function test_the_sidebar_and_dashboard_know_the_shift_recap(): void
    {
        $this->actingAs($this->admin)->get(route('tenant.dashboard', ['tenant' => $this->store->code]))
            ->assertInertia(fn (Assert $page) => $page->where('store.shifts_url', $this->url('index')));
    }

    public function test_a_store_admin_cannot_decide_their_own_shift_nor_that_of_another_store_admin_only_the_super_admin_can(): void
    {
        $shift = $this->openShiftFor($this->admin);
        $closed = $this->store->run(fn (): CashShift => app(ShiftService::class)->close($shift, '5000', 'Uang lebih'));

        $this->actingAs($this->admin)->post($this->url('approve', ['shift' => $closed->id]), ['note' => 'Saya cek sendiri'])
            ->assertSessionHas('error', 'Selisih kas shift Anda sendiri harus diputuskan oleh yang berwenang: Admin Toko untuk shift kasir, Super Admin untuk shift Admin Toko.');
        $this->actingAs($this->admin)->get($this->url('show', ['shift' => $closed->id]))->assertInertia(fn (Assert $page) => $page
            ->where('shift.is_own_shift', true)
            ->where('shift.can_decide', false)
            ->where('shift.reviewer_label', 'Super Admin'));

        $otherAdmin = $this->storeUser(UserRole::StoreAdmin);
        $this->actingAs($otherAdmin)->post($this->url('approve', ['shift' => $closed->id]), ['note' => 'Sudah dicek'])
            ->assertSessionHas('error', 'Selisih kas shift Admin Toko hanya bisa diputuskan Super Admin.');
        $this->actingAs($otherAdmin)->get($this->url('show', ['shift' => $closed->id]))->assertInertia(fn (Assert $page) => $page
            ->where('shift.can_decide', false)
            ->where('shift.decision_blocked_reason', 'Selisih kas shift Admin Toko hanya bisa diputuskan Super Admin.'));
        $this->assertSame('pending_approval', $this->shift($closed->id)->difference_status->value);

        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($superAdmin)->post($this->url('approve', ['shift' => $closed->id]), ['note' => 'Dicek Super Admin'])->assertSessionHas('success');
        $this->assertSame('approved', $this->shift($closed->id)->difference_status->value);
    }

    public function test_a_cashier_shift_is_decided_by_a_store_admin_whoever_it_is_but_not_by_the_cashier(): void
    {
        $shift = $this->shiftClosedWithShortage();
        $otherAdmin = $this->storeUser(UserRole::StoreAdmin);

        $this->actingAs($this->cashier)->post($this->url('approve', ['shift' => $shift->id]), ['note' => 'ok'])->assertForbidden();
        $this->actingAs($this->admin)->get($this->url('show', ['shift' => $shift->id]))->assertInertia(fn (Assert $page) => $page
            ->where('shift.reviewer_label', 'Admin Toko')
            ->where('shift.can_decide', true)
            ->where('shift.decision_blocked_reason', null));
        $this->actingAs($otherAdmin)->post($this->url('reject', ['shift' => $shift->id]), ['note' => 'Tagih'])->assertSessionHas('success');
    }

    public function test_a_cashier_who_became_an_admin_before_the_decision_is_reviewed_by_the_super_admin(): void
    {
        $shift = $this->shiftClosedWithShortage();
        $this->cashier->stores()->updateExistingPivot($this->store->getKey(), ['role_id' => Role::idOf(UserRole::StoreAdmin)]);

        $this->actingAs($this->admin)->post($this->url('approve', ['shift' => $shift->id]), ['note' => 'ok'])
            ->assertSessionHas('error', 'Selisih kas shift Admin Toko hanya bisa diputuskan Super Admin.');
    }

    public function test_a_super_admin_decides_the_difference_of_a_store_admin_shift(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $shift = $this->openShiftFor($this->admin);
        $closed = $this->store->run(fn (): CashShift => app(ShiftService::class)->close($shift, '5000', null));

        $this->actingAs($superAdmin)->get($this->url('show', ['shift' => $closed->id]))->assertInertia(fn (Assert $page) => $page->where('shift.can_decide', true));
        $this->actingAs($superAdmin)->post($this->url('reject', ['shift' => $closed->id]), ['note' => 'BA no. 7'])->assertSessionHas('success');
        $this->assertSame([$superAdmin->id, 'closed'], [$this->shift($closed->id)->difference_approved_by, $this->shift($closed->id)->status->value]);
    }

    public function test_the_expected_cash_and_money_of_a_running_shift_are_not_given_to_the_admin(): void
    {
        $shift = $this->openShiftFor($this->cashier);
        $this->sellBrushes($shift, 5);

        $this->actingAs($this->admin)->get($this->url('show', ['shift' => $shift->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('shift.status', 'open')
            ->where('shift.is_cash_hidden', true)
            ->where('shift.sale_count', 1)
            ->where('shift.payments', [])
            ->where('shift.returns', [])
            ->where('shift.expenses', [])
            ->missing('shift.expected_cash')
            ->missing('shift.total_sales')
            ->missing('shift.drawer_expenses')
            ->missing('shift.cash_refunds'));
        $this->actingAs($this->admin)->get($this->url('sheet', ['shift' => $shift->id]))->assertNotFound();
    }

    public function test_an_admin_can_close_a_shift_the_cashier_forgot_but_cannot_decide_its_difference(): void
    {
        $shift = $this->openShiftFor($this->cashier);
        $this->sellBrushes($shift, 5);

        $this->actingAs($this->admin)->get($this->url('show', ['shift' => $shift->id]))->assertInertia(fn (Assert $page) => $page
            ->where('shift.can_close', true)
            ->where('shift.urls.close', route('tenant.cashier.shifts.close', ['tenant' => $this->store->code, 'shift' => $shift->id])));

        $this->actingAs($this->admin)->post(route('tenant.cashier.shifts.close', ['tenant' => $this->store->code, 'shift' => $shift->id]), ['counted_cash' => 45000, 'drawer_left_cash' => 0])
            ->assertRedirect($this->url('show', ['shift' => $shift->id]))
            ->assertSessionHas('success', 'Hitungan tercatat. Selisihnya menunggu keputusan.');

        $closed = $this->shift($shift->id);
        $this->assertSame([$this->admin->id, 'pending_review'], [$closed->closed_by, $closed->status->value]);
        $this->assertNotNull($closed->counted_at);
        $this->assertNull($closed->closed_at);

        $this->actingAs($this->admin)->post($this->url('approve', ['shift' => $shift->id]), ['note' => 'ok'])
            ->assertSessionHas('error', 'Anda yang menutup shift ini, jadi selisih kasnya diputuskan Admin lain atau Super Admin.');
        $this->actingAs($this->storeUser(UserRole::StoreAdmin))->post($this->url('approve', ['shift' => $shift->id]), ['note' => 'Dicek'])->assertSessionHas('success');
    }

    public function test_the_own_running_shift_of_an_admin_is_closed_from_the_till_not_from_the_recap(): void
    {
        $shift = $this->openShiftFor($this->admin);

        $this->actingAs($this->admin)->get($this->url('show', ['shift' => $shift->id]))->assertInertia(fn (Assert $page) => $page->where('shift.can_close', false));
        $this->actingAs($this->admin)->post(route('tenant.cashier.shifts.close', ['tenant' => $this->store->code, 'shift' => $shift->id]), ['counted_cash' => 0, 'drawer_left_cash' => 0])
            ->assertRedirect(route('tenant.cashier.index', ['tenant' => $this->store->code]));
    }

    public function test_a_super_admin_can_decide_the_difference_of_a_shift_they_worked_themselves(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $shift = $this->openShiftFor($superAdmin);
        $closed = $this->store->run(fn (): CashShift => app(ShiftService::class)->close($shift, '5000', 'Uang lebih'));

        $this->assertSame('pending_approval', $this->shift($closed->id)->difference_status->value);
        $this->actingAs($superAdmin)->get($this->url('show', ['shift' => $closed->id]))->assertInertia(fn (Assert $page) => $page
            ->where('shift.is_own_shift', true)
            ->where('shift.can_decide', true));

        $this->actingAs($superAdmin)->post($this->url('approve', ['shift' => $closed->id]), ['note' => 'Dicek Super Admin'])->assertSessionHas('success', 'Selisih kas disetujui.');

        $decided = $this->shift($closed->id);
        $this->assertSame(['approved', $superAdmin->id, 'closed'], [$decided->difference_status->value, $decided->difference_approved_by, $decided->status->value]);
    }

    public function test_the_date_filters_leave_out_a_date_that_is_not_one_and_cut_at_the_day(): void
    {
        $this->shiftClosedWithShortage();

        $this->actingAs($this->admin)->get($this->url('index', ['from' => 'abc']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('shifts.data', 1)->where('filters.from', null));
        $this->actingAs($this->admin)->get($this->url('index', ['to' => today()->subDay()->toDateString()]))->assertInertia(fn (Assert $page) => $page->has('shifts.data', 0));
        $this->actingAs($this->admin)->get($this->url('index', ['to' => today()->toDateString()]))->assertInertia(fn (Assert $page) => $page->has('shifts.data', 1));
    }
}
