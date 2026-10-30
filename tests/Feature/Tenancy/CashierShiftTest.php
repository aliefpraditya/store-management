<?php

namespace Tests\Feature\Tenancy;

use App\Enums\CashShiftStatus;
use App\Enums\ShiftDifferenceStatus;
use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->store->run(fn () => Register::query()->where('code', 'K1')->update(['fixed_opening_cash' => 500000]));
    }

    private function url(string $route, array $parameters = []): string
    {
        return route("tenant.cashier.{$route}", ['tenant' => $this->store->code, ...$parameters]);
    }

    private function openShift(User $user, ?int $registerId = null): TestResponse
    {
        return $this->actingAs($user)->post($this->url('shifts.store'), ['register_id' => $registerId ?? $this->register()->id]);
    }

    private function closeShift(User $user, CashShift $shift, mixed $countedCash, ?string $note = null): TestResponse
    {
        return $this->actingAs($user)->post($this->url('shifts.close', ['shift' => $shift->id]), ['counted_cash' => $countedCash, 'closing_note' => $note]);
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
            ->where('registers.0.fixed_opening_cash', '500000.00')
            ->where('registers.0.busy_with', null)
            ->has('payment_methods', 4)
            ->where('payment_methods.0', ['value' => 'cash', 'label' => 'Tunai'])
            ->where('can_leave_to_dashboard', false));
    }

    public function test_opening_a_shift_takes_the_fixed_opening_cash_of_the_register(): void
    {
        $cashier = $this->storeUser();

        $this->openShift($cashier)->assertRedirect($this->url('index'))->assertSessionHas('success', 'Shift dibuka.');

        $shift = $this->shiftOf($cashier);
        $this->assertSame([CashShiftStatus::Open, '500000.00', $cashier->name, now()->toDateString()], [$shift->status, $shift->opening_cash, $shift->cashier_name, $shift->business_date->toDateString()]);

        $this->actingAs($cashier)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('shift.id', $shift->id)
            ->where('shift.register.code', 'K1')
            ->where('shift.opening_cash', '500000.00')
            ->where('summary.expected_cash', '500000.00')
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

    public function test_closing_with_the_exact_cash_has_no_difference_and_a_deposit_above_the_fixed_cash(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $shift = $this->shiftOf($cashier);

        $this->closeShift($cashier, $shift, '500000')
            ->assertRedirect($this->url('index'))
            ->assertSessionHas('success', 'Shift ditutup.')
            ->assertSessionHas('closed_shift', ['expected_cash' => '500000.00', 'counted_cash' => '500000.00', 'cash_difference' => '0.00', 'deposit_amount' => '0.00']);

        $closed = $this->shiftOf($cashier);
        $this->assertSame([CashShiftStatus::Closed, ShiftDifferenceStatus::None], [$closed->status, $closed->difference_status]);
        $this->assertNotNull($closed->closed_at);
        $this->assertNull($this->store->run(fn () => app(ShiftService::class)->openShiftOf($cashier)));
    }

    public function test_a_difference_needs_a_note_and_waits_for_approval(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $shift = $this->shiftOf($cashier);

        $this->closeShift($cashier, $shift, '490000')->assertSessionHas('error', 'Ada selisih kas, isi catatan penutupan.');
        $this->assertSame(CashShiftStatus::Open, $this->shiftOf($cashier)->status);

        $this->closeShift($cashier, $shift, '490000', 'uang kembalian kurang')->assertSessionHas('success');

        $closed = $this->shiftOf($cashier);
        $this->assertSame(['-10000.00', ShiftDifferenceStatus::PendingApproval, 'uang kembalian kurang'], [$closed->cash_difference, $closed->difference_status, $closed->closing_note]);
    }

    public function test_the_deposit_is_the_counted_cash_above_the_fixed_opening_cash(): void
    {
        $cashier = $this->storeUser();
        $this->openShift($cashier);
        $shift = $this->shiftOf($cashier);

        $this->closeShift($cashier, $shift, '500000');
        $this->assertSame('0.00', $this->shiftOf($cashier)->deposit_amount);

        $other = $this->storeUser();
        $this->openShift($other);
        $this->closeShift($other, $this->shiftOf($other), '620000', 'lebih');
        $this->assertSame('120000.00', $this->shiftOf($other)->deposit_amount);
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
}
