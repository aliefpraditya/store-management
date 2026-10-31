<?php

namespace Tests\Feature\Tenancy;

use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\Product;
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
        $this->store->users()->attach($this->admin->id, ['role' => UserRole::StoreAdmin->value]);
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
        $this->store->users()->attach($cashier->id, ['role' => UserRole::Cashier->value]);

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
}
