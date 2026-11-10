<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Enums\WarehouseType;
use App\Exceptions\ShiftException;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class SaleReturnTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_sale_returns';

    private Store $store;

    private User $admin;

    private CashShift $adminShift;

    private Product $brush;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_sale_returns', 'receipt_prefix' => 'TSR', 'address' => 'Jl. Uji 1', 'phone' => '021-123']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->adminShift = $this->openShiftFor($this->admin);
        $this->brush = $this->sellableProduct('Kuas', '100');
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.returns.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

    /**
     * A receipt of pieces of the brush, paid in cash; the stock drops by as many pieces.
     */
    private function sellBrushes(User $cashier, CashShift $shift, int $pieces): Sale
    {
        $pcs = $this->unitOf($this->brush, 'PCS');

        return $this->store->run(fn (): Sale => app(SaleService::class)->create($shift, $cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $pcs->id, 'quantity' => $pieces]],
            'payments' => [['method' => 'cash', 'amount' => $pieces * 10000]],
        ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Sale $sale, int|string $quantity, array $overrides = []): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'refund_method' => 'cash',
            'reason' => 'Rusak',
            'items' => [['sale_item_id' => $sale->items[0]->id, 'quantity' => $quantity]],
            ...$overrides,
        ];
    }

    /**
     * A cashier on the second register, whose returns wait for an admin.
     *
     * @return array{0: User, 1: CashShift}
     */
    private function cashierOnSecondRegister(): array
    {
        $cashier = $this->storeUser();
        $register = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));

        return [$cashier, $this->openShiftFor($cashier, $register)];
    }

    private function pendingReturn(Sale $sale, CashShift $shift, User $cashier, int|string $quantity): SaleReturn
    {
        return $this->store->run(fn (): SaleReturn => app(SaleReturnService::class)->create($sale, $shift, $cashier, $this->payload($sale, $quantity)));
    }

    private function returnsStockOf(Product $product): string
    {
        return $this->store->run(fn (): string => (string) Stock::query()
            ->where('product_id', $product->id)
            ->whereIn('warehouse_id', Warehouse::query()->where('type', WarehouseType::Returns)->select('id'))
            ->value('quantity'));
    }

    private function returnsWarehouseOf(Sale $sale): Warehouse
    {
        return $this->store->run(fn (): Warehouse => Warehouse::returnsFor($sale->warehouse_id));
    }

    private function saleReturn(?int $id = null): SaleReturn
    {
        return $this->store->run(fn (): SaleReturn => $id === null ? SaleReturn::query()->latest('id')->with('items')->firstOrFail() : SaleReturn::query()->with('items')->findOrFail($id));
    }

    private function returnStatusOf(Sale $sale): string
    {
        return $this->store->run(fn (): string => Sale::query()->findOrFail($sale->id)->return_status->value);
    }

    public function test_only_admins_may_use_the_return_pages(): void
    {
        $cashier = $this->storeUser();
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);
        $saleReturn = $this->pendingReturn($sale, $this->adminShift, $this->admin, 1);

        $this->actingAs($cashier)->get($this->url('index'))->assertForbidden();
        $this->actingAs($cashier)->get($this->url('create'))->assertForbidden();
        $this->actingAs($cashier)->get($this->url('sales'))->assertForbidden();
        $this->actingAs($cashier)->post($this->url('store'), $this->payload($sale, 1))->assertForbidden();
        $this->actingAs($cashier)->get($this->url('show', ['sale_return' => $saleReturn->id]))->assertForbidden();
        $this->actingAs($cashier)->post($this->url('approve', ['sale_return' => $saleReturn->id]))->assertForbidden();
        $this->actingAs($cashier)->post($this->url('reject', ['sale_return' => $saleReturn->id]))->assertForbidden();
        $this->actingAs($cashier)->get($this->url('sheet', ['sale_return' => $saleReturn->id]))->assertForbidden();
    }

    public function test_an_admin_return_is_approved_at_once_and_puts_the_goods_back(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);
        $this->assertSame('95.000', $this->stockInPieces($this->brush));

        $response = $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 2));

        $saleReturn = $this->saleReturn();
        $response->assertRedirect($this->url('show', ['sale_return' => $saleReturn->id]))->assertSessionHas('success');
        $this->assertSame('RET/TSR/'.now()->format('ym').'/0001', $saleReturn->number);
        $this->assertSame('completed', $saleReturn->status->value);
        $this->assertSame([$this->admin->id, $this->admin->id, $this->adminShift->id], [$saleReturn->requested_by, $saleReturn->approved_by, $saleReturn->cash_shift_id]);
        $this->assertSame(['20000.00', '20000.00', 'cash'], [$saleReturn->credit_amount, $saleReturn->refund_amount, $saleReturn->refund_method->value]);
        $this->assertSame(['2.000', '20000.00', '12000.00', null], [$saleReturn->items[0]->quantity, $saleReturn->items[0]->refund_subtotal, $saleReturn->items[0]->cost_total, $saleReturn->items[0]->disposition?->value]);
        $this->assertSame('95.000', $this->stockInPieces($this->brush));
        $this->assertSame('2.000', $this->returnsStockOf($this->brush));
        $this->assertSame('partial', $this->returnStatusOf($sale));

        $movement = $this->store->run(fn () => StockMovement::query()->where('reference_type', 'sale_return')->sole());
        $this->assertSame(['sale_return', '2.000', '2.000', $saleReturn->id], [$movement->type->value, $movement->quantity, $movement->balance_after, (int) $movement->reference_id]);
    }

    public function test_returned_goods_wait_in_the_returns_warehouse_and_are_not_for_sale(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 3))->assertSessionHas('success');

        $this->assertSame('95.000', $this->stockInPieces($this->brush));
        $this->assertSame('3.000', $this->returnsStockOf($this->brush));
        $this->assertSame($this->returnsWarehouseOf($sale)->id, $this->saleReturn()->items[0]->destination_warehouse_id);
    }

    public function test_a_return_in_a_bigger_unit_puts_back_the_base_quantity(): void
    {
        $dus = $this->unitOf($this->brush, 'DUS');
        $sale = $this->store->run(fn (): Sale => app(SaleService::class)->create($this->adminShift, $this->admin, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $dus->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 220000]],
        ]));
        $this->assertSame('76.000', $this->stockInPieces($this->brush));

        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 1));

        $saleReturn = $this->saleReturn();
        $this->assertSame(['110000.00', '12.000'], [$saleReturn->refund_amount, $saleReturn->items[0]->base_quantity]);
        $this->assertSame('76.000', $this->stockInPieces($this->brush));
        $this->assertSame('12.000', $this->returnsStockOf($this->brush));
    }

    public function test_returns_cannot_go_beyond_what_was_sold_counting_those_waiting_for_approval(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $this->pendingReturn($sale, $cashierShift, $cashier, 3);

        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 3))
            ->assertSessionHas('error', 'Kuas melebihi yang masih bisa diretur, sisa 2 PCS.');

        $this->assertSame(1, $this->store->run(fn () => SaleReturn::query()->count()));
        $this->assertSame('95.000', $this->stockInPieces($this->brush));
    }

    public function test_the_whole_receipt_returned_in_parts_makes_it_fully_returned(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 3));
        $this->assertSame('partial', $this->returnStatusOf($sale));

        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 2));
        $this->assertSame('full', $this->returnStatusOf($sale));
        $this->assertSame('95.000', $this->stockInPieces($this->brush));
        $this->assertSame('5.000', $this->returnsStockOf($this->brush));
    }

    public function test_the_input_is_validated(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 1, ['refund_method' => 'return_credit']))->assertSessionHasErrors('refund_method');
        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, -1))->assertSessionHasErrors('items.0.quantity');
        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 0))->assertSessionHas('error', 'Pilih minimal satu barang yang diretur.');

        $this->assertSame(0, $this->store->run(fn () => SaleReturn::query()->count()));
    }

    public function test_a_refund_by_transfer_needs_the_account_it_goes_to(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 1, ['refund_method' => 'transfer']))
            ->assertSessionHas('error', 'Isi rekening penerima untuk pengembalian uang non tunai.');
        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 1, ['refund_method' => 'qris', 'refund_account' => '   ']))
            ->assertSessionHas('error', 'Isi rekening penerima untuk pengembalian uang non tunai.');
        $this->assertSame(0, $this->store->run(fn () => SaleReturn::query()->count()));

        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 1, ['refund_method' => 'transfer', 'refund_account' => '  BCA 123 a.n. Budi ']))->assertSessionHas('success');

        $this->assertSame('BCA 123 a.n. Budi', $this->saleReturn()->refund_account);
        $this->actingAs($this->admin)->get($this->url('show', ['sale_return' => $this->saleReturn()->id]))->assertInertia(fn (Assert $page) => $page->where('saleReturn.refund_account', 'BCA 123 a.n. Budi'));
    }

    public function test_a_cash_refund_keeps_no_account(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 1, ['refund_account' => 'BCA 123']))->assertSessionHas('success');

        $this->assertNull($this->saleReturn()->refund_account);
    }

    public function test_a_voided_receipt_cannot_be_returned(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);
        $this->store->run(fn () => Sale::query()->whereKey($sale->id)->update(['status' => 'voided']));

        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 1))
            ->assertSessionHas('error', 'Retur hanya bisa dibuat dari nota yang selesai, bukan nota void.');
    }

    public function test_an_admin_needs_a_running_shift_to_make_a_return(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);
        $this->store->run(fn () => $this->adminShift->forceFill(['status' => 'closed'])->save());

        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 1))->assertSessionHas('error', 'Buka shift dulu untuk membuat retur.');

        $this->assertSame(0, $this->store->run(fn () => SaleReturn::query()->count()));
    }

    public function test_approving_a_waiting_return_takes_the_goods_into_the_returns_warehouse(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $saleReturn = $this->pendingReturn($sale, $cashierShift, $cashier, 2);
        $this->assertSame('95.000', $this->stockInPieces($this->brush));
        $this->assertSame('none', $this->returnStatusOf($sale));

        $this->actingAs($this->admin)->post($this->url('approve', ['sale_return' => $saleReturn->id]))->assertSessionHas('success');

        $approved = $this->saleReturn($saleReturn->id);
        $this->assertSame(['completed', $this->admin->id, $cashier->id], [$approved->status->value, $approved->approved_by, $approved->requested_by]);
        $this->assertSame('2.000', $this->returnsStockOf($this->brush));
        $this->assertSame('partial', $this->returnStatusOf($sale));

        $this->actingAs($this->admin)->post($this->url('approve', ['sale_return' => $saleReturn->id]))->assertSessionHas('error', 'Retur berstatus Selesai, jadi tidak bisa disetujui.');
        $this->assertSame('2.000', $this->returnsStockOf($this->brush));
    }

    public function test_rejecting_a_waiting_return_frees_the_goods_and_moves_no_stock(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $saleReturn = $this->pendingReturn($sale, $cashierShift, $cashier, 2);

        $this->actingAs($this->admin)->post($this->url('reject', ['sale_return' => $saleReturn->id]))->assertSessionHas('success');

        $this->assertSame(['rejected', $this->admin->id], [$this->saleReturn($saleReturn->id)->status->value, $this->saleReturn($saleReturn->id)->approved_by]);
        $this->assertSame('95.000', $this->stockInPieces($this->brush));
        $this->assertSame('none', $this->returnStatusOf($sale));

        $this->actingAs($this->admin)->post($this->url('approve', ['sale_return' => $saleReturn->id]))->assertSessionHas('error', 'Retur berstatus Ditolak, jadi tidak bisa disetujui.');
        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 5))->assertSessionHas('success');
    }

    public function test_cash_refunds_come_out_of_the_drawer_only_once_approved(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);
        $summarize = fn (): array => $this->store->run(fn (): array => app(ShiftService::class)->summarize($this->adminShift));
        $this->assertSame('50000.00', $summarize()['expected_cash']);

        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 2));
        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 1, ['refund_method' => 'transfer', 'refund_account' => 'BCA 123 a.n. Budi']));

        $summary = $summarize();
        $this->assertSame(['30000.00', '20000.00', '50000.00', '30000.00'], [$summary['expected_cash'], $summary['cash_refunds'], $summary['payments']['cash'], $summary['returns_total']]);
    }

    public function test_a_shift_cannot_be_closed_while_it_has_a_return_waiting(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $saleReturn = $this->pendingReturn($sale, $cashierShift, $cashier, 2);

        $countedCash = $this->store->run(fn (): string => app(ShiftService::class)->summarize($cashierShift)['expected_cash']);

        try {
            $this->store->run(fn () => app(ShiftService::class)->close($cashierShift, $countedCash, null));
            $this->fail('The shift closed with a return waiting.');
        } catch (ShiftException $exception) {
            $this->assertSame('Masih ada 1 retur yang menunggu persetujuan Admin. Selesaikan dulu sebelum menutup shift.', $exception->getMessage());
        }

        $this->actingAs($this->admin)->post($this->url('reject', ['sale_return' => $saleReturn->id]));
        $closed = $this->store->run(fn () => app(ShiftService::class)->close($cashierShift, $countedCash, null));
        $this->assertSame('closed', $closed->status->value);
    }

    public function test_the_list_shows_returns_newest_first_with_filters_and_the_waiting_count(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $this->pendingReturn($sale, $cashierShift, $cashier, 1);
        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 2));

        $this->actingAs($this->admin)->get($this->url('index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Returns/Index')
            ->has('returns.data', 2)
            ->where('returns.data.0.status', 'completed')
            ->where('returns.data.0.sale_number', $sale->number)
            ->where('returns.data.0.refund_amount', '20000.00')
            ->where('returns.data.1.status', 'pending')
            ->where('pending_count', 1)
            ->has('statuses', 3));

        $this->actingAs($this->admin)->get($this->url('index', ['status' => 'pending']))->assertInertia(fn (Assert $page) => $page->has('returns.data', 1)->where('returns.data.0.cashier_name', $cashier->name));
        $this->actingAs($this->admin)->get($this->url('index', ['search' => $sale->number]))->assertInertia(fn (Assert $page) => $page->has('returns.data', 2));
        $this->actingAs($this->admin)->get($this->url('index', ['search' => 'tidak-ada']))->assertInertia(fn (Assert $page) => $page->has('returns.data', 0));
    }

    public function test_the_return_page_shows_the_goods_and_what_may_be_done_next(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $saleReturn = $this->pendingReturn($sale, $cashierShift, $cashier, 2);

        $this->actingAs($this->admin)->get($this->url('show', ['sale_return' => $saleReturn->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Returns/Show')
            ->where('saleReturn.number', $saleReturn->number)
            ->where('saleReturn.is_pending', true)
            ->where('saleReturn.status_label', 'Menunggu persetujuan')
            ->where('saleReturn.refund_method_label', 'Tunai')
            ->where('saleReturn.cashier_name', $cashier->name)
            ->where('saleReturn.reason', 'Rusak')
            ->where('saleReturn.sale.number', $sale->number)
            ->where('saleReturn.items.0.name', 'Kuas')
            ->where('saleReturn.items.0.disposition_label', 'Gudang retur, menunggu pemeriksaan')
            ->where('saleReturn.urls.approve', $this->url('approve', ['sale_return' => $saleReturn->id]))
            ->where('saleReturn.urls.sheet', $this->url('sheet', ['sale_return' => $saleReturn->id])));
    }

    public function test_the_create_page_can_come_with_a_receipt_and_what_can_still_be_returned(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);
        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 2));

        $this->actingAs($this->admin)->get($this->url('create', ['sale' => $sale->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Returns/Create')
            ->where('sale.number', $sale->number)
            ->where('sale.default_refund_method', 'cash')
            ->where('sale.lines.0.quantity', '5.000')
            ->where('sale.lines.0.returned', '2.000')
            ->where('sale.lines.0.remaining', '3.000')
            ->has('refund_methods', 4)
            ->missing('dispositions'));
    }

    public function test_the_lookup_finds_receipts_with_something_left_to_return(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 2);
        $fullyReturned = $this->sellBrushes($this->admin, $this->adminShift, 1);
        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($fullyReturned, 1));

        $this->actingAs($this->admin)->getJson($this->url('sales', ['q' => 'INV']))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $sale->number)
            ->assertJsonPath('data.0.lines.0.remaining', '2.000');
    }

    public function test_the_default_refund_method_is_how_the_customer_paid_most(): void
    {
        $pcs = $this->unitOf($this->brush, 'PCS');
        $sale = $this->store->run(fn (): Sale => app(SaleService::class)->create($this->adminShift, $this->admin, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $pcs->id, 'quantity' => 10]],
            'payments' => [['method' => 'cash', 'amount' => 30000], ['method' => 'qris', 'amount' => 70000]],
        ]));

        $this->actingAs($this->admin)->get($this->url('create', ['sale' => $sale->id]))->assertInertia(fn (Assert $page) => $page->where('sale.default_refund_method', 'qris'));
    }

    public function test_the_slip_has_the_store_and_the_goods(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);
        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 2));
        $saleReturn = $this->saleReturn();

        $this->actingAs($this->admin)->get($this->url('sheet', ['sale_return' => $saleReturn->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Print/SaleReturn')
            ->where('saleReturn.number', $saleReturn->number)
            ->where('saleReturn.store.name', $this->store->name)
            ->where('saleReturn.store.address', 'Jl. Uji 1')
            ->where('saleReturn.paper_width', 80)
            ->where('saleReturn.refund_amount', '20000.00')
            ->where('saleReturn.items.0.name', 'Kuas'));
    }

    public function test_sending_the_same_admin_return_twice_returns_the_goods_once(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);
        $payload = $this->payload($sale, 2);

        $this->actingAs($this->admin)->post($this->url('store'), $payload)->assertSessionHas('success');
        $this->actingAs($this->admin)->post($this->url('store'), $payload)->assertSessionHas('success');

        $this->assertSame(1, $this->store->run(fn () => SaleReturn::query()->count()));
        $this->assertSame('2.000', $this->returnsStockOf($this->brush));
        $this->assertSame('partial', $this->returnStatusOf($sale));
    }

    public function test_a_line_of_another_receipt_cannot_be_returned(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);
        $otherSale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)
            ->post($this->url('store'), $this->payload($sale, 1, ['items' => [['sale_item_id' => $otherSale->items[0]->id, 'quantity' => 1]]]))
            ->assertSessionHas('error', 'Ada barang yang tidak ada di nota ini.');

        $this->assertSame(0, $this->store->run(fn () => SaleReturn::query()->count()));
        $this->assertSame('90.000', $this->stockInPieces($this->brush));
    }

    public function test_a_return_needs_a_returns_warehouse_for_the_warehouse_the_receipt_sold_from(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);
        $this->store->run(fn () => Warehouse::query()->where('type', WarehouseType::Returns)->delete());

        $this->actingAs($this->admin)->post($this->url('store'), $this->payload($sale, 1))
            ->assertSessionHas('error', 'Gudang retur untuk gudang nota ini belum ada di toko. Hubungi Admin.');

        $this->assertSame(0, $this->store->run(fn () => SaleReturn::query()->count()));
    }

    public function test_receipts_that_are_used_up_do_not_crowd_the_search_for_ones_that_can_still_be_returned(): void
    {
        $returnable = $this->sellBrushes($this->admin, $this->adminShift, 5);
        $this->store->run(fn () => Sale::query()->whereKey($returnable->id)->update(['sold_at' => now()->subDays(3)]));

        foreach (range(1, 11) as $ignored) {
            $usedUp = $this->sellBrushes($this->admin, $this->adminShift, 1);
            $this->actingAs($this->admin)->post($this->url('store'), $this->payload($usedUp, 1))->assertSessionHas('success');
        }

        $this->actingAs($this->admin)->getJson($this->url('sales'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $returnable->number);
    }

    public function test_receipts_whose_goods_are_all_waiting_for_approval_are_not_offered_again(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $this->pendingReturn($sale, $cashierShift, $cashier, 5);

        $this->actingAs($this->admin)->getJson($this->url('sales'))->assertOk()->assertJsonCount(0, 'data');
    }
}
