<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DailyClosingStatus;
use App\Enums\DeliveryNoteStatus;
use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\DailyClosing;
use App\Models\DeliveryNote;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Services\ExpenseService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\ShiftRecapService;
use App\Services\ShiftService;
use Exception;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class DailyClosingTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_daily_closing';

    private Store $store;

    private User $admin;

    private User $superAdmin;

    private User $cashier;

    private Product $brush;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_daily_closing', 'receipt_prefix' => 'TDC', 'address' => 'Jl. Uji 1']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->cashier = $this->storeUser();
        $this->brush = $this->sellableProduct('Kuas', '100');
    }

    private function url(string $name, ?string $date = null): string
    {
        return route("tenant.daily-closings.{$name}", ['tenant' => $this->store->code, ...($date === null ? [] : ['date' => $date])]);
    }

    private function today(): string
    {
        return today()->toDateString();
    }

    private function closing(): ?DailyClosing
    {
        return $this->store->run(fn (): ?DailyClosing => DailyClosing::query()->first());
    }

    private function closeToday(?User $by = null, ?string $notes = null): void
    {
        $this->actingAs($by ?? $this->admin)->post($this->url('store', $this->today()), ['notes' => $notes])->assertSessionHasNoErrors();
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
     * Today as it would stand at the end of a normal day: five brushes sold for cash (50.000), two of them returned
     * for a cash refund (20.000), 10.000 taken from the drawer and 70.000 paid from elsewhere. The shift is counted to
     * the rupiah (20.000 in the drawer) and closed.
     */
    private function settledDay(): CashShift
    {
        $shift = $this->openShiftFor($this->cashier);
        $sale = $this->sellBrushes($shift, 5);

        $this->store->run(function () use ($shift, $sale): void {
            app(SaleReturnService::class)->createApproved($sale, $shift, $this->admin, [
                'client_uuid' => (string) Str::uuid(),
                'refund_method' => 'cash',
                'items' => [['sale_item_id' => $sale->items[0]->id, 'quantity' => 2, 'disposition' => 'resellable']],
            ]);

            $categoryId = ExpenseCategory::query()->where('name', 'Operasional')->value('id');
            app(ExpenseService::class)->recordFromDrawer($this->cashier, $shift, ['client_uuid' => (string) Str::uuid(), 'expense_category_id' => $categoryId, 'description' => 'Beli galon', 'amount' => 10000]);
            app(ExpenseService::class)->recordFromOtherSource($this->admin, today(), ['client_uuid' => (string) Str::uuid(), 'expense_category_id' => $categoryId, 'description' => 'Listrik', 'amount' => 70000]);
            app(ShiftService::class)->close($shift, '20000', null);
        });

        return $shift;
    }

    public function test_only_admins_may_use_day_closing_and_only_a_super_admin_may_reopen(): void
    {
        $this->actingAs($this->cashier)->get($this->url('index'))->assertForbidden();
        $this->actingAs($this->cashier)->get($this->url('show', $this->today()))->assertForbidden();
        $this->actingAs($this->cashier)->post($this->url('store', $this->today()))->assertForbidden();
        $this->actingAs($this->cashier)->get($this->url('sheet', $this->today()))->assertForbidden();

        $this->closeToday();
        $this->actingAs($this->admin)->post($this->url('reopen', $this->today()), ['reason' => 'Salah hitung'])->assertForbidden();

        $this->assertSame(DailyClosingStatus::Closed, $this->closing()->status);
    }

    public function test_the_index_goes_to_today(): void
    {
        $this->actingAs($this->admin)->get($this->url('index'))->assertRedirect($this->url('show', $this->today()));
    }

    public function test_a_day_that_is_not_real_or_has_not_come_yet_is_not_found(): void
    {
        $this->actingAs($this->admin)->get($this->url('show', '2026-13-45'))->assertNotFound();
        $this->actingAs($this->admin)->get($this->url('show', today()->addDay()->toDateString()))->assertNotFound();
        $this->actingAs($this->admin)->post($this->url('store', today()->addDay()->toDateString()))->assertNotFound();
        $this->assertNull($this->closing());
    }

    public function test_an_open_shift_blocks_closing_the_day(): void
    {
        $shift = $this->openShiftFor($this->cashier);

        $this->actingAs($this->admin)->get($this->url('show', $this->today()))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/DailyClosings/Show')
            ->where('checklist.can_close', false)
            ->has('checklist.open_shifts', 1)
            ->where('checklist.open_shifts.0.label', $shift->label())
            ->where('closing', null));

        $this->actingAs($this->admin)->post($this->url('store', $this->today()))->assertSessionHas('error', 'Hari '.today()->format('d/m/Y').' belum bisa ditutup. Selesaikan dulu shift yang masih terbuka, selisih kas yang belum diputuskan, atau retur yang menunggu persetujuan, lalu coba lagi.');
        $this->assertNull($this->closing());
    }

    public function test_a_cash_difference_nobody_decided_blocks_closing_until_it_is_decided(): void
    {
        $shift = $this->openShiftFor($this->cashier);
        $this->sellBrushes($shift, 5);
        $this->store->run(fn () => app(ShiftService::class)->close($shift, '45000', 'Uang kembalian kurang'));

        $this->actingAs($this->admin)->get($this->url('show', $this->today()))->assertInertia(fn (Assert $page) => $page
            ->where('checklist.can_close', false)
            ->has('checklist.pending_differences', 1)
            ->where('checklist.pending_differences.0.amount', '-5000.00'));
        $this->actingAs($this->admin)->post($this->url('store', $this->today()))->assertSessionHas('error');

        $this->store->run(fn () => app(ShiftRecapService::class)->decideDifference($shift, $this->admin, true, 'Dicek rekaman'));

        $this->actingAs($this->admin)->get($this->url('show', $this->today()))->assertInertia(fn (Assert $page) => $page->where('checklist.can_close', true));
        $this->closeToday();
        $this->assertSame(DailyClosingStatus::Closed, $this->closing()->status);
    }

    public function test_a_return_waiting_for_approval_is_counted_on_the_list(): void
    {
        $shift = $this->openShiftFor($this->cashier);
        $sale = $this->sellBrushes($shift, 5);
        $this->store->run(fn () => app(SaleReturnService::class)->create($sale, $shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'refund_method' => 'cash',
            'items' => [['sale_item_id' => $sale->items[0]->id, 'quantity' => 1, 'disposition' => 'resellable']],
        ]));

        $this->actingAs($this->admin)->get($this->url('show', $this->today()))->assertInertia(fn (Assert $page) => $page
            ->where('checklist.pending_return_count', 1)
            ->where('checklist.can_close', false));
    }

    public function test_unfinished_delivery_notes_only_remind(): void
    {
        $this->store->run(function (): void {
            DeliveryNote::factory()->create(['status' => DeliveryNoteStatus::Shipped, 'business_date' => today()]);
            // The factory's receipt comes with a running shift of its own, which would block the day.
            CashShift::query()->update(['status' => 'closed', 'closed_at' => now()]);
        });

        $this->actingAs($this->admin)->get($this->url('show', $this->today()))->assertInertia(fn (Assert $page) => $page
            ->where('checklist.unfinished_delivery_note_count', 1)
            ->where('checklist.can_close', true));
    }

    public function test_a_day_without_any_transaction_can_be_closed(): void
    {
        $this->closeToday($this->admin, 'Toko tutup');

        $closing = $this->closing();
        $this->assertSame([DailyClosingStatus::Closed, 'Toko tutup', $this->admin->id, 0, 0, '0.00'], [$closing->status, $closing->notes, $closing->closed_by, $closing->shifts_count, $closing->sales_count, $closing->total_sales]);
    }

    public function test_closing_saves_the_figures_of_the_day(): void
    {
        $this->settledDay();

        $this->closeToday($this->admin, 'Semua cocok');

        $closing = $this->closing();
        $this->assertSame(
            [1, 1, '50000.00', '50000.00', '0.00', '0.00', '0.00', '20000.00', '20000.00', '10000.00', '70000.00', '20000.00', '20000.00', '0.00', '20000.00'],
            [$closing->shifts_count, $closing->sales_count, $closing->total_sales, $closing->sales_cash, $closing->sales_transfer, $closing->sales_qris, $closing->sales_edc, $closing->total_returns, $closing->refunds_cash, $closing->expenses_drawer, $closing->expenses_other, $closing->total_expected_cash, $closing->total_counted_cash, $closing->total_cash_difference, $closing->total_deposit],
        );
        $this->assertSame([DailyClosingStatus::Closed, 'Semua cocok', $this->admin->id], [$closing->status, $closing->notes, $closing->closed_by]);
    }

    public function test_the_cash_of_the_day_adds_up_from_its_parts(): void
    {
        $this->settledDay();

        $figures = $this->store->run(fn (): array => app(DailyClosingService::class)->summarize(today()));

        $this->assertSame(
            (string) number_format((float) $figures['sales_cash'] - (float) $figures['refunds_cash'] - (float) $figures['expenses_drawer'], 2, '.', ''),
            $figures['total_expected_cash'],
        );
    }

    public function test_methods_other_than_cash_are_counted_apart(): void
    {
        $shift = $this->openShiftFor($this->cashier);
        $pcs = $this->unitOf($this->brush, 'PCS');
        $this->store->run(fn () => app(SaleService::class)->create($shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $pcs->id, 'quantity' => 10]],
            'payments' => [['method' => 'cash', 'amount' => 60000], ['method' => 'qris', 'amount' => 40000]],
        ]));
        $this->store->run(fn () => app(ShiftService::class)->close($shift, '60000', null));

        $this->closeToday();

        $closing = $this->closing();
        $this->assertSame(['100000.00', '60000.00', '0.00', '40000.00'], [$closing->total_sales, $closing->sales_cash, $closing->sales_transfer, $closing->sales_qris]);
    }

    public function test_a_closed_day_shows_its_saved_figures_and_cannot_be_closed_again(): void
    {
        $this->settledDay();
        $this->closeToday();
        $this->store->run(fn () => DailyClosing::query()->update(['total_sales' => 123]));

        $this->actingAs($this->admin)->get($this->url('show', $this->today()))->assertInertia(fn (Assert $page) => $page
            ->where('closing.status', 'closed')
            ->where('figures.total_sales', '123.00')
            ->where('closing.closed_by_name', $this->admin->name));

        $this->actingAs($this->admin)->post($this->url('store', $this->today()))->assertSessionHas('error', 'Hari '.today()->format('d/m/Y').' sudah ditutup.');
    }

    public function test_the_history_lists_closed_days_newest_first(): void
    {
        $this->store->run(function (): void {
            foreach ([3, 1, 2] as $daysAgo) {
                DailyClosing::query()->create(['business_date' => today()->subDays($daysAgo), 'status' => DailyClosingStatus::Closed, 'closed_by' => $this->admin->id, 'closed_at' => now()]);
            }
        });

        $this->actingAs($this->admin)->get($this->url('show', $this->today()))->assertInertia(fn (Assert $page) => $page
            ->has('history', 3)
            ->where('history.0.business_date', today()->subDay()->toDateString())
            ->where('history.2.business_date', today()->subDays(3)->toDateString()));
    }

    public function test_a_closed_day_locks_its_transactions(): void
    {
        $this->settledDay();
        $otherExpense = $this->store->run(fn (): Expense => Expense::query()->whereNull('cash_shift_id')->sole());
        $this->closeToday();

        $categoryId = $this->store->run(fn (): int => ExpenseCategory::query()->where('name', 'Operasional')->value('id'));
        $closedDayMessage = 'Hari '.today()->format('d/m/Y').' sudah ditutup, jadi pengeluaran pada tanggal itu tidak bisa ditambah atau dibatalkan. Minta Super Admin membuka kembali hari itu.';

        $this->actingAs($this->admin)->post(route('tenant.expenses.store', ['tenant' => $this->store->code]), [
            'client_uuid' => (string) Str::uuid(), 'expense_category_id' => $categoryId, 'source' => 'other', 'business_date' => $this->today(), 'description' => 'Susulan', 'amount' => 5000,
        ])->assertSessionHas('error', $closedDayMessage);
        $this->actingAs($this->admin)->post(route('tenant.expenses.void', ['tenant' => $this->store->code, 'expense' => $otherExpense->id]), ['reason' => 'Salah'])->assertSessionHas('error', $closedDayMessage);

        $this->actingAs($this->admin)->post(route('tenant.expenses.store', ['tenant' => $this->store->code]), [
            'client_uuid' => (string) Str::uuid(), 'expense_category_id' => $categoryId, 'source' => 'other', 'business_date' => today()->subDay()->toDateString(), 'description' => 'Kemarin', 'amount' => 5000,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get(route('tenant.expenses.index', ['tenant' => $this->store->code]))->assertInertia(fn (Assert $page) => $page
            ->where('expenses.data', fn ($rows) => collect($rows)->firstWhere('id', $otherExpense->id)['can_be_voided'] === false));
        $this->assertSame(3, $this->store->run(fn (): int => Expense::query()->count()));
    }

    public function test_no_shift_can_be_opened_on_a_closed_day(): void
    {
        $this->closeToday();

        $this->actingAs($this->cashier)->post(route('tenant.cashier.shifts.store', ['tenant' => $this->store->code]), ['register_id' => $this->register()->id, 'opening_cash' => '0'])
            ->assertSessionHas('error', 'Hari '.today()->format('d/m/Y').' sudah ditutup. Minta Super Admin membuka kembali hari itu sebelum membuka shift.');

        $this->assertSame(0, $this->store->run(fn (): int => CashShift::query()->count()));
    }

    public function test_a_super_admin_reopens_a_day_with_a_reason_and_it_is_closed_again_afterwards(): void
    {
        $this->settledDay();
        $this->closeToday();

        $this->actingAs($this->superAdmin)->post($this->url('reopen', $this->today()), ['reason' => ''])->assertSessionHasErrors(['reason' => 'Isi alasan membuka kembali hari ini.']);
        $this->assertSame(DailyClosingStatus::Closed, $this->closing()->status);

        $this->actingAs($this->superAdmin)->post($this->url('reopen', $this->today()), ['reason' => 'Nota salah catat'])->assertSessionHasNoErrors();

        $closing = $this->closing();
        $this->assertSame([DailyClosingStatus::Reopened, 'Nota salah catat', $this->superAdmin->id], [$closing->status, $closing->reopen_reason, $closing->reopened_by]);
        $this->assertFalse($this->store->run(fn (): bool => DailyClosing::isClosedOn(today())));

        $this->openShiftFor($this->storeUser());
        $this->store->run(fn () => CashShift::query()->update(['status' => 'closed', 'closed_at' => now(), 'difference_status' => 'none']));
        $this->closeToday($this->superAdmin, 'Ditutup ulang');

        $closing = $this->closing();
        $this->assertSame([DailyClosingStatus::Closed, 'Ditutup ulang', 'Nota salah catat', 2], [$closing->status, $closing->notes, $closing->reopen_reason, $closing->shifts_count]);
        $this->assertSame(1, $this->store->run(fn (): int => DailyClosing::query()->count()));
    }

    public function test_a_day_that_is_not_closed_cannot_be_reopened(): void
    {
        $this->actingAs($this->superAdmin)->post($this->url('reopen', $this->today()), ['reason' => 'Coba'])
            ->assertSessionHas('error', 'Hari '.today()->format('d/m/Y').' tidak sedang berstatus tutup, jadi tidak bisa dibuka kembali.');
    }

    public function test_the_daily_report_is_printed_from_the_saved_figures_of_a_closed_day(): void
    {
        $this->settledDay();
        $this->closeToday($this->admin, 'Semua cocok');

        $this->actingAs($this->admin)->get($this->url('sheet', $this->today()))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Print/DailyReport')
            ->where('report.store.name', $this->store->name)
            ->where('report.business_date', $this->today())
            ->where('report.notes', 'Semua cocok')
            ->where('report.figures.total_sales', '50000.00')
            ->where('report.figures.expenses_other', '70000.00')
            ->has('report.shifts', 1)
            ->where('report.shifts.0.cashier_name', $this->cashier->name)
            ->where('report.shifts.0.expected_cash', '20000.00'));
    }

    public function test_only_a_closed_day_has_a_report(): void
    {
        $this->actingAs($this->admin)->get($this->url('sheet', $this->today()))->assertNotFound();

        $this->closeToday();
        $this->actingAs($this->superAdmin)->post($this->url('reopen', $this->today()), ['reason' => 'Koreksi']);

        $this->actingAs($this->admin)->get($this->url('sheet', $this->today()))->assertNotFound();
    }

    public function test_a_reopened_day_shows_its_figures_as_they_stand_now(): void
    {
        $this->settledDay();
        $this->closeToday();
        $this->actingAs($this->superAdmin)->post($this->url('reopen', $this->today()), ['reason' => 'Nota salah catat']);
        $this->store->run(fn () => DailyClosing::query()->update(['total_sales' => 123]));

        $this->actingAs($this->admin)->get($this->url('show', $this->today()))->assertInertia(fn (Assert $page) => $page
            ->where('closing.status', 'reopened')
            ->where('closing.reopened_by_name', $this->superAdmin->name)
            ->where('figures.total_sales', '50000.00'));
    }

    public function test_closing_and_reopening_a_day_are_audited(): void
    {
        config(['audit.console' => true]);
        $this->settledDay();

        $this->closeToday();
        $this->actingAs($this->superAdmin)->post($this->url('reopen', $this->today()), ['reason' => 'Nota salah catat']);

        $audits = $this->store->run(fn () => DailyClosing::query()->sole()->audits()->orderBy('id')->get());

        $this->assertSame(['created', 'updated'], $audits->pluck('event')->all());
        $this->assertSame('reopened', $audits[1]->new_values['status']);
        $this->assertSame('Nota salah catat', $audits[1]->new_values['reopen_reason']);
    }

    /**
     * @return list<string>
     */
    private function statementsRunBy(callable $action): array
    {
        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $action();

        return $statements;
    }

    /**
     * What the day's transactions rest on is read only after the registers are locked. A plain read of an expense
     * before that (the route binding of a void) changes nothing: the service reads it again under its lock.
     *
     * @param  list<string>  $statements
     */
    private function assertRegistersAreLockedBeforeAnythingElse(array $statements): void
    {
        $isRegisterLock = fn (string $sql): bool => str_contains($sql, 'from `registers`') && str_contains($sql, 'for update');
        $isDayRead = fn (string $sql): bool => str_contains($sql, 'from `daily_closings`') || (str_contains($sql, 'from `expenses`') && str_contains($sql, 'for update'));

        $registerLockAt = array_key_first(array_filter($statements, $isRegisterLock));
        $firstReadAt = array_key_first(array_filter($statements, $isDayRead));

        $this->assertNotNull($registerLockAt, 'the registers were never locked');
        $this->assertLessThan($firstReadAt, $registerLockAt);
    }

    public function test_closing_a_day_locks_the_registers_before_it_reads_the_day(): void
    {
        $this->register();

        $statements = $this->statementsRunBy(fn () => $this->closeToday());

        $this->assertRegistersAreLockedBeforeAnythingElse($statements);
    }

    public function test_booking_or_voiding_an_expense_of_another_source_locks_the_registers_first(): void
    {
        $this->register();
        $categoryId = $this->store->run(fn (): int => ExpenseCategory::query()->where('name', 'Operasional')->value('id'));

        $recordStatements = $this->statementsRunBy(fn () => $this->actingAs($this->admin)->post(route('tenant.expenses.store', ['tenant' => $this->store->code]), [
            'client_uuid' => (string) Str::uuid(), 'expense_category_id' => $categoryId, 'source' => 'other', 'business_date' => $this->today(), 'description' => 'Listrik', 'amount' => 5000,
        ])->assertSessionHasNoErrors());
        $this->assertRegistersAreLockedBeforeAnythingElse($recordStatements);

        $expense = $this->store->run(fn (): Expense => Expense::query()->sole());
        $voidStatements = $this->statementsRunBy(fn () => $this->actingAs($this->admin)->post(route('tenant.expenses.void', ['tenant' => $this->store->code, 'expense' => $expense->id]), ['reason' => 'Salah'])->assertSessionHasNoErrors());
        $this->assertRegistersAreLockedBeforeAnythingElse($voidStatements);
    }

    public function test_two_closings_that_race_to_insert_the_same_day_leave_the_second_a_clear_message(): void
    {
        DailyClosing::creating(fn () => throw new UniqueConstraintViolationException('mysql', 'insert into `daily_closings`', [], new Exception('Duplicate entry')));

        $this->actingAs($this->admin)->post($this->url('store', $this->today()))
            ->assertSessionHas('error', 'Hari '.today()->format('d/m/Y').' sudah ditutup.');
    }

    public function test_the_expense_list_asks_for_closed_days_once_not_per_row(): void
    {
        $categoryId = $this->store->run(fn (): int => ExpenseCategory::query()->where('name', 'Operasional')->value('id'));
        $this->store->run(function () use ($categoryId): void {
            foreach ([1, 2, 3, 4] as $daysAgo) {
                app(ExpenseService::class)->recordFromOtherSource($this->admin, today()->subDays($daysAgo), ['client_uuid' => (string) Str::uuid(), 'expense_category_id' => $categoryId, 'description' => "Listrik {$daysAgo}", 'amount' => 1000]);
            }
        });

        $statements = $this->statementsRunBy(fn () => $this->actingAs($this->admin)->get(route('tenant.expenses.index', ['tenant' => $this->store->code]))->assertOk());

        $this->assertCount(1, array_filter($statements, fn (string $sql): bool => str_contains($sql, 'from `daily_closings`')));
    }

    public function test_the_closing_history_asks_for_its_people_once_however_many_days_it_lists(): void
    {
        $addClosedDay = fn (int $daysAgo) => $this->store->run(fn () => DailyClosing::query()->create(['business_date' => today()->subDays($daysAgo), 'status' => DailyClosingStatus::Closed, 'closed_by' => $this->admin->id, 'closed_at' => now()]));
        $countUserQueries = fn (): int => count(array_filter(
            $this->statementsRunBy(fn () => $this->actingAs($this->admin)->get($this->url('show', $this->today()))->assertOk()),
            fn (string $sql): bool => str_contains($sql, 'from `users`'),
        ));

        $addClosedDay(1);
        $queriesWithOneDay = $countUserQueries();

        $addClosedDay(2);
        $addClosedDay(3);

        $this->assertSame($queriesWithOneDay, $countUserQueries());
    }
}
