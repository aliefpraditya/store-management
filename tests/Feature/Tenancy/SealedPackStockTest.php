<?php

namespace Tests\Feature\Tenancy;

use App\Enums\OpnameReasonBehavior;
use App\Enums\StockMovementType;
use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Enums\UserRole;
use App\Exceptions\CustomerOrderException;
use App\Exceptions\StockOpnameException;
use App\Models\CashShift;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\OpnameReason;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierCatalogService;
use App\Services\CustomerOrderService;
use App\Services\ProductService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\SaleVoidService;
use App\Services\StockOpnameReport;
use App\Services\StockOpnameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

/**
 * A roll of wire is a sealed pack: once metres are cut from it, it can no longer be sold as a whole roll. The stock stays one
 * quantity in metres, with a count of the rolls that are still whole. The shelf in these tests is the one from the
 * shop floor: 400 m, of which 3 whole rolls and 100 m loose (two leftovers of 50 m).
 */
class SealedPackStockTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_sealed_pack';

    private Store $store;

    private User $cashier;

    private User $admin;

    private CashShift $shift;

    private Product $wire;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_sealed_pack', 'receipt_prefix' => 'TSP']);
        $this->cashier = $this->storeUser();
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->shift = $this->openShiftFor($this->cashier);
        $this->wire = $this->wireProduct();
    }

    /**
     * @param  list<array{0: string, 1: string|int|float}>  $lines  unit code and quantity
     */
    private function sell(array $lines, string $paymentMethod = 'cash', int $amount = 1000000): TestResponse
    {
        return $this->actingAs($this->cashier)->post(route('tenant.cashier.sales.store', ['tenant' => $this->store->code]), [
            'client_uuid' => (string) Str::uuid(),
            'items' => array_map(fn (array $line): array => ['product_unit_id' => $this->unitOf($this->wire, $line[0])->id, 'quantity' => $line[1]], $lines),
            'payments' => [['method' => $paymentMethod, 'amount' => $amount]],
        ]);
    }

    private function sellCod(array $lines, int $total): Sale
    {
        return $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => array_map(fn (array $line): array => ['product_unit_id' => $this->unitOf($this->wire, $line[0])->id, 'quantity' => $line[1]], $lines),
            'payments' => [['method' => 'cod', 'amount' => $total]],
            'delivery_note' => ['recipient_name' => 'Pak Budi', 'recipient_address' => 'Jl. Mawar 5'],
        ]));
    }

    public function test_four_rolls_cannot_be_sold_when_only_three_are_whole_even_though_400_m_is_there(): void
    {
        $this->sell([['ROL', 4]])->assertSessionHas('error', 'Kawat: ROL utuh tidak cukup, tersisa 3 ROL utuh. Sisanya potongan eceran, jual per satuan yang lebih kecil.');

        $this->assertSame(['quantity' => '400.000', 'intact_packs' => 3], $this->shelfOf($this->wire));
        $this->assertSame(0, $this->store->run(fn () => Sale::query()->count()));
    }

    public function test_the_whole_rolls_can_all_be_sold_and_the_loose_metres_stay(): void
    {
        $this->sell([['ROL', 3]])->assertSessionHas('last_sale');

        $this->assertSame(['quantity' => '100.000', 'intact_packs' => 0], $this->shelfOf($this->wire));

        $movement = $this->store->run(fn () => StockMovement::query()->where('type', StockMovementType::Sale)->sole());
        $this->assertSame([-3, 0], [$movement->pack_change, $movement->packs_after]);
    }

    public function test_metres_are_cut_from_the_loose_stock_first_and_no_roll_is_opened(): void
    {
        $this->sell([['MTR', 40]])->assertSessionHas('last_sale');

        $this->assertSame(['quantity' => '360.000', 'intact_packs' => 3], $this->shelfOf($this->wire));
    }

    public function test_metres_beyond_the_loose_stock_open_a_whole_roll(): void
    {
        $this->sell([['MTR', 150]])->assertSessionHas('last_sale');

        $this->assertSame(['quantity' => '250.000', 'intact_packs' => 2], $this->shelfOf($this->wire));

        $movement = $this->store->run(fn () => StockMovement::query()->where('type', StockMovementType::Sale)->sole());
        $this->assertSame([-1, 2], [$movement->pack_change, $movement->packs_after]);
    }

    public function test_a_roll_that_was_opened_can_no_longer_be_sold_whole(): void
    {
        $this->sell([['MTR', 150]]);

        $this->sell([['ROL', 3]])->assertSessionHas('error', 'Stok Kawat tidak cukup, tersisa 250 MTR.');
        $this->sell([['ROL', 2]])->assertSessionHas('last_sale');

        $this->assertSame(['quantity' => '50.000', 'intact_packs' => 0], $this->shelfOf($this->wire));
    }

    public function test_cutting_50_m_and_then_75_m_from_three_whole_rolls_opens_a_roll_each_time_the_pieces_run_out(): void
    {
        $wire = $this->wireProduct('Kawat Tiga Rol', '300', 3);
        $sell = fn (int $metres) => $this->actingAs($this->cashier)->post(route('tenant.cashier.sales.store', ['tenant' => $this->store->code]), [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($wire, 'MTR')->id, 'quantity' => $metres]],
            'payments' => [['method' => 'cash', 'amount' => 1000000]],
        ])->assertSessionHas('last_sale');

        $sell(50);
        $this->assertSame(['quantity' => '250.000', 'intact_packs' => 2], $this->shelfOf($wire));

        // 50 m of pieces cover 50 of the 75 m; the other 25 m open the next roll, leaving 1 whole roll and 75 m of pieces (25 + 50).
        $sell(75);
        $this->assertSame(['quantity' => '175.000', 'intact_packs' => 1], $this->shelfOf($wire));
    }

    public function test_rolls_and_metres_on_one_receipt_share_the_same_shelf(): void
    {
        $this->sell([['ROL', 3], ['MTR', 100]])->assertSessionHas('last_sale');
        $this->assertSame(['quantity' => '0.000', 'intact_packs' => 0], $this->shelfOf($this->wire));
    }

    public function test_the_total_in_metres_is_still_checked(): void
    {
        $this->sell([['ROL', 3], ['MTR', 101]])->assertSessionHas('error', 'Stok Kawat tidak cukup, tersisa 400 MTR.');
    }

    public function test_a_sealed_pack_is_sold_in_whole_numbers(): void
    {
        $this->sell([['ROL', 1.5]])->assertSessionHas('error', 'Kawat dijual per ROL utuh, jumlahnya harus bilangan bulat. Untuk sebagian, jual per satuan yang lebih kecil.');
    }

    public function test_a_product_without_a_sealed_pack_unit_keeps_selling_by_total_quantity(): void
    {
        $brush = $this->sellableProduct('Kuas', '100');

        $this->actingAs($this->cashier)->post(route('tenant.cashier.sales.store', ['tenant' => $this->store->code]), [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($brush, 'DUS')->id, 'quantity' => 8]],
            'payments' => [['method' => 'cash', 'amount' => 1000000]],
        ])->assertSessionHas('last_sale');

        $this->assertSame('4.000', $this->stockInPieces($brush));
        $this->assertSame(0, $this->shelfOf($brush)['intact_packs']);
    }

    public function test_voiding_a_sale_puts_whole_rolls_back_as_whole_rolls(): void
    {
        $sale = $this->sellCod([['ROL', 1], ['MTR', 150]], total: 240000);
        $this->assertSame(['quantity' => '150.000', 'intact_packs' => 1], $this->shelfOf($this->wire));

        $this->store->run(fn () => app(SaleVoidService::class)->void($sale, $this->cashier, 'Batal', null));

        // The roll that was opened for the 150 m stays opened: its pieces come back loose.
        $this->assertSame(['quantity' => '400.000', 'intact_packs' => 2], $this->shelfOf($this->wire));
    }

    public function test_a_returned_roll_waits_in_the_returns_warehouse_as_a_whole_roll_and_returned_metres_do_not(): void
    {
        $this->sell([['ROL', 1], ['MTR', 30]]);
        $sale = $this->store->run(fn () => Sale::query()->with('items')->sole());
        $rollLine = $sale->items->firstWhere('unit_name', 'ROL');
        $metreLine = $sale->items->firstWhere('unit_name', 'MTR');
        $shelfAfterSale = $this->shelfOf($this->wire);

        $this->store->run(fn () => app(SaleReturnService::class)->createApproved($sale, $this->shift, $this->admin, [
            'client_uuid' => (string) Str::uuid(),
            'refund_method' => 'cash',
            'items' => [
                ['sale_item_id' => $rollLine->id, 'quantity' => 1],
                ['sale_item_id' => $metreLine->id, 'quantity' => 30],
            ],
        ]));

        $this->assertSame($shelfAfterSale, $this->shelfOf($this->wire));
        $this->assertSame(['quantity' => '130.000', 'intact_packs' => 1], $this->returnsShelfOf($this->wire));
    }

    public function test_inspecting_the_returns_warehouse_puts_the_roll_back_among_the_whole_rolls_and_the_metres_loose(): void
    {
        $this->sell([['ROL', 1], ['MTR', 30]]);
        $sale = $this->store->run(fn () => Sale::query()->with('items')->sole());
        $this->store->run(fn () => app(SaleReturnService::class)->createApproved($sale, $this->shift, $this->admin, [
            'client_uuid' => (string) Str::uuid(),
            'refund_method' => 'cash',
            'items' => $sale->items->map(fn ($line): array => ['sale_item_id' => $line->id, 'quantity' => $line->quantity])->all(),
        ]));
        $shelfAfterSale = $this->shelfOf($this->wire);

        $opname = $this->store->run(function () {
            $opnames = app(StockOpnameService::class);
            ['opname' => $opname] = $opnames->create([
                'warehouse_id' => $this->returnsWarehouse()->id,
                'opname_date' => now()->toDateString(),
                'scope' => StockOpnameScope::Selected->value,
                'reason_code' => StockOpnameReason::ReturnsCheck->value,
            ], $this->admin->id);
            $itemId = StockOpnameItem::query()->where('stock_opname_id', $opname->id)->value('id');

            $opnames->saveCounts($opname, [['item_id' => $itemId, 'reason_id' => null, 'note' => null, 'parts' => [['unit_code' => 'ROL', 'quantity' => '1'], ['unit_code' => 'MTR', 'quantity' => '30']]]]);
            $opnames->finishCounting($opname);
            $opnames->saveReview($opname, $this->admin->id, [['item_id' => $itemId, 'reason_id' => null, 'note' => null, 'is_reviewed' => true, 'dead_stock_quantity' => '0', 'damaged_goods_quantity' => '0', 'full_price_quantity' => '130', 'damaged_goods_price' => null]]);
            $opnames->post($opname, $this->admin->id);

            return $opname;
        });

        $this->assertSame(['quantity' => '0.000', 'intact_packs' => 0], $this->returnsShelfOf($this->wire));
        $this->assertSame(['quantity' => '400.000', 'intact_packs' => $shelfAfterSale['intact_packs'] + 1], $this->shelfOf($this->wire));
    }

    public function test_an_opname_counts_whole_rolls_apart_from_loose_metres(): void
    {
        $opname = $this->opnameCounting(['ROL' => '3', 'MTR' => '100']);

        $item = $this->store->run(fn () => StockOpnameItem::query()->where('stock_opname_id', $opname->id)->sole());
        $this->assertSame(['400.000', 3], [$item->counted_quantity, $item->counted_packs]);
    }

    public function test_an_opname_refuses_part_of_a_roll_counted_as_a_roll(): void
    {
        $this->expectException(StockOpnameException::class);

        $this->opnameCounting(['ROL' => '2.5']);
    }

    public function test_posting_an_opname_corrects_the_number_of_whole_rolls_even_when_the_metres_match(): void
    {
        $this->store->run(fn () => Stock::query()->where('product_id', $this->wire->id)->update(['intact_packs' => 4]));

        $opname = $this->opnameCounting(['ROL' => '3', 'MTR' => '100']);
        $this->store->run(fn () => app(StockOpnameService::class)->post($opname, $this->admin->id));

        $this->assertSame(['quantity' => '400.000', 'intact_packs' => 3], $this->shelfOf($this->wire));

        $movement = $this->store->run(fn () => StockMovement::query()->where('type', StockMovementType::OpnameAdjustment)->sole());
        $this->assertSame(['0.000', -1, 3], [$movement->quantity, $movement->pack_change, $movement->packs_after]);
    }

    public function test_a_count_typed_in_metres_only_is_flagged_for_the_whole_rolls_it_loses(): void
    {
        $opname = $this->opnameCounting(['MTR' => '400']);

        $this->assertSame(
            [['product_name' => 'Kawat', 'pack_unit' => 'ROL', 'system_packs' => 3, 'counted_packs' => 0]],
            $this->store->run(fn () => app(StockOpnameReport::class)->sealedPackDrops($opname)),
        );
    }

    public function test_a_count_that_keeps_the_whole_rolls_is_not_flagged(): void
    {
        $opname = $this->opnameCounting(['ROL' => '3', 'MTR' => '100']);

        $this->assertSame([], $this->store->run(fn () => app(StockOpnameReport::class)->sealedPackDrops($opname)));
    }

    public function test_the_review_page_hands_the_drops_to_the_confirmation_before_posting(): void
    {
        $opname = $this->opnameCounting(['ROL' => '1', 'MTR' => '300']);

        $this->actingAs($this->admin)
            ->get(route('tenant.stock-opnames.show', ['tenant' => $this->store->code, 'stock_opname' => $opname->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sealed_pack_drops', [['product_name' => 'Kawat', 'pack_unit' => 'ROL', 'system_packs' => 3, 'counted_packs' => 1]])
                ->where('items.data.0.system_intact_packs', 3)
                ->where('items.data.0.counted_packs', 1));
    }

    public function test_the_opening_balance_of_a_roll_product_starts_the_whole_roll_count(): void
    {
        $product = $this->wireProduct('Kawat Baru', '0', 0);
        $opname = $this->opnameCounting(['ROL' => '3', 'MTR' => '100'], $product, StockOpnameReason::OpeningBalance);
        $this->store->run(fn () => app(StockOpnameService::class)->post($opname, $this->admin->id));

        $this->assertSame(['quantity' => '400.000', 'intact_packs' => 3], $this->shelfOf($product));
    }

    public function test_setting_a_sealed_pack_unit_on_an_item_with_stock_starts_counting_its_whole_rolls(): void
    {
        $product = $this->wireProduct('Kawat Lama', '450', 0);
        $this->store->run(fn () => ProductUnit::query()->where('product_id', $product->id)->update(['is_sealed_pack' => false]));

        $this->store->run(fn () => app(ProductService::class)->update($product, $this->formOf($product, isSealedPack: true)));

        $this->assertSame(['quantity' => '450.000', 'intact_packs' => 4], $this->shelfOf($product));

        $this->store->run(fn () => app(ProductService::class)->update($product->refresh(), $this->formOf($product, isSealedPack: false)));

        $this->assertSame(['quantity' => '450.000', 'intact_packs' => 0], $this->shelfOf($product));
    }

    public function test_changing_the_sealed_pack_unit_of_an_item_with_stock_leaves_movements_so_the_pack_chain_holds(): void
    {
        $product = $this->wireProduct('Kawat Lama', '450', 0);
        $this->store->run(fn () => ProductUnit::query()->where('product_id', $product->id)->update(['is_sealed_pack' => false]));

        $this->store->run(fn () => app(ProductService::class)->update($product, $this->formOf($product, isSealedPack: true)));
        $this->store->run(fn () => app(ProductService::class)->update($product->refresh(), $this->formOf($product, isSealedPack: false)));

        $movements = $this->store->run(fn () => StockMovement::query()->where('product_id', $product->id)->orderBy('id')->get());

        $this->assertSame([[4, 4], [-4, 0]], $movements->map(fn (StockMovement $movement): array => [$movement->pack_change, $movement->packs_after])->all());
        $this->assertSame(['0.000', '0.000'], $movements->map(fn (StockMovement $movement): string => (string) $movement->quantity)->all());
        $this->assertSame([StockMovementType::OpnameAdjustment], $movements->pluck('type')->unique()->values()->all());
    }

    public function test_the_item_form_refuses_a_sealed_pack_that_is_not_bigger_than_the_base_unit(): void
    {
        $form = [...$this->formOf($this->wire, isSealedPack: true), 'sku' => 'KW-NEW', 'version' => null];
        $form['units'][1]['conversion_factor'] = 1;

        $this->actingAs($this->admin)
            ->post(route('tenant.products.store', ['tenant' => $this->store->code]), $form)
            ->assertSessionHasErrors(['units.1.is_sealed_pack']);
    }

    public function test_a_roll_set_aside_for_a_ready_order_is_not_sold_whole_to_someone_else(): void
    {
        $this->shelfIs('300', 2);
        $this->orderOfRolls(2, isReady: true);

        $this->sell([['ROL', 1]])->assertSessionHas('error', 'Kawat: ROL utuh tidak cukup, tersisa 0 ROL utuh. Sisanya potongan eceran, jual per satuan yang lebih kecil.');
        $this->sell([['MTR', 100]])->assertSessionHas('last_sale');

        $this->assertSame(['quantity' => '200.000', 'intact_packs' => 2], $this->shelfOf($this->wire));
    }

    public function test_the_order_that_set_the_rolls_aside_can_still_collect_them(): void
    {
        $this->shelfIs('300', 2);
        $order = $this->orderOfRolls(2, isReady: true);

        $this->actingAs($this->cashier)->post(route('tenant.cashier.sales.store', ['tenant' => $this->store->code]), [
            'client_uuid' => (string) Str::uuid(),
            'customer_order_id' => $order->id,
            'payments' => [['method' => 'cash', 'amount' => 180000]],
        ])->assertSessionHas('last_sale');

        $this->assertSame(['quantity' => '100.000', 'intact_packs' => 0], $this->shelfOf($this->wire));
    }

    public function test_an_order_cannot_be_marked_arrived_when_other_ready_orders_hold_the_whole_rolls(): void
    {
        $this->shelfIs('300', 2);
        $this->orderOfRolls(2, isReady: true);
        $waiting = $this->orderOfRolls(1, isReady: false);

        $this->expectException(CustomerOrderException::class);
        $this->expectExceptionMessage('Kawat: ROL utuh belum cukup untuk pesanan ini, tersedia 0 ROL utuh.');

        $this->store->run(fn () => app(CustomerOrderService::class)->markReady($waiting));
    }

    public function test_the_cashier_catalog_leaves_out_the_rolls_and_metres_set_aside_for_orders(): void
    {
        $this->orderOfRolls(1, isReady: true);
        $this->assertSame(['stock' => '300.000', 'intact_packs' => 2], $this->catalogOfWire());

        $this->shelfIs('400', 3);
        $this->store->run(fn () => CustomerOrderItem::query()->update(['is_sealed_pack' => false, 'quantity' => 150, 'base_quantity' => 150]));

        // 250 m can be sold, which holds 2 whole rolls: the screen must not claim 3 rolls and -50 m.
        $this->assertSame(['stock' => '250.000', 'intact_packs' => 2], $this->catalogOfWire());
    }

    public function test_voiding_an_old_receipt_after_the_roll_unit_was_released_adds_no_whole_rolls(): void
    {
        $sale = $this->sellCod([['ROL', 1]], total: 90000);
        $this->store->run(function (): void {
            ProductUnit::query()->where('product_id', $this->wire->id)->update(['is_sealed_pack' => false]);
            Stock::query()->where('product_id', $this->wire->id)->update(['intact_packs' => 0]);
        });

        $this->store->run(fn () => app(SaleVoidService::class)->void($sale, $this->cashier, 'Batal', null));

        $this->assertSame(['quantity' => '400.000', 'intact_packs' => 0], $this->shelfOf($this->wire));
    }

    private function shelfIs(string $metres, int $wholeRolls): void
    {
        $this->store->run(fn () => Stock::query()->where('product_id', $this->wire->id)->update(['quantity' => $metres, 'intact_packs' => $wholeRolls]));
    }

    private function orderOfRolls(int $rolls, bool $isReady): CustomerOrder
    {
        return $this->store->run(function () use ($rolls, $isReady): CustomerOrder {
            $orders = app(CustomerOrderService::class);
            $order = $orders->create($this->shift, $this->cashier, [
                'client_uuid' => (string) Str::uuid(),
                'customer_name' => 'Pak Budi',
                'customer_phone' => '08123456789',
                'items' => [['product_unit_id' => $this->unitOf($this->wire, 'ROL')->id, 'quantity' => $rolls]],
            ]);

            return $isReady ? $orders->markReady($order) : $order;
        });
    }

    /**
     * @return array{stock: string, intact_packs: int|null}
     */
    private function catalogOfWire(): array
    {
        return $this->store->run(function (): array {
            $item = app(CashierCatalogService::class)->search($this->mainWarehouse()->id, 'Kawat')[0];

            return ['stock' => $item['stock'], 'intact_packs' => $item['intact_packs']];
        });
    }

    public function test_the_item_form_refuses_the_base_unit_as_the_sealed_pack(): void
    {
        $this->actingAs($this->admin)
            ->post(route('tenant.products.store', ['tenant' => $this->store->code]), [...$this->formOf($this->wire, isSealedPack: true, hasSealedBase: true), 'sku' => 'KW-NEW', 'version' => null])
            ->assertSessionHasErrors(['units.0.is_sealed_pack']);
    }

    public function test_a_returned_roll_sent_to_dead_stock_stays_a_roll_there_and_the_loose_metres_go_back_on_the_shelf(): void
    {
        $shelfAfterSale = $this->inspectReturnedRollAndMetres(['dead_stock_quantity' => '100', 'dead_stock_packs' => 1, 'damaged_goods_quantity' => '0', 'full_price_quantity' => '30', 'full_price_packs' => 0]);

        $this->assertSame(['quantity' => '100.000', 'intact_packs' => 1], $this->stockIn($this->wire, 'DEAD'));
        $this->assertSame(['quantity' => (string) ($shelfAfterSale['quantity'] + 30).'.000', 'intact_packs' => $shelfAfterSale['intact_packs']], $this->shelfOf($this->wire));
        $this->assertSame(['quantity' => '0.000', 'intact_packs' => 0], $this->returnsShelfOf($this->wire));
    }

    public function test_a_returned_roll_sold_on_as_damaged_goods_is_a_roll_of_the_damaged_goods_product(): void
    {
        $this->inspectReturnedRollAndMetres(['dead_stock_quantity' => '0', 'damaged_goods_quantity' => '100', 'damaged_goods_packs' => 1, 'damaged_goods_price' => '500', 'full_price_quantity' => '30', 'full_price_packs' => 0]);

        $twin = $this->store->run(fn () => Product::query()->where('damaged_from_product_id', $this->wire->id)->with('productUnits')->sole());

        $this->assertSame('ROL', $twin->sealedPackUnit()?->unit_code);
        $this->assertSame(['quantity' => '100.000', 'intact_packs' => 1], $this->stockIn($twin, 'MAIN'));
    }

    public function test_a_roll_kept_on_the_shelf_goes_back_as_a_whole_roll_when_the_decision_says_so(): void
    {
        $shelfAfterSale = $this->inspectReturnedRollAndMetres(['dead_stock_quantity' => '30', 'dead_stock_packs' => 0, 'damaged_goods_quantity' => '0', 'full_price_quantity' => '100', 'full_price_packs' => 1]);

        $this->assertSame(['quantity' => '30.000', 'intact_packs' => 0], $this->stockIn($this->wire, 'DEAD'));
        $this->assertSame(['quantity' => (string) ($shelfAfterSale['quantity'] + 100).'.000', 'intact_packs' => $shelfAfterSale['intact_packs'] + 1], $this->shelfOf($this->wire));
    }

    public function test_a_roll_cannot_be_divided_when_the_returns_warehouse_was_counted_in_metres_only(): void
    {
        $this->expectException(StockOpnameException::class);
        $this->expectExceptionMessage('ROL utuh yang dibagi (1) lebih banyak dari yang ada (0)');

        $this->inspectReturnedRollAndMetres(
            ['dead_stock_quantity' => '100', 'dead_stock_packs' => 1, 'damaged_goods_quantity' => '0', 'full_price_quantity' => '30', 'full_price_packs' => 0],
            [['unit_code' => 'MTR', 'quantity' => '130']],
        );
    }

    public function test_a_roll_cannot_be_made_out_of_fewer_metres_than_a_roll(): void
    {
        $this->expectException(StockOpnameException::class);
        $this->expectExceptionMessage('ROL utuh lebih banyak dari yang muat di jumlah barangnya');

        $this->inspectReturnedRollAndMetres(['dead_stock_quantity' => '30', 'dead_stock_packs' => 1, 'damaged_goods_quantity' => '0', 'full_price_quantity' => '100', 'full_price_packs' => 0]);
    }

    public function test_a_lost_roll_found_damaged_in_a_routine_count_goes_to_dead_stock_as_a_roll(): void
    {
        $opname = $this->opnameCounting(['ROL' => '2', 'MTR' => '100']);
        $damaged = $this->store->run(fn () => OpnameReason::query()->where('behavior', OpnameReasonBehavior::Damaged)->firstOrFail());

        $this->store->run(function () use ($opname, $damaged): void {
            $opnames = app(StockOpnameService::class);
            $itemId = StockOpnameItem::query()->where('stock_opname_id', $opname->id)->value('id');

            $opnames->saveReview($opname, $this->admin->id, [[
                'item_id' => $itemId, 'reason_id' => $damaged->id, 'note' => null, 'is_reviewed' => true,
                'dead_stock_quantity' => '100', 'damaged_goods_quantity' => '0', 'full_price_quantity' => '0', 'damaged_goods_price' => null,
                'dead_stock_packs' => 1, 'damaged_goods_packs' => 0, 'full_price_packs' => 0,
            ]]);
            $opnames->post($opname, $this->admin->id);
        });

        $this->assertSame(['quantity' => '300.000', 'intact_packs' => 2], $this->shelfOf($this->wire));
        $this->assertSame(['quantity' => '100.000', 'intact_packs' => 1], $this->stockIn($this->wire, 'DEAD'));
    }

    public function test_a_roll_cannot_go_to_dead_stock_when_the_count_lost_no_roll(): void
    {
        $opname = $this->opnameCounting(['ROL' => '3', 'MTR' => '0']);
        $damaged = $this->store->run(fn () => OpnameReason::query()->where('behavior', OpnameReasonBehavior::Damaged)->firstOrFail());

        $this->expectException(StockOpnameException::class);
        $this->expectExceptionMessage('ROL utuh yang dibagi (1) lebih banyak dari yang ada (0)');

        $this->store->run(fn () => app(StockOpnameService::class)->saveReview($opname, $this->admin->id, [[
            'item_id' => StockOpnameItem::query()->where('stock_opname_id', $opname->id)->value('id'), 'reason_id' => $damaged->id, 'note' => null, 'is_reviewed' => true,
            'dead_stock_quantity' => '100', 'damaged_goods_quantity' => '0', 'full_price_quantity' => '0', 'damaged_goods_price' => null,
            'dead_stock_packs' => 1, 'damaged_goods_packs' => 0, 'full_price_packs' => 0,
        ]]));
    }

    /**
     * Sells 1 roll and 30 m of the wire, takes it all back, counts it in the returns warehouse as 1 ROL + 30 MTR, reviews the
     * decision given and posts.
     *
     * @param  array<string, mixed>  $decision
     * @param  list<array{unit_code: string, quantity: string}>  $countedParts
     * @return array{quantity: string, intact_packs: int} the main shelf before the inspection
     */
    private function inspectReturnedRollAndMetres(array $decision, array $countedParts = [['unit_code' => 'ROL', 'quantity' => '1'], ['unit_code' => 'MTR', 'quantity' => '30']]): array
    {
        $this->sell([['ROL', 1], ['MTR', 30]]);
        $sale = $this->store->run(fn () => Sale::query()->with('items')->sole());
        $this->store->run(fn () => app(SaleReturnService::class)->createApproved($sale, $this->shift, $this->admin, [
            'client_uuid' => (string) Str::uuid(),
            'refund_method' => 'cash',
            'items' => $sale->items->map(fn ($line): array => ['sale_item_id' => $line->id, 'quantity' => $line->quantity])->all(),
        ]));
        $shelfAfterSale = $this->shelfOf($this->wire);

        $this->store->run(function () use ($decision, $countedParts): void {
            $opnames = app(StockOpnameService::class);
            ['opname' => $opname] = $opnames->create([
                'warehouse_id' => $this->returnsWarehouse()->id,
                'opname_date' => now()->toDateString(),
                'scope' => StockOpnameScope::Selected->value,
                'reason_code' => StockOpnameReason::ReturnsCheck->value,
            ], $this->admin->id);
            $itemId = StockOpnameItem::query()->where('stock_opname_id', $opname->id)->value('id');

            $opnames->saveCounts($opname, [['item_id' => $itemId, 'reason_id' => null, 'note' => null, 'parts' => $countedParts]]);
            $opnames->finishCounting($opname);
            $opnames->saveReview($opname, $this->admin->id, [['item_id' => $itemId, 'reason_id' => null, 'note' => null, 'is_reviewed' => true, 'damaged_goods_price' => null, ...$decision]]);
            $opnames->post($opname, $this->admin->id);
        });

        return $shelfAfterSale;
    }

    /**
     * @return array{quantity: string, intact_packs: int}
     */
    private function stockIn(Product $product, string $warehouseCode): array
    {
        return $this->store->run(function () use ($product, $warehouseCode): array {
            $stock = Stock::query()->where('product_id', $product->id)->whereIn('warehouse_id', Warehouse::query()->where('code', $warehouseCode)->select('id'))->firstOrFail();

            return ['quantity' => (string) $stock->quantity, 'intact_packs' => $stock->intact_packs];
        });
    }

    /**
     * The item form of the wire product as the browser sends it back, with the roll marked sealed or not.
     *
     * @return array<string, mixed>
     */
    private function formOf(Product $product, bool $isSealedPack, bool $hasSealedBase = false): array
    {
        $product = $this->store->run(fn () => Product::query()->with(['category', 'productUnits'])->findOrFail($product->id));

        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'category' => ['id' => $product->category_id, 'name' => null],
            'brand' => null,
            'min_stock' => 0,
            'is_active' => true,
            'version' => $product->version,
            'units' => $product->productUnits->map(fn (ProductUnit $unit): array => [
                'id' => $unit->id,
                'unit' => ['code' => $unit->unit_code],
                'conversion_factor' => $unit->conversion_factor,
                'is_sellable' => true,
                'cost_price' => $unit->cost_price,
                'selling_price' => $unit->selling_price,
                'is_default_sale' => $unit->is_base,
                'is_sealed_pack' => $hasSealedBase ? $unit->is_base : ! $unit->is_base && $isSealedPack,
            ])->all(),
        ];
    }

    /**
     * A started opname of the wire in the main warehouse, counted in the given units and ready to post.
     *
     * @param  array<string, string>  $parts  quantity by unit code
     */
    private function opnameCounting(array $parts, ?Product $product = null, StockOpnameReason $reason = StockOpnameReason::Routine): StockOpname
    {
        $product ??= $this->wire;

        return $this->store->run(function () use ($parts, $product, $reason) {
            $opnames = app(StockOpnameService::class);
            ['opname' => $opname] = $opnames->create([
                'warehouse_id' => $this->mainWarehouse()->id,
                'opname_date' => now()->toDateString(),
                'scope' => StockOpnameScope::Selected->value,
                'reason_code' => $reason->value,
            ], $this->admin->id);

            $opnames->addProduct($opname, $product);
            $opnames->saveCounts($opname, [[
                'item_id' => StockOpnameItem::query()->where('stock_opname_id', $opname->id)->value('id'),
                'parts' => collect($parts)->map(fn (string $quantity, string $unitCode): array => ['unit_code' => $unitCode, 'quantity' => $quantity])->values()->all(),
                'reason_id' => null,
                'note' => null,
            ]]);
            $opnames->finishCounting($opname);

            return $opname->refresh();
        });
    }
}
