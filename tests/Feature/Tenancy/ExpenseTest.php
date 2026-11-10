<?php

namespace Tests\Feature\Tenancy;

use App\Enums\ExpenseSource;
use App\Enums\ExpenseStatus;
use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_expenses';

    private Store $store;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_expenses', 'receipt_prefix' => 'TEX']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.expenses.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

    private function category(string $name = 'Operasional'): ExpenseCategory
    {
        return $this->store->run(fn (): ExpenseCategory => ExpenseCategory::query()->where('name', $name)->firstOrFail());
    }

    /**
     * An expense as the form sends it; `$overrides` replaces any field.
     *
     * @return array<string, mixed>
     */
    private function body(array $overrides = []): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'expense_category_id' => $this->category()->id,
            'source' => 'other',
            'business_date' => today()->toDateString(),
            'description' => 'Beli galon air minum',
            'paid_to' => 'Toko Air',
            'amount' => 50000,
            ...$overrides,
        ];
    }

    /**
     * The cashier's shift on a register that opens with 100.000 in the drawer.
     */
    private function openShiftWithFloat(): CashShift
    {
        return $this->openShiftFor($this->cashier, null, '100000');
    }

    private function closeShift(CashShift $shift, string $countedCash): void
    {
        $this->store->run(fn () => app(ShiftService::class)->close($shift, $countedCash, null));
    }

    private function expectedCash(CashShift $shift): string
    {
        return $this->store->run(fn (): string => app(ShiftService::class)->summarize($shift->fresh())['expected_cash']);
    }

    private function expenseCount(): int
    {
        return $this->store->run(fn (): int => Expense::query()->count());
    }

    private function onlyExpense(): Expense
    {
        return $this->store->run(fn (): Expense => Expense::query()->sole());
    }

    private function recordDrawerExpense(CashShift $shift, int $amount = 20000): Expense
    {
        $this->actingAs($this->admin)->post($this->url('store'), $this->body(['source' => 'drawer', 'cash_shift_id' => $shift->id, 'amount' => $amount]))->assertSessionHasNoErrors();

        return $this->store->run(fn (): Expense => Expense::query()->latest('id')->firstOrFail());
    }

    private function statusOf(Expense $expense): ExpenseStatus
    {
        return $this->reloaded($expense)->status;
    }

    /**
     * @template TModel of Expense|ExpenseCategory
     *
     * @param  TModel  $model
     * @return TModel
     */
    private function reloaded(Expense|ExpenseCategory $model): Expense|ExpenseCategory
    {
        return $this->store->run(fn () => $model->fresh());
    }

    public function test_only_admins_may_use_the_expense_pages(): void
    {
        $expense = $this->store->run(fn (): Expense => Expense::factory()->create());

        $this->actingAs($this->cashier)->get($this->url('index'))->assertForbidden();
        $this->actingAs($this->cashier)->post($this->url('store'), $this->body())->assertForbidden();
        $this->actingAs($this->cashier)->post($this->url('void', ['expense' => $expense->id]), ['reason' => 'Salah'])->assertForbidden();
        $this->actingAs($this->cashier)->post($this->url('categories.store'), ['name' => 'Baru'])->assertForbidden();

        $this->assertSame(1, $this->expenseCount());
    }

    public function test_every_store_starts_with_the_usual_categories(): void
    {
        $names = $this->store->run(fn (): array => ExpenseCategory::query()->orderBy('name')->pluck('name')->all());

        $this->assertSame(['ATK/Perlengkapan', 'Lain-lain', 'Listrik/Air', 'Operasional', 'Transport/Bensin', 'Upah/Gaji'], $names);
    }

    public function test_an_admin_records_an_expense_from_another_source(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));
        $this->actingAs($this->admin)->post($this->url('store'), $this->body())->assertSessionHasNoErrors()->assertSessionHas('success', 'Pengeluaran dicatat.');

        $expense = $this->onlyExpense();

        $this->assertSame('PGL/TEX/2611/0001', $expense->number);
        $this->assertSame([ExpenseSource::Other, ExpenseStatus::Recorded, null, '50000.00', today()->toDateString()], [$expense->source, $expense->status, $expense->cash_shift_id, $expense->amount, $expense->business_date->toDateString()]);
        $this->assertSame([$this->admin->id, 'Beli galon air minum', 'Toko Air'], [$expense->created_by, $expense->description, $expense->paid_to]);
    }

    public function test_an_expense_from_another_source_keeps_the_day_it_was_paid(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), $this->body(['business_date' => today()->subDays(3)->toDateString()]))->assertSessionHasNoErrors();

        $this->assertSame(today()->subDays(3)->toDateString(), $this->onlyExpense()->business_date->toDateString());
    }

    public function test_an_expense_from_a_running_drawer_lowers_the_expected_cash_of_that_shift(): void
    {
        $shift = $this->openShiftWithFloat();
        $this->assertSame('100000.00', $this->expectedCash($shift));

        $this->recordDrawerExpense($shift, 20000);

        $expense = $this->onlyExpense();
        $this->assertSame([ExpenseSource::Drawer, $shift->id, $shift->business_date->toDateString()], [$expense->source, $expense->cash_shift_id, $expense->business_date->toDateString()]);
        $this->assertSame('80000.00', $this->expectedCash($shift));
    }

    public function test_an_expense_from_another_source_does_not_touch_the_drawer(): void
    {
        $shift = $this->openShiftWithFloat();

        $this->actingAs($this->admin)->post($this->url('store'), $this->body())->assertSessionHasNoErrors();

        $this->assertSame('100000.00', $this->expectedCash($shift));
    }

    public function test_a_drawer_expense_needs_a_running_shift(): void
    {
        $shift = $this->openShiftWithFloat();
        $this->closeShift($shift, '100000');

        $this->actingAs($this->admin)->post($this->url('store'), $this->body(['source' => 'drawer', 'cash_shift_id' => $shift->id]))
            ->assertSessionHas('error', 'Buka shift dulu untuk mencatat pengeluaran dari laci kasir. Bila shift Anda sudah dihitung, tunggu keputusan Admin.');

        $this->assertSame(0, $this->expenseCount());
    }

    public function test_the_expense_is_validated(): void
    {
        $post = fn (array $overrides) => $this->actingAs($this->admin)->post($this->url('store'), $this->body($overrides));

        $post(['description' => ''])->assertSessionHasErrors(['description' => 'Keterangan wajib diisi.']);
        $post(['amount' => 0])->assertSessionHasErrors(['amount' => 'Nominal harus lebih dari nol.']);
        $post(['amount' => 'banyak'])->assertSessionHasErrors('amount');
        $post(['expense_category_id' => 999999])->assertSessionHasErrors('expense_category_id');
        $post(['business_date' => today()->addDay()->toDateString()])->assertSessionHasErrors(['business_date' => 'Tanggal pengeluaran tidak boleh di masa depan.']);
        $post(['business_date' => null])->assertSessionHasErrors('business_date');
        $post(['source' => 'drawer', 'cash_shift_id' => null])->assertSessionHasErrors(['cash_shift_id' => 'Pilih laci shift yang dipakai.']);
        $post(['source' => 'bank'])->assertSessionHasErrors('source');
        $post(['client_uuid' => 'bukan-uuid'])->assertSessionHasErrors('client_uuid');

        $this->assertSame(0, $this->expenseCount());
    }

    public function test_a_repeated_submit_records_the_expense_once(): void
    {
        $body = $this->body();

        $this->actingAs($this->admin)->post($this->url('store'), $body)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post($this->url('store'), $body)->assertSessionHasNoErrors();

        $this->assertSame(1, $this->expenseCount());
    }

    public function test_a_marker_used_by_someone_else_is_refused(): void
    {
        $this->store->run(fn () => Expense::factory()->create(['client_uuid' => 'a0000000-0000-4000-8000-000000000001', 'created_by' => $this->cashier->id]));

        $this->actingAs($this->admin)->post($this->url('store'), $this->body(['client_uuid' => 'a0000000-0000-4000-8000-000000000001']))
            ->assertSessionHas('error', 'Pengeluaran ini sudah tercatat untuk orang atau sumber lain. Muat ulang layar lalu coba lagi.');

        $this->assertSame(1, $this->expenseCount());
    }

    public function test_an_inactive_category_cannot_be_used(): void
    {
        $category = $this->store->run(fn (): ExpenseCategory => ExpenseCategory::factory()->inactive()->create(['name' => 'Lama']));

        $this->actingAs($this->admin)->post($this->url('store'), $this->body(['expense_category_id' => $category->id]))
            ->assertSessionHas('error', 'Kategori ini sudah dinonaktifkan. Pilih kategori lain.');

        $this->assertSame(0, $this->expenseCount());
    }

    public function test_expense_numbers_run_on_without_gaps(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));
        $this->actingAs($this->admin)->post($this->url('store'), $this->body());
        $this->actingAs($this->admin)->post($this->url('store'), $this->body());

        $numbers = $this->store->run(fn (): array => Expense::query()->orderBy('id')->pluck('number')->all());

        $this->assertSame(['PGL/TEX/2611/0001', 'PGL/TEX/2611/0002'], $numbers);
    }

    public function test_an_admin_voids_an_expense_with_a_reason(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), $this->body())->assertSessionHasNoErrors();
        $expense = $this->onlyExpense();

        $this->actingAs($this->admin)->post($this->url('void', ['expense' => $expense->id]), ['reason' => 'Salah input'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Pengeluaran dibatalkan.');

        $expense = $this->reloaded($expense);
        $this->assertSame([ExpenseStatus::Voided, 'Salah input', $this->admin->name], [$expense->status, $expense->void_reason, $expense->voided_by_name]);
        $this->assertNotNull($expense->voided_at);
    }

    public function test_voiding_needs_a_reason_and_works_only_once(): void
    {
        $expense = $this->store->run(fn (): Expense => Expense::factory()->create());
        $void = $this->url('void', ['expense' => $expense->id]);

        $this->actingAs($this->admin)->post($void, ['reason' => ''])->assertSessionHasErrors(['reason' => 'Isi alasan pembatalan.']);
        $this->assertSame(ExpenseStatus::Recorded, $this->reloaded($expense)->status);

        $this->actingAs($this->admin)->post($void, ['reason' => 'Salah'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post($void, ['reason' => 'Lagi'])->assertSessionHas('error', 'Pengeluaran ini sudah dibatalkan.');
        $this->assertSame('Salah', $this->reloaded($expense)->void_reason);
    }

    public function test_voiding_a_drawer_expense_of_a_running_shift_gives_the_cash_back(): void
    {
        $shift = $this->openShiftWithFloat();
        $drawerExpense = $this->recordDrawerExpense($shift, 20000);
        $this->assertSame('80000.00', $this->expectedCash($shift));

        $this->actingAs($this->admin)->post($this->url('void', ['expense' => $drawerExpense->id]), ['reason' => 'Salah input'])->assertSessionHasNoErrors();

        $this->assertSame('100000.00', $this->expectedCash($shift));
    }

    public function test_a_drawer_expense_of_a_closed_shift_cannot_be_voided(): void
    {
        $shift = $this->openShiftWithFloat();
        $drawerExpense = $this->recordDrawerExpense($shift, 20000);
        $this->closeShift($shift, '80000');

        $this->actingAs($this->admin)->post($this->url('void', ['expense' => $drawerExpense->id]), ['reason' => 'Salah'])
            ->assertSessionHas('error', 'Shift pengeluaran ini sudah ditutup, jadi tidak bisa dibatalkan. Selisih kasnya diputuskan di Rekap Shift.');

        $this->assertSame(ExpenseStatus::Recorded, $this->statusOf($drawerExpense));
    }

    public function test_the_list_shows_expenses_newest_first_with_the_totals(): void
    {
        $shift = $this->openShiftWithFloat();
        $this->recordDrawerExpense($shift, 20000);
        $this->actingAs($this->admin)->post($this->url('store'), $this->body(['amount' => 50000, 'description' => 'Listrik']))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post($this->url('store'), $this->body(['amount' => 7000, 'description' => 'Salah']))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post($this->url('void', ['expense' => $this->store->run(fn () => Expense::query()->latest('id')->value('id'))]), ['reason' => 'Salah'])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get($this->url('index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Expenses/Index')
            ->has('expenses.data', 3)
            ->where('expenses.data.0.description', 'Salah')
            ->where('expenses.data.0.status', 'voided')
            ->where('expenses.data.0.can_be_voided', false)
            ->where('expenses.data.1.description', 'Listrik')
            ->where('expenses.data.1.can_be_voided', true)
            ->where('expenses.data.2.drawer_label', 'K1 · '.$this->cashier->name)
            ->where('totals', ['drawer' => '20000.00', 'other' => '50000.00', 'total' => '70000.00'])
            ->has('categories', 6)
            ->where('open_shifts', [['id' => $shift->id, 'label' => 'K1 · '.$this->cashier->name]]));
    }

    public function test_the_list_is_narrowed_by_category_source_status_day_and_search(): void
    {
        $fuel = $this->category('Transport/Bensin');
        $this->store->run(function () use ($fuel): void {
            Expense::factory()->create(['description' => 'Bensin pickup', 'paid_to' => 'SPBU', 'expense_category_id' => $fuel->id, 'amount' => 100000, 'business_date' => today()->subDays(5)]);
            Expense::factory()->create(['description' => 'Galon', 'paid_to' => 'Toko Air', 'expense_category_id' => $this->category()->id, 'amount' => 30000]);
            Expense::factory()->create(['description' => 'Batal', 'status' => ExpenseStatus::Voided, 'amount' => 9000]);
        });

        $assertRows = fn (array $query, array $descriptions, string $total) => $this->actingAs($this->admin)->get($this->url('index', $query))
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenses.data', fn ($rows) => collect($rows)->pluck('description')->sort()->values()->all() === collect($descriptions)->sort()->values()->all())
                ->where('totals.total', $total));

        $assertRows(['category' => $fuel->id], ['Bensin pickup'], '100000.00');
        $assertRows(['status' => 'voided'], ['Batal'], '0.00');
        $assertRows(['status' => 'recorded'], ['Bensin pickup', 'Galon'], '130000.00');
        $assertRows(['from' => today()->subDays(2)->toDateString()], ['Galon', 'Batal'], '30000.00');
        $assertRows(['to' => today()->subDays(2)->toDateString()], ['Bensin pickup'], '100000.00');
        $assertRows(['search' => 'SPBU'], ['Bensin pickup'], '100000.00');
        $assertRows(['source' => 'drawer'], [], '0.00');
        $assertRows(['source' => 'other'], ['Bensin pickup', 'Galon', 'Batal'], '130000.00');
    }

    public function test_an_admin_adds_renames_and_switches_off_categories(): void
    {
        $this->actingAs($this->admin)->post($this->url('categories.store'), ['name' => 'Sewa Gudang'])->assertSessionHasNoErrors();
        $category = $this->category('Sewa Gudang');
        $this->assertTrue($category->is_active);

        $this->actingAs($this->admin)->put($this->url('categories.update', ['category' => $category->id]), ['name' => 'Sewa Toko', 'is_active' => true])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put($this->url('categories.update', ['category' => $category->id]), ['name' => 'Sewa Toko', 'is_active' => false])->assertSessionHasNoErrors();

        $category = $this->reloaded($category);
        $this->assertSame(['Sewa Toko', false], [$category->name, $category->is_active]);
    }

    public function test_category_names_are_unique(): void
    {
        $this->actingAs($this->admin)->post($this->url('categories.store'), ['name' => 'Operasional'])->assertSessionHasErrors(['name' => 'Nama kategori sudah dipakai.']);
        $this->actingAs($this->admin)->post($this->url('categories.store'), ['name' => ''])->assertSessionHasErrors(['name' => 'Nama kategori wajib diisi.']);

        $category = $this->category('Listrik/Air');
        $this->actingAs($this->admin)->put($this->url('categories.update', ['category' => $category->id]), ['name' => 'Operasional'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->put($this->url('categories.update', ['category' => $category->id]), ['name' => 'Listrik/Air'])->assertSessionHasNoErrors();
    }

    public function test_a_marker_reused_for_another_source_is_refused_instead_of_handing_back_the_first_expense(): void
    {
        $shift = $this->openShiftWithFloat();
        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)->post($this->url('store'), $this->body(['client_uuid' => $uuid]))->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post($this->url('store'), $this->body(['client_uuid' => $uuid, 'source' => 'drawer', 'cash_shift_id' => $shift->id]))
            ->assertSessionHas('error', 'Pengeluaran ini sudah tercatat untuk orang atau sumber lain. Muat ulang layar lalu coba lagi.');

        $this->assertSame(1, $this->expenseCount());
        $this->assertSame('100000.00', $this->expectedCash($shift));
    }

    public function test_a_marker_reused_for_another_drawer_is_refused(): void
    {
        $shift = $this->openShiftWithFloat();
        $uuid = (string) Str::uuid();
        $this->actingAs($this->admin)->post($this->url('store'), $this->body(['client_uuid' => $uuid, 'source' => 'drawer', 'cash_shift_id' => $shift->id]))->assertSessionHasNoErrors();
        $secondRegister = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));
        $otherShift = $this->openShiftFor($this->storeUser(), $secondRegister);

        $this->actingAs($this->admin)->post($this->url('store'), $this->body(['client_uuid' => $uuid, 'source' => 'drawer', 'cash_shift_id' => $otherShift->id]))
            ->assertSessionHas('error');

        $this->assertSame(1, $this->expenseCount());
    }

    public function test_whether_an_expense_can_be_voided_is_decided_in_one_place(): void
    {
        $shift = $this->openShiftWithFloat();
        $drawerExpense = $this->recordDrawerExpense($shift);
        $this->actingAs($this->admin)->post($this->url('store'), $this->body())->assertSessionHasNoErrors();
        $otherExpense = $this->store->run(fn (): Expense => Expense::query()->where('source', 'other')->sole());

        $blockers = fn (Expense $expense) => $this->store->run(fn () => $expense->fresh(['cashShift'])->voidBlocker()?->getMessage());

        $this->assertNull($blockers($drawerExpense));
        $this->assertNull($blockers($otherExpense));

        $this->closeShift($shift, '80000');
        $this->assertSame('Shift pengeluaran ini sudah ditutup, jadi tidak bisa dibatalkan. Selisih kasnya diputuskan di Rekap Shift.', $blockers($drawerExpense));
        $this->assertNull($blockers($otherExpense));

        $this->actingAs($this->admin)->post($this->url('void', ['expense' => $otherExpense->id]), ['reason' => 'Salah'])->assertSessionHas('success');
        $this->assertSame('Pengeluaran ini sudah dibatalkan.', $blockers($otherExpense));
    }

    public function test_the_void_address_is_a_noun(): void
    {
        $this->assertStringEndsWith('/expenses/5/voids', $this->url('void', ['expense' => 5]));
    }

    public function test_the_default_categories_are_added_to_a_store_that_has_none_and_existing_ones_are_left_alone(): void
    {
        $this->store->run(fn () => ExpenseCategory::query()->delete());
        $this->store->run(fn (): ExpenseCategory => ExpenseCategory::factory()->inactive()->create(['name' => 'Operasional']));
        $migration = require base_path('database/migrations/tenant/2026_11_02_160000_add_default_expense_categories_to_existing_stores.php');

        $this->store->run(fn () => $migration->up());
        $this->store->run(fn () => $migration->up());

        $categories = $this->store->run(fn (): array => ExpenseCategory::query()->orderBy('name')->pluck('is_active', 'name')->all());
        $this->assertSame(['ATK/Perlengkapan', 'Lain-lain', 'Listrik/Air', 'Operasional', 'Transport/Bensin', 'Upah/Gaji'], array_keys($categories));
        $this->assertFalse($categories['Operasional']);
        $this->assertTrue($categories['Lain-lain']);
    }

    public function test_the_category_list_carries_the_counts_and_the_address_to_change_each(): void
    {
        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->has('categories', 6)
            ->where('categories.0.expenses_count', 0)
            ->whereType('categories.0.update_url', 'string'));
    }
}
