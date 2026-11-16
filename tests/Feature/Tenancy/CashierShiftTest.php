<?php

namespace Tests\Feature\Tenancy;

use App\Enums\CashShiftStatus;
use App\Enums\ShiftDifferenceStatus;
use App\Enums\UserRole;
use App\Exceptions\SaleReturnException;
use App\Exceptions\ShiftException;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Register;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\ShiftRecapService;
use App\Services\ShiftService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class CashierShiftTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_cashier_shift';

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_cashier_shift', 'receipt_prefix' => 'TCS']);
    }

    private function url(string $route, array $parameters = []): string
    {
        return route("tenant.cashier.{$route}", ['tenant' => $this->store->code, ...$parameters]);
    }

    private function openShift(User $user, ?int $registerId = null, mixed $openingCash = '500000', ?string $openingNote = null): TestResponse
    {
        return $this->actingAs($user)->post($this->url('shifts.store'), [
            'register_id' => $registerId ?? $this->register()->id,
            'opening_cash' => $openingCash,
            'opening_cash_note' => $openingNote,
        ]);
    }

    /**
     * Unless told otherwise the drawer keeps 500.000 (or all the cash when less is counted), as the cashier would by habit.
     */
    private function closeShift(User $user, CashShift $shift, mixed $countedCash, ?string $note = null, mixed $drawerLeftCash = null): TestResponse
    {
        $drawerLeftCash ??= is_numeric($countedCash) ? min(500000, (float) $countedCash) : 0;

        return $this->actingAs($user)->post($this->url('shifts.close', ['shift' => $shift->id]), ['counted_cash' => $countedCash, 'drawer_left_cash' => $drawerLeftCash, 'closing_note' => $note]);
    }

    private function shiftOf(User $user): ?CashShift
    {
        return $this->store->run(fn () => CashShift::query()->where('cashier_id', $user->id)->latest('id')->first());
    }

    public function test_the_cashier_screen_is_for_every_user_of_the_store_but_not_for_outsiders(): void
    {
        foreach ([UserRole::Cashier, UserRole::StoreAdmin, UserRole::SuperAdmin] as $role) {
            $user = $role === UserRole::SuperAdmin ? User::factory()->create(['is_super_admin' => true]) : $this->storeUser($role);

            $this->actingAs($user)->get($this->url('index'))->assertOk();
        }

        $this->actingAs(User::factory()->create())->get($this->url('index'))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get($this->url('index'))->assertRedirect(route('login'));
    }

    public function test_without_a_shift_the_screen_offers_the_registers_to_open(): void
    {
        $this->actingAs($this->storeUser())->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Cashier/Index')
            ->where('shift', null)
            ->where('summary', null)
            ->has('registers', 1)
            ->where('registers.0.code', 'K1')
            ->where('registers.0.carried_over_cash', null)
            ->where('registers.0.busy_with', null)
            ->has('payment_methods', 5)
            ->where('payment_methods.0', ['value' => 'cash', 'label' => 'Tunai'])
            ->where('payment_methods.4', ['value' => 'cod', 'label' => 'COD'])
            ->where('can_leave_to_dashboard', false));
    }

    public function test_the_first_shift_of_a_register_opens_with_the_cash_the_cashier_entered(): void
    {
        $cashier = $this->storeUser();

        $this->openShift($cashier)->assertRedirect($this->url('index'))->assertSessionHas('success', 'Shift dibuka.');

        $shift = $this->shiftOf($cashier);
        $this->assertSame([CashShiftStatus::Open, '500000.00', null, $cashier->name, now()->toDateString()], [$shift->status, $shift->opening_cash, $shift->opening_cash_note, $shift->cashier_name, $shift->business_date->toDateString()]);

        $this->actingAs($cashier)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('shift.id', $shift->id)
            ->where('shift.register.code', 'K1')
            ->where('shift.opening_cash', '500000.00')
            ->where('summary.sale_count', 0)
            ->missing('summary.expected_cash')
            ->missing('summary.total_sales')
            ->missing('summary.returns_total')
            ->missing('summary.payments')
            ->where('summary.cod.delivered_count', 0)
            ->where('summary.cod.undelivered_count', 0)
            ->has('registers', 0));
    }

    public function test_a_register_has_one_open_shift_and_a_cashier_has_one_shift(): void
    {
        $first = $this->storeUser();
        $second = $this->storeUser();
        $this->openShift($first);

        $this->openShift($second)->assertSessionHas('error', 'Kasir 1 sedang dipakai '.$first->name.'. Tutup shift itu dulu.');
        $this->openShift($first)->assertSessionHas('error', 'Anda masih punya shift yang terbuka.');

        $this->assertSame(1, $this->store->run(fn () => CashShift::query()->count()));
        $this->assertNull($this->shiftOf($second));
    }

    public function test_the_screen_shows_who_holds_a_busy_register(): void
    {
        $first = $this->storeUser();
        $this->openShift($first);

        $this->actingAs($this->storeUser())->get($this->url('index'))
            ->assertInertia(fn (Assert $page) => $page->where('registers.0.busy_with', $first->name));
    }

    public function test_an_unknown_or_inactive_register_cannot_be_opened(): void
    {
        $cashier = $this->storeUser();
        $this->store->run(fn () => Register::query()->where('code', 'K1')->update(['is_active' => false]));

        $this->openShift($cashier)->assertSessionHasErrors('register_id');
        $this->openShift($cashier, 9999)->assertSessionHasErrors('register_id');
        $this->assertNull($this->shiftOf($cashier));
    }

    public function test_closing_with_the_exact_cash_has_no_difference_and_nothing_to_deposit_when_it_all_stays(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $shift = $this->shiftOf($cashier);

        $this->closeShift($cashier, $shift, '500000')
            ->assertRedirect($this->url('index'))
            ->assertSessionHas('success', 'Shift ditutup.')
            ->assertSessionHas('closed_shift', ['counted_cash' => '500000.00', 'deposit_amount' => '0.00', 'drawer_left_cash' => '500000.00', 'report_url' => $this->url('shifts.report', ['shift' => $shift->id])]);

        $closed = $this->shiftOf($cashier);
        $this->assertSame([CashShiftStatus::Closed, ShiftDifferenceStatus::None], [$closed->status, $closed->difference_status]);
        $this->assertNotNull($closed->closed_at);
        $this->assertNull($this->store->run(fn () => app(ShiftService::class)->openShiftOf($cashier)));
    }

    public function test_a_difference_needs_no_note_and_waits_for_approval(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $shift = $this->shiftOf($cashier);

        $this->closeShift($cashier, $shift, '490000')
            ->assertRedirect($this->url('index'))
            ->assertSessionHas('success', 'Hitungan tercatat. Shift belum ditutup: menunggu review Admin.')
            ->assertSessionMissing('closed_shift');

        $closed = $this->shiftOf($cashier);
        $this->assertSame(['-10000.00', CashShiftStatus::PendingReview, ShiftDifferenceStatus::PendingApproval, null], [$closed->cash_difference, $closed->status, $closed->difference_status, $closed->closing_note]);
        $this->assertNull($closed->closed_at);
        $this->assertSame($closed->id, $this->store->run(fn () => app(ShiftService::class)->openShiftOf($cashier))?->id);
    }

    public function test_a_shift_waiting_for_review_keeps_its_register_until_an_admin_decides(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $this->closeShift($cashier, $this->shiftOf($cashier), '490000');
        $next = $this->storeUser();

        $this->openShift($next)->assertSessionHas('error', 'Shift sebelumnya di Kasir 1 masih menunggu review Admin karena kasnya tidak cocok. Minta Admin memutuskannya dulu, atau pakai kasir lain.');
        $this->assertNull($this->shiftOf($next));

        $admin = $this->storeUser(UserRole::StoreAdmin);
        $this->store->run(fn () => app(ShiftRecapService::class)->decideDifference($this->shiftOf($cashier), $admin, true, 'Sudah dicek'));

        $this->assertSame(CashShiftStatus::Closed, $this->shiftOf($cashier)->status);
        $this->openShift($next, null, '490000')->assertSessionHas('success', 'Shift dibuka.');
    }

    public function test_a_shift_waiting_for_review_stays_on_the_till_locked_and_cannot_be_closed_again(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $shift = $this->shiftOf($cashier);
        $this->closeShift($cashier, $shift, '490000');

        $this->actingAs($cashier)->get($this->url('index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('shift.id', $shift->id)
            ->where('shift.is_awaiting_review', true));

        $this->closeShift($cashier, $shift, '500000')->assertSessionHas('error', 'Hitungan shift ini sudah tercatat dan menunggu review Admin.');
        $this->assertSame('-10000.00', $this->shiftOf($cashier)->cash_difference);

        $unit = $this->unitOf($this->sellableProduct(), 'PCS');
        $this->actingAs($cashier)->post($this->url('sales.store'), [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $unit->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10000]],
        ])->assertSessionHas('error');
        $this->assertSame(0, $this->store->run(fn () => Sale::query()->count()));
    }

    public function test_a_shift_waiting_for_review_shows_who_reviews_it_and_keeps_its_register_marked_busy(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $this->closeShift($cashier, $this->shiftOf($cashier), '490000');

        $this->actingAs($cashier)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('shift.reviewer_label', 'Admin Toko')
            ->where('summary', null));

        $admin = $this->storeUser(UserRole::StoreAdmin);
        $this->actingAs($admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('shift', null)
            ->where('registers.0.busy_with', $cashier->name)
            ->where('registers.0.busy_note', "menunggu review, shift {$cashier->name}"));

        $this->openShift($admin, null, '490000')->assertSessionHas('error');
        $this->assertNull($this->shiftOf($admin));
    }

    public function test_the_reviewer_shown_to_an_admin_and_a_super_admin_waiting_for_review_is_the_super_admin(): void
    {
        $second = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));
        $admin = $this->storeUser(UserRole::StoreAdmin);
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $this->openShift($admin);
        $this->closeShift($admin, $this->shiftOf($admin), '1');
        $this->openShift($superAdmin, $second->id);
        $this->closeShift($superAdmin, $this->shiftOf($superAdmin), '1');

        $this->actingAs($admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page->where('shift.reviewer_label', 'Super Admin'));
        $this->actingAs($superAdmin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page->where('shift.reviewer_label', 'Super Admin (Anda sendiri bisa memutuskannya di Rekap Shift)'));
    }

    public function test_nothing_that_needs_a_running_shift_goes_through_while_the_shift_waits_for_review(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $shift = $this->shiftOf($cashier);
        $this->closeShift($cashier, $shift, '490000');
        $hint = ShiftException::AWAITING_REVIEW_HINT;

        $categoryId = $this->store->run(fn () => ExpenseCategory::query()->where('name', 'Operasional')->value('id'));
        $unit = $this->unitOf($this->sellableProduct(), 'PCS');

        $this->actingAs($cashier)->postJson($this->url('expenses.store'), ['client_uuid' => (string) Str::uuid(), 'expense_category_id' => $categoryId, 'description' => 'Galon', 'paid_to' => 'Toko Air', 'amount' => 10000])
            ->assertStatus(422)->assertJsonPath('message', 'Buka shift dulu untuk mencatat pengeluaran dari laci kasir. '.$hint);
        $this->actingAs($cashier)->post($this->url('sales.store'), [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $unit->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10000]],
        ])->assertSessionHas('error', 'Buka shift dulu sebelum berjualan. '.$hint);
        $this->assertSame([0, 0], $this->store->run(fn (): array => [Sale::query()->count(), Expense::query()->count()]));
    }

    public function test_a_return_cannot_be_made_in_a_shift_that_waits_for_review(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $shift = $this->shiftOf($cashier);
        $unit = $this->unitOf($this->sellableProduct(), 'PCS');
        $sale = $this->store->run(fn (): Sale => app(SaleService::class)->create($shift, $cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $unit->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 20000]],
        ]));
        $this->closeShift($cashier, $shift, '490000');

        $this->expectException(SaleReturnException::class);
        $this->expectExceptionMessage('Buka shift dulu untuk membuat retur. '.ShiftException::AWAITING_REVIEW_HINT);

        $this->store->run(fn () => app(SaleReturnService::class)->create($sale, $this->shiftOf($cashier), $cashier, [
            'client_uuid' => (string) Str::uuid(),
            'refund_method' => 'cash',
            'items' => [['sale_item_id' => $sale->items[0]->id, 'quantity' => 1, 'disposition' => 'resellable']],
        ]));
    }

    public function test_the_shift_closed_by_the_cashier_keeps_who_counted_it_and_when(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);

        $this->closeShift($cashier, $this->shiftOf($cashier), '500000');

        $closed = $this->shiftOf($cashier);
        $this->assertSame([$cashier->id, CashShiftStatus::Closed], [$closed->closed_by, $closed->status]);
        $this->assertNotNull($closed->counted_at);
        $this->assertEquals($closed->counted_at, $closed->closed_at);
    }

    public function test_a_note_written_with_a_difference_is_kept_for_the_admin(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);

        $this->closeShift($cashier, $this->shiftOf($cashier), '490000', 'uang kembalian kurang')->assertSessionHas('success');

        $this->assertSame('uang kembalian kurang', $this->shiftOf($cashier)->closing_note);
    }

    public function test_the_deposit_is_the_counted_cash_minus_what_the_cashier_leaves_in_the_drawer(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $this->closeShift($cashier, $this->shiftOf($cashier), '500000', null, '300000');

        $closed = $this->shiftOf($cashier);
        $this->assertSame(['200000.00', '300000.00'], [$closed->deposit_amount, $closed->drawer_left_cash]);

        $other = $this->storeUser();
        $this->openShift($other, null, '300000');
        $this->closeShift($other, $this->shiftOf($other), '620000', 'lebih', '400000');

        $closed = $this->shiftOf($other);
        $this->assertSame(['220000.00', '400000.00'], [$closed->deposit_amount, $closed->drawer_left_cash]);
    }

    public function test_the_drawer_cannot_keep_more_than_was_counted(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $shift = $this->shiftOf($cashier);

        $this->closeShift($cashier, $shift, '500000', null, '500001')->assertSessionHas('error', 'Uang yang ditinggal di laci tidak boleh lebih dari tunai yang dihitung.');

        $this->assertSame(CashShiftStatus::Open, $this->shiftOf($cashier)->status);
    }

    public function test_what_is_left_in_the_drawer_is_validated(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $shift = $this->shiftOf($cashier);
        $close = fn (mixed $left) => $this->actingAs($cashier)->post($this->url('shifts.close', ['shift' => $shift->id]), ['counted_cash' => '500000'] + ($left === null ? [] : ['drawer_left_cash' => $left]));

        $close(null)->assertSessionHasErrors(['drawer_left_cash' => 'Isi uang yang ditinggal di laci.']);
        $close('abc')->assertSessionHasErrors('drawer_left_cash');
        $close('-5')->assertSessionHasErrors(['drawer_left_cash' => 'Uang yang ditinggal di laci tidak boleh negatif.']);
        $close('10.555')->assertSessionHasErrors('drawer_left_cash');
        $this->assertSame(CashShiftStatus::Open, $this->shiftOf($cashier)->status);
    }

    public function test_without_being_told_the_drawer_keeps_the_opening_cash_or_all_the_counted_cash_when_less(): void
    {
        $shifts = $this->store->run(fn (): ShiftService => app(ShiftService::class));
        $first = $this->openShiftFor($this->storeUser(), null, '500000');
        $closedFirst = $this->store->run(fn () => $shifts->close($first, '650000', 'lebih'));
        $this->store->run(fn () => app(ShiftRecapService::class)->decideDifference($closedFirst, $this->storeUser(UserRole::StoreAdmin), true, 'Sudah dicek'));

        $second = $this->openShiftFor($this->storeUser());
        $closedSecond = $this->store->run(fn () => $shifts->close($second, '120000', 'kurang'));

        $this->assertSame(['500000.00', '150000.00', '120000.00', '0.00'], [$closedFirst->drawer_left_cash, $closedFirst->deposit_amount, $closedSecond->drawer_left_cash, $closedSecond->deposit_amount]);
    }

    public function test_the_next_shift_on_the_register_is_offered_what_the_last_one_left_in_the_drawer(): void
    {
        $first = $this->storeUser();
        $this->openShift($first);
        $this->closeShift($first, $this->shiftOf($first), '500000', null, '300000');

        $this->actingAs($this->storeUser())->get($this->url('index'))
            ->assertInertia(fn (Assert $page) => $page->where('registers.0.carried_over_cash', '300000.00'));

        $second = $this->storeUser();
        $this->openShift($second, null, '300000')->assertSessionHas('success');
        $this->assertNull($this->shiftOf($second)->opening_cash_note);
    }

    public function test_only_the_latest_closed_shift_of_the_register_counts_and_other_registers_are_apart(): void
    {
        $secondRegister = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));
        $first = $this->storeUser();
        $this->openShift($first);
        $this->closeShift($first, $this->shiftOf($first), '500000', null, '300000');
        $this->openShift($first, null, '300000');
        $this->closeShift($first, $this->shiftOf($first), '300000', null, '100000');

        $shifts = $this->store->run(fn (): ShiftService => app(ShiftService::class));

        $this->assertSame('100000.00', $this->store->run(fn () => $shifts->carriedOverCash($this->register())));
        $this->assertNull($this->store->run(fn () => $shifts->carriedOverCash($secondRegister)));
    }

    public function test_opening_with_another_amount_than_the_drawer_was_left_with_needs_a_reason(): void
    {
        $first = $this->storeUser();
        $this->openShift($first);
        $this->closeShift($first, $this->shiftOf($first), '500000', null, '300000');
        $second = $this->storeUser();

        $this->openShift($second, null, '250000')->assertSessionHas('error', 'Modal awal berbeda dari sisa laci shift sebelumnya (Rp 300.000). Isi alasannya.');
        $this->assertNull($this->shiftOf($second));

        $this->openShift($second, null, '250000', 'Dipakai beli galon')->assertSessionHas('success');

        $shift = $this->shiftOf($second);
        $this->assertSame(['250000.00', 'Dipakai beli galon'], [$shift->opening_cash, $shift->opening_cash_note]);
    }

    public function test_the_opening_cash_is_validated(): void
    {
        $cashier = $this->storeUser();

        $this->openShift($cashier, null, '')->assertSessionHasErrors(['opening_cash' => 'Isi modal kas awal.']);
        $this->openShift($cashier, null, 'abc')->assertSessionHasErrors('opening_cash');
        $this->openShift($cashier, null, '-1')->assertSessionHasErrors(['opening_cash' => 'Modal kas awal tidak boleh negatif.']);
        $this->openShift($cashier, null, '10.555')->assertSessionHasErrors('opening_cash');
        $this->assertNull($this->shiftOf($cashier));
    }

    public function test_a_shift_cannot_be_closed_twice(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $shift = $this->shiftOf($cashier);
        $this->closeShift($cashier, $shift, '500000');

        $this->closeShift($cashier, $shift, '500000')->assertSessionHas('error', 'Shift ini sudah ditutup.');
    }

    public function test_the_counted_cash_is_validated(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $shift = $this->shiftOf($cashier);

        $this->closeShift($cashier, $shift, '')->assertSessionHasErrors('counted_cash');
        $this->closeShift($cashier, $shift, 'abc')->assertSessionHasErrors('counted_cash');
        $this->closeShift($cashier, $shift, '-5')->assertSessionHasErrors('counted_cash');
        $this->closeShift($cashier, $shift, '10.555')->assertSessionHasErrors('counted_cash');
        $this->assertSame(CashShiftStatus::Open, $this->shiftOf($cashier)->status);
    }

    public function test_only_the_owner_or_an_admin_may_close_a_shift(): void
    {
        $owner = $this->storeUser();
        $this->openShift($owner);
        $shift = $this->shiftOf($owner);

        $this->closeShift($this->storeUser(), $shift, '500000')->assertForbidden();
        $this->assertSame(CashShiftStatus::Open, $this->shiftOf($owner)->status);

        $this->closeShift($this->storeUser(UserRole::StoreAdmin), $shift, '500000')->assertSessionHas('success');
        $this->assertSame(CashShiftStatus::Closed, $this->shiftOf($owner)->status);
    }

    public function test_a_new_shift_can_be_opened_once_the_previous_one_is_closed(): void
    {
        $first = $this->storeUser();
        $second = $this->storeUser();
        $this->openShift($first);
        $this->closeShift($first, $this->shiftOf($first), '500000');

        $this->openShift($second)->assertSessionHas('success');

        $this->assertSame(2, $this->store->run(fn () => CashShift::query()->count()));
    }

    public function test_an_admin_on_the_cashier_screen_may_go_back_to_the_dashboard(): void
    {
        $this->actingAs($this->storeUser(UserRole::StoreAdmin))->get($this->url('index'))
            ->assertInertia(fn (Assert $page) => $page->where('can_leave_to_dashboard', true)->where('urls.dashboard', route('tenant.dashboard', ['tenant' => $this->store->code])));
    }

    public function test_the_database_refuses_a_second_open_shift_of_the_same_cashier_or_register(): void
    {
        $cashier = $this->storeUser();
        $other = $this->storeUser();
        $secondRegister = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));
        $this->openShift($cashier);

        $openShiftOn = fn (User $user, Register $register) => $this->store->run(fn () => CashShift::factory()->create([
            'cashier_id' => $user->id, 'cashier_name' => $user->name, 'register_id' => $register->id, 'status' => 'open',
        ]));

        $this->assertThrows(fn () => $openShiftOn($cashier, $secondRegister), UniqueConstraintViolationException::class);
        $this->assertThrows(fn () => $openShiftOn($other, $this->register()), UniqueConstraintViolationException::class);
    }

    public function test_closed_shifts_never_clash_with_each_other_or_with_an_open_one(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $this->closeShift($cashier, $this->shiftOf($cashier), '500000');
        $this->openShift($cashier);
        $this->closeShift($cashier, $this->shiftOf($cashier), '500000');
        $this->openShift($cashier)->assertSessionHas('success');

        $this->assertSame(3, $this->store->run(fn () => CashShift::query()->where('cashier_id', $cashier->id)->count()));
    }
}
