<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DeliveryNoteStatus;
use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class StoreDashboardTest extends TestCase
{
    use PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_dashboard';

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_dashboard', 'name' => 'Toko Uji', 'receipt_prefix' => 'TU']);
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->store->users()->attach($user->id, ['role' => $role->value]);

        return $user;
    }

    private function dashboardAs(User $user): TestResponse
    {
        return $this->actingAs($user)->get(route('tenant.dashboard', ['tenant' => $this->store->code]));
    }

    private function adminDashboard(): TestResponse
    {
        return $this->dashboardAs($this->userWithRole(UserRole::StoreAdmin));
    }

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('tenant.dashboard', ['tenant' => $this->store->code]))->assertRedirect(route('login'));
    }

    public function test_store_admin_sees_the_store_and_an_empty_summary(): void
    {
        $this->adminDashboard()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Dashboard')
                ->where('store.code', 'test_dashboard')
                ->where('store.name', 'Toko Uji')
                ->where('store.receipt_prefix', 'TU')
                ->where('store.dashboard_url', route('tenant.dashboard', ['tenant' => 'test_dashboard']))
                ->where('summary.sales', ['gross_total' => '0.00', 'returns_total' => '0.00', 'net_total' => '0.00', 'receipt_count' => 0])
                ->where('summary.low_stock_count', 0)
                ->where('summary.pending', ['returns' => 0, 'cash_differences' => 0, 'cash_difference_follow_ups' => 0, 'delivery_notes' => 0, 'total' => 0])
                ->has('summary.approvals', 0)
                ->has('summary.cash_difference_follow_ups', 0)
                ->has('summary.unfinished_delivery_notes', 0)
                ->has('summary.open_shifts', 0)
            );
    }

    public function test_super_admin_sees_the_dashboard_of_any_store(): void
    {
        $this->dashboardAs(User::factory()->create(['is_super_admin' => true]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tenant/Dashboard')->has('summary'));
    }

    public function test_cashier_is_sent_to_the_cashier_screen_and_never_sees_dashboard_numbers(): void
    {
        $this->dashboardAs($this->userWithRole(UserRole::Cashier))
            ->assertRedirect(route('tenant.cashier.index', ['tenant' => $this->store->code]));
    }

    public function test_net_sales_are_todays_completed_receipts_minus_completed_returns(): void
    {
        $this->store->run(function (): void {
            Sale::factory()->create(['total' => 100000]);
            Sale::factory()->create(['total' => 250000]);
            Sale::factory()->voided()->create(['total' => 999999]);
            $earlier = Sale::factory()->create(['total' => 777777, 'business_date' => today()->subDay()]);

            $returnOf = fn (array $attributes) => SaleReturn::factory()->create([
                'sale_id' => $earlier->id,
                'register_id' => $earlier->register_id,
                'cash_shift_id' => $earlier->cash_shift_id,
                ...$attributes,
            ]);

            $returnOf(['credit_amount' => 50000])->forceFill(['status' => 'completed'])->save();
            $returnOf(['credit_amount' => 70000]);
            $returnOf(['credit_amount' => 30000, 'returned_at' => now()->subDay()])->forceFill(['status' => 'completed'])->save();
        });

        $this->adminDashboard()->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales', [
                'gross_total' => '350000.00',
                'returns_total' => '50000.00',
                'net_total' => '300000.00',
                'receipt_count' => 2,
            ])
        );
    }

    public function test_low_stock_counts_active_products_under_their_minimum_in_sellable_warehouses(): void
    {
        $this->store->run(function (): void {
            $sellable = Warehouse::factory()->create();
            $deadStock = Warehouse::factory()->deadStock()->create();

            $stockOf = function (Product $product, Warehouse $warehouse, float $quantity): void {
                Stock::factory()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => $quantity]);
            };

            $low = Product::factory()->create(['min_stock' => 10]);
            $stockOf($low, $sellable, 4);

            $exactlyAtMinimum = Product::factory()->create(['min_stock' => 10]);
            $stockOf($exactlyAtMinimum, $sellable, 10);

            $onlyDeadStockIsPlentiful = Product::factory()->create(['min_stock' => 10]);
            $stockOf($onlyDeadStockIsPlentiful, $sellable, 4);
            $stockOf($onlyDeadStockIsPlentiful, $deadStock, 100);

            Product::factory()->create(['min_stock' => 5]);
            Product::factory()->create(['min_stock' => 10, 'is_active' => false]);
            Product::factory()->create(['min_stock' => 0]);
        });

        $this->adminDashboard()->assertInertia(fn (Assert $page) => $page->where('summary.low_stock_count', 3));
    }

    public function test_pending_work_is_counted_and_listed(): void
    {
        $this->store->run(function (): void {
            $sale = Sale::factory()->voided()->create();

            $returnOf = fn (array $attributes = []) => SaleReturn::factory()->create([
                'sale_id' => $sale->id,
                'register_id' => $sale->register_id,
                'cash_shift_id' => $sale->cash_shift_id,
                ...$attributes,
            ]);

            $returnOf(['number' => 'RET/0001', 'credit_amount' => 450000]);
            $returnOf(['number' => 'RET/0002']);
            $returnOf(['number' => 'RET/0003'])->forceFill(['status' => 'completed'])->save();

            CashShift::factory()->closedWithPendingDifference(-10000)->create();
            CashShift::factory()->create(['difference_status' => 'approved']);
            CashShift::factory()->closedWithPendingDifference(-2500)->create(['difference_status' => 'rejected', 'cashier_name' => 'Budi']);
            CashShift::factory()->closedWithPendingDifference(-1000)->create(['difference_status' => 'rejected', 'followed_up_at' => now()]);

            foreach ([DeliveryNoteStatus::Draft, DeliveryNoteStatus::Shipped, DeliveryNoteStatus::Delivered, DeliveryNoteStatus::Cancelled] as $status) {
                DeliveryNote::factory()->withStatus($status)->create(['sale_id' => $sale->id]);
            }
        });

        $this->adminDashboard()->assertInertia(fn (Assert $page) => $page
            ->where('summary.pending', ['returns' => 2, 'cash_differences' => 1, 'cash_difference_follow_ups' => 1, 'delivery_notes' => 2, 'total' => 6])
            ->has('summary.cash_difference_follow_ups', 1)
            ->where('summary.cash_difference_follow_ups.0.cashier_name', 'Budi')
            ->where('summary.cash_difference_follow_ups.0.amount', '-2500.00')
            ->where('summary.cash_difference_follow_ups.0.show_url', fn (string $url) => str_contains($url, "/{$this->store->code}/shifts/"))
            ->has('summary.approvals', 3)
            ->where('summary.approvals.0', ['kind' => 'return', 'reference' => 'RET/0002', 'amount' => '50000.00'])
            ->where('summary.approvals.1', ['kind' => 'return', 'reference' => 'RET/0001', 'amount' => '450000.00'])
            ->where('summary.approvals.2.kind', 'cash_difference')
            ->where('summary.approvals.2.amount', '-10000.00')
            ->where('summary.approvals.2.reference', fn (string $reference) => str_starts_with($reference, 'Shift #'))
            ->has('summary.unfinished_delivery_notes', 2)
            ->where('summary.unfinished_delivery_notes.0.status_label', 'Draft')
            ->where('summary.unfinished_delivery_notes.1.status_label', 'Dikirim')
            ->where('summary.unfinished_delivery_notes.0.show_url', fn (string $url) => str_contains($url, "/{$this->store->code}/delivery-notes/"))
        );
    }

    public function test_only_running_shifts_are_listed_with_their_register(): void
    {
        $this->store->run(function (): void {
            $register = Register::factory()->create(['name' => 'Kasir 1']);

            CashShift::factory()->create(['register_id' => $register->id, 'cashier_name' => 'Sari']);
            CashShift::factory()->closedWithPendingDifference()->create(['cashier_name' => 'Budi']);
        });

        $this->adminDashboard()->assertInertia(fn (Assert $page) => $page
            ->has('summary.open_shifts', 1)
            ->where('summary.open_shifts.0.cashier_name', 'Sari')
            ->where('summary.open_shifts.0.register_name', 'Kasir 1')
        );
    }
}
