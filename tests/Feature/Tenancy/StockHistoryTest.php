<?php

namespace Tests\Feature\Tenancy;

use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class StockHistoryTest extends TestCase
{
    use PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_history';

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_history']);
        $this->admin = User::factory()->create();
        $this->store->users()->attach($this->admin->id, ['role_id' => Role::idOf(UserRole::StoreAdmin)]);
    }

    private function url(array $query = []): string
    {
        return route('tenant.stock-history.index', ['tenant' => $this->store->code, ...$query]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inStore(callable $callback): mixed
    {
        return $this->store->run($callback);
    }

    private function warehouse(string $code = 'MAIN'): Warehouse
    {
        return $this->inStore(fn () => Warehouse::query()->where('code', $code)->firstOrFail());
    }

    private function makeProduct(string $name): Product
    {
        return $this->inStore(fn () => Product::factory()->create(['name' => $name, 'base_unit_code' => Unit::factory()->create(['code' => strtoupper(substr($name, 0, 3))])->code]));
    }

    private function record(Product $product, string $quantity, ?Warehouse $warehouse = null, StockMovementType $type = StockMovementType::OpnameAdjustment, mixed $reference = null): StockMovement
    {
        $warehouse ??= $this->warehouse();

        return $this->inStore(fn () => app(StockService::class)->record($product->id, $warehouse->id, $type, $quantity, $reference, $this->admin->id));
    }

    public function test_only_admins_may_open_the_stock_history(): void
    {
        $cashier = User::factory()->create();
        $this->store->users()->attach($cashier->id, ['role_id' => Role::idOf(UserRole::Cashier)]);

        $this->actingAs($cashier)->get($this->url())->assertForbidden();
    }

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get($this->url())->assertRedirect(route('login'));
    }

    public function test_recording_a_movement_updates_the_stock_and_keeps_a_running_balance(): void
    {
        $paint = $this->makeProduct('Cat');

        $first = $this->record($paint, '20');
        $second = $this->record($paint, '-8');

        $this->assertSame('20.000', $first->balance_after);
        $this->assertSame('12.000', $second->balance_after);
        $this->assertSame('12.000', $this->inStore(fn () => (string) Stock::query()->where('product_id', $paint->id)->value('quantity')));
    }

    public function test_the_history_lists_movements_newest_first_with_the_document_number(): void
    {
        $paint = $this->makeProduct('Cat');
        $opname = $this->inStore(fn () => StockOpname::factory()->create(['warehouse_id' => $this->warehouse()->id, 'number' => 'OPN/TST/2610/0001']));
        $this->record($paint, '20', null, StockMovementType::OpeningBalance, $opname);
        $this->record($paint, '-8');

        $this->actingAs($this->admin)->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/StockHistory/Index')
            ->has('movements.data', 2)
            ->where('movements.data.0.quantity', '-8.000')
            ->where('movements.data.0.balance_after', '12.000')
            ->where('movements.data.0.document_number', null)
            ->where('movements.data.1.quantity', '20.000')
            ->where('movements.data.1.type_label', 'Stok awal')
            ->where('movements.data.1.document_number', 'OPN/TST/2610/0001')
            ->where('movements.data.1.warehouse', 'Main Warehouse')
            ->where('movements.data.1.product.name', 'Cat')
            ->where('movements.data.1.base_unit', 'CAT')
        );
    }

    public function test_the_history_can_be_filtered_by_product_warehouse_and_dates(): void
    {
        $paint = $this->makeProduct('Cat');
        $brush = $this->makeProduct('Kuas');
        $this->record($paint, '5');
        $this->record($brush, '7');
        $this->record($paint, '3', $this->warehouse('DEAD'));
        $old = $this->record($paint, '1');
        $this->inStore(fn () => StockMovement::query()->whereKey($old->id)->update(['moved_at' => '2026-09-01 10:00:00']));

        $this->actingAs($this->admin);

        $this->get($this->url(['product' => $paint->id]))->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 3)
            ->where('product.name', 'Cat')
            ->where('product.base_unit', 'CAT'));
        $this->get($this->url(['product' => $paint->id, 'warehouse' => $this->warehouse('DEAD')->id]))
            ->assertInertia(fn (Assert $page) => $page->has('movements.data', 1)->where('movements.data.0.quantity', '3.000'));
        $this->get($this->url(['to' => '2026-09-30']))->assertInertia(fn (Assert $page) => $page->has('movements.data', 1)->where('movements.data.0.quantity', '1.000'));
        $this->get($this->url(['from' => '2026-09-02', 'product' => $brush->id]))->assertInertia(fn (Assert $page) => $page->has('movements.data', 1));
    }

    public function test_an_unknown_reference_type_still_lists_without_a_number(): void
    {
        $paint = $this->makeProduct('Cat');
        $movement = $this->record($paint, '5');
        $this->inStore(fn () => StockMovement::query()->whereKey($movement->id)->update(['reference_type' => 'legacy', 'reference_id' => 1]));

        $this->actingAs($this->admin)->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('movements.data.0.document_number', null));
    }

    public function test_a_date_filter_that_is_not_a_date_is_ignored_instead_of_failing(): void
    {
        $paint = $this->makeProduct('Cat');
        $this->record($paint, '5');

        $this->actingAs($this->admin)->get($this->url(['from' => 'abc', 'to' => '31/12/2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.from', null)
                ->where('filters.to', null)
                ->has('movements.data', 1));
    }

    private function recordOn(Product $product, string $quantity, string $movedAt, string $warehouseCode = 'MAIN'): void
    {
        $movement = $this->record($product, $quantity, $this->warehouse($warehouseCode));
        $this->inStore(fn () => StockMovement::query()->whereKey($movement->id)->update(['moved_at' => $movedAt]));
    }

    public function test_the_stock_of_a_product_on_a_day_is_everything_recorded_up_to_then_per_kind_of_warehouse(): void
    {
        $paint = $this->makeProduct('Cat');
        $this->recordOn($paint, '20', '2024-03-01 09:00:00');
        $this->recordOn($paint, '-8', '2024-06-01 10:00:00');
        $this->recordOn($paint, '3', '2024-07-01 10:00:00', 'DEAD');
        $this->recordOn($paint, '2', '2024-08-01 10:00:00', 'RETUR');
        $this->recordOn($paint, '5', '2026-10-01 10:00:00');

        $this->actingAs($this->admin)->get($this->url(['product' => $paint->id, 'to' => '2024-12-31']))->assertInertia(fn (Assert $page) => $page
            ->where('stock_on', [
                'date' => '2024-12-31',
                'sellable' => '12.000',
                'dead_stock' => '3.000',
                'returns' => '2.000',
                'has_records' => true,
                'first_recorded_on' => '2024-03-01',
            ]));
    }

    public function test_the_whole_of_the_chosen_day_counts_but_not_the_day_after(): void
    {
        $paint = $this->makeProduct('Cat');
        $this->recordOn($paint, '10', '2024-06-01 23:30:00');
        $this->recordOn($paint, '4', '2024-06-02 00:00:00');

        $this->actingAs($this->admin)->get($this->url(['product' => $paint->id, 'to' => '2024-06-01']))
            ->assertInertia(fn (Assert $page) => $page->where('stock_on.sellable', '10.000'));
    }

    public function test_a_day_before_the_first_record_has_no_answer_instead_of_zero(): void
    {
        $paint = $this->makeProduct('Cat');
        $this->recordOn($paint, '5', '2026-10-01 10:00:00');

        $this->actingAs($this->admin)->get($this->url(['product' => $paint->id, 'to' => '2024-12-31']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stock_on.has_records', false)
                ->where('stock_on.first_recorded_on', '2026-10-01'));
    }

    public function test_a_product_without_any_record_has_no_answer_and_no_first_day(): void
    {
        $paint = $this->makeProduct('Cat');

        $this->actingAs($this->admin)->get($this->url(['product' => $paint->id, 'to' => '2026-11-01']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stock_on.has_records', false)
                ->where('stock_on.first_recorded_on', null));
    }

    public function test_the_stock_on_a_day_needs_both_a_product_and_a_day(): void
    {
        $paint = $this->makeProduct('Cat');
        $this->record($paint, '5');

        $this->actingAs($this->admin);

        $this->get($this->url(['product' => $paint->id]))->assertInertia(fn (Assert $page) => $page->where('stock_on', null));
        $this->get($this->url(['to' => '2026-12-31']))->assertInertia(fn (Assert $page) => $page->where('stock_on', null));
    }

    public function test_the_history_can_be_narrowed_to_a_warehouse_without_a_product(): void
    {
        $paint = $this->makeProduct('Cat');
        $dead = $this->warehouse('DEAD');
        $this->record($paint, '5');
        $this->record($paint, '2', $dead);

        $this->actingAs($this->admin)->get($this->url(['warehouse' => $dead->id]))
            ->assertInertia(fn (Assert $page) => $page->has('movements.data', 1)->where('movements.data.0.warehouse', 'Dead Stock'));
    }
}
