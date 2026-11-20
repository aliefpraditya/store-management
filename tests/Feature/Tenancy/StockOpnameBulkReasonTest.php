<?php

namespace Tests\Feature\Tenancy;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameStatus;
use App\Enums\UserRole;
use App\Enums\WarehouseType;
use App\Models\OpnameReason;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

/**
 * One reason given to every variance of a routine opname at once, instead of picking it row by row before posting.
 */
class StockOpnameBulkReasonTest extends TestCase
{
    use PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_opname_bulk';

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_opname_bulk', 'receipt_prefix' => 'TOB']);
        $this->admin = $this->userWithRole(UserRole::StoreAdmin);
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->store->users()->attach($user->id, ['role_id' => Role::idOf($role)]);

        return $user;
    }

    private function reason(string $name): OpnameReason
    {
        return $this->store->run(fn (): OpnameReason => OpnameReason::query()->where('name', $name)->firstOrFail());
    }

    private function warehouse(): Warehouse
    {
        return $this->store->run(fn () => Warehouse::query()->where('code', 'MAIN')->firstOrFail());
    }

    private function productWithStock(string $name, string $stock): Product
    {
        return $this->store->run(function () use ($name, $stock): Product {
            $pail = Unit::query()->firstOrCreate(['code' => 'PAIL'], ['name' => 'Pail']);
            $product = Product::factory()->create(['name' => $name, 'base_unit_code' => $pail->code, 'cost_price' => '1000']);
            ProductUnit::factory()->create(['product_id' => $product->id, 'unit_code' => $pail->code, 'conversion_factor' => 1, 'sort_order' => 0]);
            Stock::query()->updateOrInsert(['product_id' => $product->id, 'warehouse_id' => $this->warehouse()->id], ['quantity' => $stock]);

            return $product;
        });
    }

    private function opname(StockOpnameStatus $status = StockOpnameStatus::Review, StockOpnameReason $reason = StockOpnameReason::Routine): StockOpname
    {
        return $this->store->run(fn () => StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse()->id,
            'reason_code' => $reason,
            'status' => $status,
            'snapshot_at' => now(),
            'created_by' => $this->admin->id,
        ]));
    }

    private function addItem(StockOpname $opname, Product $product, string $counted, ?OpnameReason $reason = null): StockOpnameItem
    {
        return $this->store->run(fn () => StockOpnameItem::factory()->create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'snapshot_quantity' => '10',
            'counted_quantity' => $counted,
            'reason_id' => $reason?->id,
        ]));
    }

    private function applyReason(StockOpname $opname, ?OpnameReason $reason, ?User $by = null): TestResponse
    {
        return $this->actingAs($by ?? $this->admin)->post(
            route('tenant.stock-opnames.apply-reason', ['tenant' => $this->store->code, 'stock_opname' => $opname->id]),
            ['reason_id' => $reason?->id],
        );
    }

    private function fresh(StockOpnameItem $item): StockOpnameItem
    {
        return $this->store->run(fn () => $item->fresh());
    }

    public function test_one_reason_goes_to_every_variance_that_has_none_and_marks_them_reviewed(): void
    {
        $opname = $this->opname();
        $short = $this->addItem($opname, $this->productWithStock('Cat', '10'), '7');
        $over = $this->addItem($opname, $this->productWithStock('Kuas', '10'), '12');

        $this->applyReason($opname, $this->reason('Cek stok'))->assertSessionHas('success', '2 selisih diberi alasan dan ditandai sudah direview.');

        foreach ([$short, $over] as $item) {
            $this->assertSame($this->reason('Cek stok')->id, $this->fresh($item)->reason_id);
            $this->assertTrue($this->fresh($item)->isReviewed());
            $this->assertSame($this->admin->id, $this->fresh($item)->reviewed_by);
        }
    }

    public function test_items_that_match_or_already_have_a_reason_are_left_alone(): void
    {
        $opname = $this->opname();
        $matching = $this->addItem($opname, $this->productWithStock('Cat', '10'), '10');
        $explained = $this->addItem($opname, $this->productWithStock('Kuas', '10'), '7', $this->reason('Hilang'));
        $notCounted = $this->store->run(fn () => StockOpnameItem::factory()->create(['stock_opname_id' => $opname->id, 'product_id' => $this->productWithStock('Roller', '10')->id]));

        $this->applyReason($opname, $this->reason('Cek stok'))->assertSessionHas('success', 'Tidak ada selisih yang belum diberi alasan.');

        $this->assertNull($this->fresh($matching)->reason_id);
        $this->assertNull($this->fresh($notCounted)->reason_id);
        $this->assertSame($this->reason('Hilang')->id, $this->fresh($explained)->reason_id);
        $this->assertFalse($this->fresh($explained)->isReviewed());
    }

    public function test_the_variance_is_judged_against_the_live_stock(): void
    {
        $opname = $this->opname();
        $product = $this->productWithStock('Cat', '10');
        $soldSince = $this->addItem($opname, $product, '7');
        $this->store->run(fn () => Stock::query()->where('product_id', $product->id)->update(['quantity' => '7']));

        $this->applyReason($opname, $this->reason('Cek stok'))->assertSessionHas('success', 'Tidak ada selisih yang belum diberi alasan.');

        $this->assertNull($this->fresh($soldSince)->reason_id);
    }

    public function test_the_damaged_reason_goes_to_the_shortages_only_and_leaves_them_to_be_decided(): void
    {
        $opname = $this->opname();
        $short = $this->addItem($opname, $this->productWithStock('Cat', '10'), '7');
        $over = $this->addItem($opname, $this->productWithStock('Kuas', '10'), '12');

        $this->applyReason($opname, $this->reason('Rusak'))
            ->assertSessionHas('success', '1 selisih kurang diberi alasan. Tentukan ke mana barang rusaknya di tiap baris.');

        $this->assertSame($this->reason('Rusak')->id, $this->fresh($short)->reason_id);
        $this->assertFalse($this->fresh($short)->isReviewed());
        $this->assertNull($this->fresh($over)->reason_id);
    }

    public function test_the_damaged_reason_is_refused_outside_a_sellable_warehouse(): void
    {
        $opname = $this->opname();
        $item = $this->addItem($opname, $this->productWithStock('Cat', '10'), '7');
        $this->store->run(fn () => $this->warehouse()->update(['type' => WarehouseType::DeadStock]));

        $this->applyReason($opname, $this->reason('Rusak'))->assertSessionHas('error');

        $this->assertNull($this->fresh($item)->reason_id);
    }

    public function test_a_switched_off_reason_is_refused(): void
    {
        $opname = $this->opname();
        $item = $this->addItem($opname, $this->productWithStock('Cat', '10'), '7');
        $this->store->run(fn () => $this->reason('Cek stok')->update(['is_active' => false]));

        $this->applyReason($opname, $this->reason('Cek stok'))->assertSessionHasErrors('reason_id');
        $this->applyReason($opname, null)->assertSessionHasErrors('reason_id');

        $this->assertNull($this->fresh($item)->reason_id);
    }

    public function test_it_only_works_while_a_routine_opname_is_in_review(): void
    {
        foreach ([
            [StockOpnameStatus::Counting, StockOpnameReason::Routine],
            [StockOpnameStatus::Review, StockOpnameReason::StockIncrease],
            [StockOpnameStatus::Review, StockOpnameReason::ReturnsCheck],
        ] as [$status, $opnameReason]) {
            $opname = $this->opname($status, $opnameReason);
            $item = $this->addItem($opname, $this->productWithStock("Cat {$status->value} {$opnameReason->value}", '10'), '7');

            $this->applyReason($opname, $this->reason('Cek stok'))->assertSessionHas('error');

            $this->assertNull($this->fresh($item)->reason_id, "{$status->value} {$opnameReason->value}");
        }
    }

    public function test_only_who_may_post_can_give_the_reason(): void
    {
        $opname = $this->opname();
        $item = $this->addItem($opname, $this->productWithStock('Cat', '10'), '7');

        $this->applyReason($opname, $this->reason('Cek stok'), $this->userWithRole(UserRole::Cashier))->assertForbidden();

        $this->assertNull($this->fresh($item)->reason_id);
    }

    public function test_an_opname_explained_this_way_can_be_posted(): void
    {
        $opname = $this->opname();
        $product = $this->productWithStock('Cat', '10');
        $this->addItem($opname, $product, '7');

        $this->applyReason($opname, $this->reason('Cek stok'));
        $this->actingAs($this->admin)->post(route('tenant.stock-opnames.post', ['tenant' => $this->store->code, 'stock_opname' => $opname->id]))->assertSessionHas('success');

        $this->assertSame(StockOpnameStatus::Posted, $this->store->run(fn () => $opname->fresh()->status));
        $this->assertSame('7.000', $this->store->run(fn () => (string) Stock::query()->where('product_id', $product->id)->value('quantity')));
    }
}
