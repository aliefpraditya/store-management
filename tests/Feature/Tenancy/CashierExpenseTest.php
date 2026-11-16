<?php

namespace Tests\Feature\Tenancy;

use App\Enums\ApiErrorCode;
use App\Enums\ExpenseSource;
use App\Enums\ExpenseStatus;
use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Store;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class CashierExpenseTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_cashier_expenses';

    private Store $store;

    private User $cashier;

    private CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_cashier_expenses', 'receipt_prefix' => 'TCE']);
        $this->cashier = $this->storeUser();
        $this->shift = $this->openShiftFor($this->cashier, null, '100000');
    }

    private function url(string $name): string
    {
        return route("tenant.cashier.{$name}", ['tenant' => $this->store->code]);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(array $overrides = []): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'expense_category_id' => $this->store->run(fn (): int => ExpenseCategory::query()->where('name', 'Operasional')->value('id')),
            'description' => 'Beli galon',
            'paid_to' => 'Toko Air',
            'amount' => 25000,
            ...$overrides,
        ];
    }

    private function summary(): array
    {
        return $this->store->run(fn (): array => app(ShiftService::class)->summarize($this->shift));
    }

    private function expenseCount(): int
    {
        return $this->store->run(fn (): int => Expense::query()->count());
    }

    public function test_the_dialog_gets_the_active_categories_and_what_this_shift_has_taken(): void
    {
        $this->store->run(fn () => ExpenseCategory::factory()->inactive()->create(['name' => 'Lama']));
        $this->actingAs($this->cashier)->postJson($this->url('expenses.store'), $this->body())->assertCreated();

        $response = $this->actingAs($this->cashier)->getJson($this->url('expenses.index'))->assertOk()
            ->assertJsonCount(6, 'data.categories')
            ->assertJsonMissingPath('data.categories.0.update_url')
            ->assertJsonMissingPath('data.categories.0.expenses_count')
            ->assertJsonCount(1, 'data.expenses')
            ->assertJsonPath('data.expenses.0.description', 'Beli galon')
            ->assertJsonPath('data.expenses.0.category_name', 'Operasional')
            ->assertJsonPath('data.expenses.0.amount', '25000.00')
            ->assertJsonPath('data.expenses.0.status', 'recorded');

        $this->assertNotContains('Lama', array_column($response->json('data.categories'), 'name'));
        $this->assertArrayNotHasKey('void_url', $response->json('data.expenses.0'));
    }

    public function test_a_cashier_takes_money_out_of_their_own_drawer(): void
    {
        $this->actingAs($this->cashier)->postJson($this->url('expenses.store'), $this->body())->assertCreated()
            ->assertJsonPath('data.number', 'PGL/TCE/'.$this->shift->business_date->format('ym').'/0001')
            ->assertJsonPath('data.amount', '25000.00');

        $expense = $this->store->run(fn (): Expense => Expense::query()->sole());
        $this->assertSame([ExpenseSource::Drawer, $this->shift->id, $this->cashier->id, ExpenseStatus::Recorded], [$expense->source, $expense->cash_shift_id, $expense->created_by, $expense->status]);
        $this->assertSame(['25000.00', '75000.00'], [$this->summary()['drawer_expenses'], $this->summary()['expected_cash']]);
    }

    public function test_drawer_expenses_lower_the_expected_cash_but_the_cashier_screen_does_not_show_it(): void
    {
        $this->actingAs($this->cashier)->postJson($this->url('expenses.store'), $this->body())->assertCreated();

        $summary = $this->store->run(fn (): array => app(ShiftService::class)->summarize($this->shift));
        $this->assertSame(['25000.00', '75000.00'], [$summary['drawer_expenses'], $summary['expected_cash']]);

        $this->actingAs($this->cashier)->get($this->url('index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->missing('summary.drawer_expenses')
            ->missing('summary.expected_cash')
            ->where('urls.expenses', $this->url('expenses.index'))
            ->where('urls.expenses_store', $this->url('expenses.store')));
    }

    public function test_closing_the_shift_counts_against_the_cash_left_after_expenses(): void
    {
        $this->actingAs($this->cashier)->postJson($this->url('expenses.store'), $this->body())->assertCreated();

        $closedShift = $this->store->run(fn (): CashShift => app(ShiftService::class)->close($this->shift, '75000', null));

        $this->assertSame(['75000.00', '0.00', '0.00', '75000.00'], [$closedShift->expected_cash, $closedShift->cash_difference, $closedShift->deposit_amount, $closedShift->drawer_left_cash]);
    }

    public function test_a_cashier_cannot_take_money_without_a_running_shift(): void
    {
        $this->store->run(fn () => app(ShiftService::class)->close($this->shift, '100000', null));

        $this->actingAs($this->cashier)->postJson($this->url('expenses.store'), $this->body())->assertStatus(409)
            ->assertJsonPath('code', ApiErrorCode::ShiftNotOpen->value)
            ->assertJsonPath('message', 'Buka shift dulu untuk mencatat pengeluaran dari laci kasir. Bila shift Anda sudah dihitung, tunggu keputusan Admin.')
            ->assertJsonStructure(['code', 'message', 'more_info']);
        $this->actingAs($this->cashier)->getJson($this->url('expenses.index'))->assertStatus(409)->assertJsonPath('code', ApiErrorCode::ShiftNotOpen->value);

        $this->assertSame(0, $this->expenseCount());
    }

    public function test_the_expense_is_validated(): void
    {
        $post = fn (array $overrides) => $this->actingAs($this->cashier)->postJson($this->url('expenses.store'), $this->body($overrides));

        $post(['description' => ''])->assertUnprocessable()->assertJsonValidationErrors('description');
        $post(['amount' => 0])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $post(['amount' => -5000])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $post(['expense_category_id' => null])->assertUnprocessable()->assertJsonValidationErrors('expense_category_id');
        $post(['client_uuid' => null])->assertUnprocessable()->assertJsonValidationErrors('client_uuid');

        $this->assertSame(0, $this->expenseCount());
    }

    public function test_a_cashier_cannot_choose_the_source_or_the_shift(): void
    {
        $otherShift = $this->store->run(fn (): CashShift => CashShift::factory()->create());

        $this->actingAs($this->cashier)->postJson($this->url('expenses.store'), $this->body(['source' => 'other', 'cash_shift_id' => $otherShift->id, 'business_date' => today()->subDays(4)->toDateString()]))->assertCreated();

        $expense = $this->store->run(fn (): Expense => Expense::query()->sole());
        $this->assertSame([ExpenseSource::Drawer, $this->shift->id, today()->toDateString()], [$expense->source, $expense->cash_shift_id, $expense->business_date->toDateString()]);
    }

    public function test_a_repeated_submit_takes_the_money_once(): void
    {
        $body = $this->body();

        $first = $this->actingAs($this->cashier)->postJson($this->url('expenses.store'), $body)->assertCreated()->json('data.id');
        $second = $this->actingAs($this->cashier)->postJson($this->url('expenses.store'), $body)->assertCreated()->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, $this->expenseCount());
        $this->assertSame('25000.00', $this->summary()['drawer_expenses']);
    }

    public function test_an_inactive_category_is_refused(): void
    {
        $category = $this->store->run(fn (): ExpenseCategory => ExpenseCategory::factory()->inactive()->create(['name' => 'Lama']));

        $this->actingAs($this->cashier)->postJson($this->url('expenses.store'), $this->body(['expense_category_id' => $category->id]))->assertUnprocessable()
            ->assertJsonPath('code', ApiErrorCode::ExpenseRefused->value)
            ->assertJsonPath('message', 'Kategori ini sudah dinonaktifkan. Pilih kategori lain.')
            ->assertJsonStructure(['code', 'message', 'more_info']);
    }

    public function test_the_dialog_lists_only_the_expenses_of_this_shift_and_this_drawer(): void
    {
        $this->store->run(function (): void {
            $otherShift = CashShift::factory()->create();
            Expense::factory()->create(['source' => ExpenseSource::Drawer, 'cash_shift_id' => $otherShift->id, 'description' => 'Laci lain']);
            Expense::factory()->create(['description' => 'Bukan dari laci']);
        });
        $this->actingAs($this->cashier)->postJson($this->url('expenses.store'), $this->body())->assertCreated();

        $this->actingAs($this->cashier)->getJson($this->url('expenses.index'))->assertOk()
            ->assertJsonCount(1, 'data.expenses')
            ->assertJsonPath('data.expenses.0.description', 'Beli galon');
        $this->assertSame('25000.00', $this->summary()['drawer_expenses']);
    }

    public function test_a_voided_expense_no_longer_counts_against_the_drawer(): void
    {
        $this->actingAs($this->cashier)->postJson($this->url('expenses.store'), $this->body())->assertCreated();
        $admin = $this->storeUser(UserRole::StoreAdmin);
        $expense = $this->store->run(fn (): Expense => Expense::query()->sole());

        $this->actingAs($admin)->post(route('tenant.expenses.void', ['tenant' => $this->store->code, 'expense' => $expense->id]), ['reason' => 'Salah input'])->assertSessionHasNoErrors();

        $this->assertSame(['0.00', '100000.00'], [$this->summary()['drawer_expenses'], $this->summary()['expected_cash']]);
        $this->actingAs($this->cashier)->getJson($this->url('expenses.index'))
            ->assertJsonPath('data.expenses.0.status', 'voided');
    }
}
