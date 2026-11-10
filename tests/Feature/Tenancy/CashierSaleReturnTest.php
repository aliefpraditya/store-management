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
use Illuminate\Support\Facades\URL;
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

    /**
     * An address the way the till gets it from the server: signed, so it works only for that receipt.
     */
    private function signedUrl(string $name, array $parameters = []): string
    {
        return URL::temporarySignedRoute("tenant.cashier.{$name}", now()->addHour(), ['tenant' => $this->store->code, ...$parameters]);
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
    private function body(Sale $sale, int|string $quantity): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'refund_method' => 'cash',
            'reason' => 'Rusak',
            'items' => [['sale_item_id' => $sale->items[0]->id, 'quantity' => $quantity]],
        ];
    }

    private function returnCount(): int
    {
        return $this->store->run(fn () => SaleReturn::query()->count());
    }

    public function test_the_dialog_gets_the_lines_the_choices_and_the_returns_already_asked_for(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);
        $this->actingAs($this->cashier)->postJson($this->signedUrl('receipts.returns.store', ['sale' => $sale->id]), $this->body($sale, 2))->assertCreated();

        $this->actingAs($this->cashier)->getJson($this->signedUrl('receipts.return', ['sale' => $sale->id]))->assertOk()
            ->assertJsonPath('data.number', $sale->number)
            ->assertJsonPath('data.default_refund_method', 'cash')
            ->assertJsonPath('data.is_approved_at_once', false)
            ->assertJsonCount(4, 'data.refund_methods')
            ->assertJsonPath('data.lines.0.quantity', '5.000')
            ->assertJsonPath('data.lines.0.returned', '2.000')
            ->assertJsonPath('data.lines.0.remaining', '3.000')
            ->assertJsonCount(1, 'data.returns')
            ->assertJsonPath('data.returns.0.status', 'pending')
            ->assertJsonPath('data.returns.0.items.0.name', 'Kuas')
            ->assertJsonPath('data.returns.0.items.0.disposition_label', 'Gudang retur, menunggu pemeriksaan');
    }

    public function test_a_cashier_asks_for_a_return_and_nothing_moves_until_an_admin_approves(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);

        $response = $this->actingAs($this->cashier)->postJson($this->signedUrl('receipts.returns.store', ['sale' => $sale->id]), $this->body($sale, 2))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Menunggu persetujuan')
            ->assertJsonPath('data.refund_amount', '20000.00')
            ->assertJsonPath('data.items.0.disposition', null);

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

        $this->actingAs($admin)->getJson($this->signedUrl('receipts.return', ['sale' => $sale->id]))->assertJsonPath('data.is_approved_at_once', true);
        $this->actingAs($admin)->postJson($this->signedUrl('receipts.returns.store', ['sale' => $sale->id]), $this->body($sale, 2))
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame('95.000', $this->stockInPieces($this->brush));
    }

    public function test_a_return_needs_a_running_shift(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);
        $this->store->run(fn () => $this->shift->forceFill(['status' => 'closed'])->save());

        $this->actingAs($this->cashier)->postJson($this->signedUrl('receipts.returns.store', ['sale' => $sale->id]), $this->body($sale, 1))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Buka shift dulu untuk membuat retur. Bila shift Anda sudah dihitung, tunggu keputusan Admin.');

        $this->assertSame(0, $this->returnCount());
    }

    public function test_the_input_and_the_returnable_quantity_are_enforced(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);
        $store = $this->signedUrl('receipts.returns.store', ['sale' => $sale->id]);

        $this->actingAs($this->cashier)->postJson($store, [...$this->body($sale, 1), 'refund_method' => 'return_credit'])->assertUnprocessable()->assertJsonValidationErrors('refund_method');
        $this->actingAs($this->cashier)->postJson($store, $this->body($sale, 0))->assertUnprocessable()->assertJsonPath('message', 'Pilih minimal satu barang yang diretur.');
        $this->actingAs($this->cashier)->postJson($store, $this->body($sale, 6))->assertUnprocessable()->assertJsonPath('message', 'Kuas melebihi yang masih bisa diretur, sisa 5 PCS.');
        $this->actingAs($this->cashier)->postJson($store, [...$this->body($sale, 1), 'refund_method' => 'transfer'])->assertUnprocessable()
            ->assertJsonPath('message', 'Isi rekening penerima untuk pengembalian uang non tunai.');

        $this->assertSame(0, $this->returnCount());
    }

    public function test_a_cashier_can_ask_for_a_return_on_the_receipt_of_another_cashier(): void
    {
        $other = $this->storeUser();
        $register = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));
        $theirs = $this->sellBrushes($other, $this->openShiftFor($other, $register), 5);

        $this->actingAs($this->cashier)->getJson($this->signedUrl('receipts.return', ['sale' => $theirs->id]))->assertOk()->assertJsonPath('data.number', $theirs->number);
        $this->actingAs($this->cashier)->postJson($this->signedUrl('receipts.returns.store', ['sale' => $theirs->id]), $this->body($theirs, 1))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $saleReturn = $this->store->run(fn () => SaleReturn::query()->sole());
        $this->assertSame([$this->cashier->id, $this->shift->id], [$saleReturn->requested_by, $saleReturn->cash_shift_id]);
        $this->assertSame('95.000', $this->stockInPieces($this->brush));
    }

    public function test_the_till_finds_a_receipt_by_its_full_number_only(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);

        $found = $this->actingAs($this->cashier)->getJson($this->url('returns.lookup', ['number' => $sale->number]))->assertOk()
            ->assertJsonPath('data.number', $sale->number);

        $this->assertSignedAddressOf($this->url('receipts.return', ['sale' => $sale->id]), $found->json('data.return_url'));
        $this->assertSignedAddressOf($this->url('receipts.returns.store', ['sale' => $sale->id]), $found->json('data.return_store_url'));
        $this->actingAs($this->cashier)->getJson($found->json('data.return_url'))->assertOk()->assertJsonPath('data.number', $sale->number);

        $this->actingAs($this->cashier)->getJson($this->url('returns.lookup', ['number' => substr($sale->number, 0, -2)]))->assertNotFound()
            ->assertJsonPath('message', 'Nota tidak ditemukan. Ketik nomor nota selengkapnya.');
        $this->actingAs($this->cashier)->getJson($this->url('returns.lookup', ['number' => 'INV/TIDAK/ADA']))->assertNotFound();
        $this->actingAs($this->cashier)->getJson($this->url('returns.lookup'))->assertNotFound();
    }

    public function test_a_refund_by_transfer_keeps_the_account_of_the_customer_off_the_till_screen(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);

        $this->actingAs($this->cashier)->postJson($this->signedUrl('receipts.returns.store', ['sale' => $sale->id]), [...$this->body($sale, 1), 'refund_method' => 'transfer', 'refund_account' => 'BCA 123'])
            ->assertCreated()
            ->assertJsonMissingPath('data.refund_account');

        $this->actingAs($this->storeUser())->getJson($this->signedUrl('receipts.return', ['sale' => $sale->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data.returns')
            ->assertJsonMissingPath('data.returns.0.refund_account');

        $saleReturn = $this->store->run(fn () => SaleReturn::query()->sole());
        $this->assertSame('BCA 123', $saleReturn->refund_account);
        $this->actingAs($this->cashier)->get($this->url('returns.sheet', ['sale_return' => $saleReturn->id]))
            ->assertInertia(fn (Assert $page) => $page->where('saleReturn.refund_account', 'BCA 123'));
    }

    public function test_a_receipt_cannot_be_reached_by_guessing_its_id(): void
    {
        $mine = $this->sellBrushes($this->cashier, $this->shift, 5);
        $other = $this->sellBrushes($this->cashier, $this->shift, 5);

        $this->actingAs($this->cashier)->getJson($this->url('receipts.return', ['sale' => $mine->id]))->assertForbidden();
        $this->actingAs($this->cashier)->postJson($this->url('receipts.returns.store', ['sale' => $mine->id]), $this->body($mine, 1))->assertForbidden();

        $signedForMine = $this->signedUrl('receipts.return', ['sale' => $mine->id]);
        $this->actingAs($this->cashier)->getJson(str_replace("/receipts/{$mine->id}/", "/receipts/{$other->id}/", $signedForMine))->assertForbidden();

        $this->assertSame(0, $this->returnCount());
    }

    public function test_the_dialog_refuses_a_receipt_that_is_not_completed(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);
        $this->store->run(fn () => Sale::query()->whereKey($sale->id)->update(['status' => 'voided']));

        $this->actingAs($this->cashier)->getJson($this->signedUrl('receipts.return', ['sale' => $sale->id]))
            ->assertUnprocessable()
            ->assertJsonPath('code', 4221)
            ->assertJsonPath('message', 'Retur hanya bisa dibuat dari nota yang selesai, bukan nota void.');
    }

    public function test_sending_the_same_return_twice_gives_back_the_first_one(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);
        $body = $this->body($sale, 2);
        $store = $this->signedUrl('receipts.returns.store', ['sale' => $sale->id]);

        $first = $this->actingAs($this->cashier)->postJson($store, $body)->assertCreated();
        $second = $this->actingAs($this->cashier)->postJson($store, $body)->assertCreated();

        $this->assertSame($first->json('data.number'), $second->json('data.number'));
        $this->assertSame(1, $this->returnCount());
        $this->actingAs($this->cashier)->getJson($this->signedUrl('receipts.return', ['sale' => $sale->id]))->assertJsonPath('data.lines.0.returned', '2.000');
    }

    public function test_a_return_uuid_that_belongs_to_someone_else_is_refused(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);
        $body = $this->body($sale, 2);
        $this->actingAs($this->cashier)->postJson($this->signedUrl('receipts.returns.store', ['sale' => $sale->id]), $body)->assertCreated();

        $otherCashier = $this->storeUser();
        $register = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));
        $this->openShiftFor($otherCashier, $register);

        $this->actingAs($otherCashier)->postJson($this->signedUrl('receipts.returns.store', ['sale' => $sale->id]), $body)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pengajuan retur ini sudah tercatat untuk orang atau nota lain. Muat ulang layar lalu coba lagi.');

        $this->assertSame(1, $this->returnCount());
    }

    public function test_a_return_needs_its_uuid(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);

        $this->actingAs($this->cashier)->postJson($this->signedUrl('receipts.returns.store', ['sale' => $sale->id]), [...$this->body($sale, 1), 'client_uuid' => 'bukan-uuid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_uuid');
    }

    /**
     * The address is the plain one plus an expiry and a signature.
     */
    private function assertSignedAddressOf(string $plainAddress, string $givenAddress): void
    {
        $this->assertStringStartsWith("{$plainAddress}?", $givenAddress);
        $this->assertStringContainsString('expires=', $givenAddress);
        $this->assertStringContainsString('signature=', $givenAddress);
    }

    public function test_a_voided_receipt_is_not_found_for_a_return(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);
        $this->store->run(fn () => Sale::query()->whereKey($sale->id)->update(['status' => 'voided']));

        $this->actingAs($this->cashier)->getJson($this->url('returns.lookup', ['number' => $sale->number]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Retur hanya bisa dibuat dari nota yang selesai, bukan nota void.');
    }

    public function test_the_receipt_lookup_needs_a_login(): void
    {
        $this->getJson($this->url('returns.lookup', ['number' => 'INV/1']))->assertUnauthorized();
    }

    public function test_the_till_gives_the_return_lookup_address(): void
    {
        $this->actingAs($this->cashier)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('urls.return_lookup', $this->url('returns.lookup'))
            ->where('app_urls.logout', route('logout')));
    }

    public function test_the_slip_can_be_printed_by_who_asked_and_by_admins(): void
    {
        $sale = $this->sellBrushes($this->cashier, $this->shift, 5);
        $slipUrl = $this->actingAs($this->cashier)->postJson($this->signedUrl('receipts.returns.store', ['sale' => $sale->id]), $this->body($sale, 2))->json('data.sheet_url');

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

        $receipts = $this->actingAs($this->cashier)->getJson($this->url('receipts'));

        $this->assertSignedAddressOf($this->url('receipts.return', ['sale' => $sale->id]), $receipts->json('data.0.return_url'));
        $this->assertSignedAddressOf($this->url('receipts.returns.store', ['sale' => $sale->id]), $receipts->json('data.0.return_store_url'));
    }
}
