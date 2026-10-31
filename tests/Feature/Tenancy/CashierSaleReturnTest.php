<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Store;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class CashierSaleReturnTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_cashier_returns';

    private Store $store;

    private User $cashier;

    private CashShift $shift;

    private Product $brush;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_cashier_returns', 'receipt_prefix' => 'TCR', 'address' => 'Jl. Uji 1', 'phone' => '021-123']);
        $this->cashier = $this->storeUser();
        $this->shift = $this->openShiftFor($this->cashier);
        $this->brush = $this->sellableProduct('Kuas', '100');
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.cashier.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

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
     * @return array<string, mixed>
     */
    private function body(Sale $sale, int|string $quantity, string $disposition = 'resellable'): array
    {
        return [
            'refund_method' => 'cash',
            'reason' => 'Rusak',
            'items' => [['sale_item_id' => $sale->items[0]->id, 'quantity' => $quantity, 'disposition' => $disposition]],
        ];
    }

    private function returnCount(): int
    {
        return $this->store->run(fn () => SaleReturn::query()->count());
    }

    public function test_the_dialog_gets_the_lines_the_choices_and_the_returns_already_asked_for(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);
        $this->actingAs($this->cashier)->postJson($this->url('receipts.returns.store', ['sale' => $sale->id]), $this->body($sale, 2))->assertCreated();

        $this->actingAs($this->cashier)->getJson($this->url('receipts.return', ['sale' => $sale->id]))->assertOk()
            ->assertJsonPath('data.number', $sale->number)
            ->assertJsonPath('data.default_refund_method', 'cash')
            ->assertJsonPath('data.is_approved_at_once', false)
            ->assertJsonCount(4, 'data.refund_methods')
            ->assertJsonCount(2, 'data.dispositions')
            ->assertJsonPath('data.lines.0.quantity', '5.000')
            ->assertJsonPath('data.lines.0.returned', '2.000')
            ->assertJsonPath('data.lines.0.remaining', '3.000')
            ->assertJsonCount(1, 'data.returns')
            ->assertJsonPath('data.returns.0.status', 'pending')
            ->assertJsonPath('data.returns.0.items.0.name', 'Kuas')
            ->assertJsonPath('data.returns.0.items.0.disposition_label', 'Dijual lagi');
    }

    public function test_a_cashier_asks_for_a_return_and_nothing_moves_until_an_admin_approves(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);

        $response = $this->actingAs($this->cashier)->postJson($this->url('receipts.returns.store', ['sale' => $sale->id]), $this->body($sale, 2, 'dead_stock'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Menunggu persetujuan')
            ->assertJsonPath('data.refund_amount', '20000.00')
            ->assertJsonPath('data.items.0.disposition', 'dead_stock');

        $saleReturn = $this->store->run(fn () => SaleReturn::query()->sole());
        $this->assertSame([$this->cashier->id, null, $this->shift->id], [$saleReturn->requested_by, $saleReturn->approved_by, $saleReturn->cash_shift_id]);
        $this->assertSame('95.000', $this->stockInPieces($this->brush));
        $this->assertSame($this->url('returns.sheet', ['sale_return' => $saleReturn->id]), $response->json('data.sheet_url'));
    }

    public function test_an_admin_using_the_till_gets_the_return_done_at_once(): void
    {
        $this->store->run(fn () => $this->shift->forceFill(['status' => 'closed'])->save());
        $admin = $this->storeUser(UserRole::StoreAdmin);
        $register = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));
        $adminShift = $this->openShiftFor($admin, $register);
        $sale = $this->sellBrushes($admin, $adminShift, 5);

        $this->actingAs($admin)->getJson($this->url('receipts.return', ['sale' => $sale->id]))->assertJsonPath('data.is_approved_at_once', true);
        $this->actingAs($admin)->postJson($this->url('receipts.returns.store', ['sale' => $sale->id]), $this->body($sale, 2))
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame('97.000', $this->stockInPieces($this->brush));
    }

    public function test_a_return_needs_a_running_shift(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);
        $this->store->run(fn () => $this->shift->forceFill(['status' => 'closed'])->save());

        $this->actingAs($this->cashier)->postJson($this->url('receipts.returns.store', ['sale' => $sale->id]), $this->body($sale, 1))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Buka shift dulu untuk membuat retur.');

        $this->assertSame(0, $this->returnCount());
    }

    public function test_the_input_and_the_returnable_quantity_are_enforced(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);
        $store = $this->url('receipts.returns.store', ['sale' => $sale->id]);

        $this->actingAs($this->cashier)->postJson($store, [...$this->body($sale, 1), 'refund_method' => 'return_credit'])->assertUnprocessable()->assertJsonValidationErrors('refund_method');
        $this->actingAs($this->cashier)->postJson($store, $this->body($sale, 1, 'lost'))->assertUnprocessable()->assertJsonValidationErrors('items.0.disposition');
        $this->actingAs($this->cashier)->postJson($store, $this->body($sale, 0))->assertUnprocessable()->assertJsonPath('message', 'Pilih minimal satu barang yang diretur.');
        $this->actingAs($this->cashier)->postJson($store, $this->body($sale, 6))->assertUnprocessable()->assertJsonPath('message', 'Kuas melebihi yang masih bisa diretur, sisa 5 PCS.');

        $this->assertSame(0, $this->returnCount());
    }

    public function test_a_cashier_cannot_touch_the_receipt_of_someone_else_but_an_admin_can(): void
    {
        $other = $this->storeUser();
        $register = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));
        $theirs = $this->sellBrushes($other, $this->openShiftFor($other, $register), 5);

        $this->actingAs($this->cashier)->getJson($this->url('receipts.return', ['sale' => $theirs->id]))->assertForbidden();
        $this->actingAs($this->cashier)->postJson($this->url('receipts.returns.store', ['sale' => $theirs->id]), $this->body($theirs, 1))->assertForbidden();
        $this->assertSame(0, $this->returnCount());
    }

    public function test_the_slip_can_be_printed_by_who_asked_and_by_admins(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);
        $slipUrl = $this->actingAs($this->cashier)->postJson($this->url('receipts.returns.store', ['sale' => $sale->id]), $this->body($sale, 2))->json('data.sheet_url');

        $this->actingAs($this->cashier)->get($slipUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Print/SaleReturn')
            ->where('saleReturn.status', 'pending')
            ->where('saleReturn.store.name', $this->store->name)
            ->where('saleReturn.items.0.name', 'Kuas')
            ->where('saleReturn.refund_amount', '20000.00'));

        $this->actingAs($this->storeUser(UserRole::StoreAdmin))->get($slipUrl)->assertOk();
        $this->actingAs($this->storeUser())->get($slipUrl)->assertForbidden();
    }

    public function test_the_receipt_list_carries_the_links_for_a_return(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);

        $this->actingAs($this->cashier)->getJson($this->url('receipts'))
            ->assertJsonPath('data.0.return_url', $this->url('receipts.return', ['sale' => $sale->id]))
            ->assertJsonPath('data.0.return_store_url', $this->url('receipts.returns.store', ['sale' => $sale->id]));
    }
}
