<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserHasNoOpenShift;
use App\Models\CashShift;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

/**
 * A user with an open shift cannot log out or open the central pages until the shift is closed. Store pages stay
 * open so an admin can still look up stock in the middle of a shift.
 */
class OpenShiftLockTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_shift_lock';

    private const LOCKED_MESSAGE = 'Tutup shift dulu sebelum keluar atau membuka halaman pusat.';

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_shift_lock']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
    }

    private function url(string $name, array $parameters = []): string
    {
        return route($name, ['tenant' => $this->store->code, ...$parameters]);
    }

    /**
     * Opens a shift the way the screen does: the post, then the redirect back to the till.
     */
    private function openShiftThroughTheScreen(User $user): void
    {
        $this->actingAs($user)->post($this->url('tenant.cashier.shifts.store'), ['register_id' => $this->register()->id])->assertSessionHas('success');
        $this->actingAs($user)->get($this->url('tenant.cashier.index'))->assertOk();
    }

    private function closeShiftThroughTheScreen(User $user): void
    {
        $shift = $this->store->run(fn () => CashShift::query()->where('cashier_id', $user->id)->latest('id')->firstOrFail());

        $this->actingAs($user)->post($this->url('tenant.cashier.shifts.close', ['shift' => $shift->id]), ['counted_cash' => '0'])->assertSessionHas('success');
        $this->actingAs($user)->get($this->url('tenant.cashier.index'))->assertOk();
    }

    private function assertSentToTheTill(TestResponse $response): void
    {
        $response->assertRedirect($this->url('tenant.cashier.index'))->assertSessionHas('error', self::LOCKED_MESSAGE);
    }

    public function test_store_pages_stay_open_while_a_shift_is_open_so_an_admin_can_check_stock(): void
    {
        $this->openShiftThroughTheScreen($this->admin);

        foreach (['tenant.dashboard', 'tenant.products.index', 'tenant.stock-opnames.index', 'tenant.stock-history.index', 'tenant.units.index'] as $route) {
            $this->actingAs($this->admin)->get($this->url($route))->assertOk();
        }

        $this->actingAs($this->admin)->getJson($this->url('tenant.products.search', ['q' => 'kuas']))->assertOk();
    }

    public function test_store_pages_know_a_shift_is_open_so_they_can_offer_the_way_back_to_the_till(): void
    {
        $this->actingAs($this->admin)->get($this->url('tenant.products.index'))
            ->assertInertia(fn (Assert $page) => $page->where('has_open_shift', false));

        $this->openShiftThroughTheScreen($this->admin);

        $this->actingAs($this->admin)->get($this->url('tenant.products.index'))
            ->assertInertia(fn (Assert $page) => $page->where('has_open_shift', true)->where('store.cashier_url', $this->url('tenant.cashier.index')));
    }

    public function test_logging_out_is_refused_while_a_shift_is_open(): void
    {
        $this->openShiftThroughTheScreen($this->admin);

        $this->assertSentToTheTill($this->actingAs($this->admin)->post(route('logout')));
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_the_central_pages_are_closed_while_a_shift_is_open(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->openShiftThroughTheScreen($superAdmin);

        foreach (['central.dashboard', 'central.stores.index', 'central.users.index'] as $route) {
            $this->assertSentToTheTill($this->actingAs($superAdmin)->get(route($route)));
        }
    }

    public function test_a_json_request_to_a_central_page_is_refused_with_a_message(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->openShiftThroughTheScreen($superAdmin);

        $this->actingAs($superAdmin)->getJson(route('central.users.index'))
            ->assertStatus(423)
            ->assertJsonPath('message', self::LOCKED_MESSAGE)
            ->assertJsonStructure(['code', 'message', 'more_info']);
    }

    public function test_a_shift_in_another_store_stays_remembered_when_a_store_page_is_opened(): void
    {
        $this->actingAs($this->admin)
            ->withSession([EnsureUserHasNoOpenShift::SESSION_KEY => ['other_store']])
            ->get($this->url('tenant.products.index'))
            ->assertSessionHas(EnsureUserHasNoOpenShift::SESSION_KEY, ['other_store']);

        $this->openShiftFor($this->admin);

        $this->actingAs($this->admin)
            ->withSession([EnsureUserHasNoOpenShift::SESSION_KEY => ['other_store']])
            ->get($this->url('tenant.products.index'))
            ->assertSessionHas(EnsureUserHasNoOpenShift::SESSION_KEY, ['other_store', $this->store->code]);
    }

    public function test_logout_and_the_central_pages_open_again_once_the_shift_is_closed(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->openShiftThroughTheScreen($superAdmin);
        $this->closeShiftThroughTheScreen($superAdmin);

        $this->actingAs($superAdmin)->get(route('central.dashboard'))->assertOk();
        $this->actingAs($superAdmin)->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_a_shift_left_open_locks_again_as_soon_as_the_user_opens_a_store_page(): void
    {
        $this->openShiftFor($this->admin);

        $this->actingAs($this->admin)->get($this->url('tenant.products.index'))->assertOk();

        $this->assertSentToTheTill($this->actingAs($this->admin)->post(route('logout')));
    }

    public function test_someone_elses_open_shift_does_not_lock_you(): void
    {
        $this->openShiftThroughTheScreen($this->admin);
        $otherAdmin = $this->storeUser(UserRole::StoreAdmin);

        $this->actingAs($otherAdmin)->get($this->url('tenant.products.index'))->assertOk();
        $this->actingAs($otherAdmin)->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_a_flag_without_a_real_open_shift_is_dropped(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($superAdmin)
            ->withSession([EnsureUserHasNoOpenShift::SESSION_KEY => [$this->store->code]])
            ->get(route('central.dashboard'))
            ->assertOk()
            ->assertSessionMissing(EnsureUserHasNoOpenShift::SESSION_KEY);
    }

    public function test_a_cashier_without_a_shift_may_log_out(): void
    {
        $this->actingAs($this->storeUser())->post(route('logout'))->assertRedirect();

        $this->assertGuest();
    }
}
