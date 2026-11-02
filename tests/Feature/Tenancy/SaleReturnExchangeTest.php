<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Store;
use App\Models\User;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class SaleReturnExchangeTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_return_exchange';

    private Store $store;

    private User $admin;

    private CashShift $adminShift;

    private Product $brush;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_return_exchange', 'receipt_prefix' => 'TRX', 'address' => 'Jl. Uji 1']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->adminShift = $this->openShiftFor($this->admin);
        $this->brush = $this->sellableProduct('Kuas', '100');
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.returns.{$name}", ['tenant' => $this->store->code, ...$parameters]);
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
     * An exchange: `$returned` pieces come back and the customer takes `$quantity` of the unit (PCS 10.000, DUS of
     * 12 for 110.000) instead.
     *
     * @param  list<array{method: string, amount: int}>  $payments
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function exchange(Sale $sale, int $returned, string $unitCode, int $quantity, array $payments = [], array $overrides = []): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'resolution' => 'exchange',
            'refund_method' => 'cash',
            'reason' => 'Salah ukuran',
            'items' => [['sale_item_id' => $sale->items[0]->id, 'quantity' => $returned, 'disposition' => 'resellable']],
            'exchange_items' => [['product_unit_id' => $this->unitOf($this->brush, $unitCode)->id, 'quantity' => $quantity]],
            'exchange_payments' => $payments,
            ...$overrides,
        ];
    }

    /**
     * @return array{0: User, 1: CashShift}
     */
    private function cashierOnSecondRegister(): array
    {
        $cashier = $this->storeUser();
        $register = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));

        return [$cashier, $this->openShiftFor($cashier, $register)];
    }

    private function saleReturn(): SaleReturn
    {
        return $this->store->run(fn (): SaleReturn => SaleReturn::query()->latest('id')->firstOrFail());
    }

    private function exchangeSaleOf(SaleReturn $saleReturn): Sale
    {
        return $this->store->run(fn (): Sale => Sale::query()->with('payments')->findOrFail($saleReturn->exchange_sale_id));
    }

    /**
     * @return array<string, string>
     */
    private function paymentsOf(Sale $sale): array
    {
        return $sale->payments->mapWithKeys(fn ($payment): array => [$payment->method->value => $payment->amount])->all();
    }

    private function expectedCash(CashShift $shift): string
    {
        return $this->store->run(fn (): string => app(ShiftService::class)->summarize($shift)['expected_cash']);
    }

    public function test_a_dearer_replacement_is_paid_with_the_credit_and_the_customers_extra(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)->post($this->url('store'), $this->exchange($sale, 2, 'DUS', 1, [['method' => 'cash', 'amount' => 90000]]))->assertSessionHas('success');

        $saleReturn = $this->saleReturn();
        $exchangeSale = $this->exchangeSaleOf($saleReturn);
        $this->assertSame(['exchange', 'completed', '20000.00', '0.00'], [$saleReturn->resolution->value, $saleReturn->status->value, $saleReturn->credit_amount, $saleReturn->refund_amount]);
        $this->assertSame(['110000.00', '110000.00', $this->admin->id, $this->adminShift->id], [$exchangeSale->total, $exchangeSale->paid_total, $exchangeSale->cashier_id, $exchangeSale->cash_shift_id]);
        $this->assertSame(['return_credit' => '20000.00', 'cash' => '90000.00'], $this->paymentsOf($exchangeSale));
        $this->assertSame('85.000', $this->stockInPieces($this->brush));
        $this->assertSame('140000.00', $this->expectedCash($this->adminShift));
    }

    public function test_the_customer_can_pay_the_extra_with_more_cash_and_get_change(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)->post($this->url('store'), $this->exchange($sale, 2, 'DUS', 1, [['method' => 'cash', 'amount' => 100000]]))->assertSessionHas('success');

        $exchangeSale = $this->exchangeSaleOf($this->saleReturn());
        $this->assertSame(['120000.00', '10000.00'], [$exchangeSale->paid_total, $exchangeSale->change_amount]);
        $this->assertSame('140000.00', $this->expectedCash($this->adminShift));
    }

    public function test_a_cheaper_replacement_gives_the_rest_back_as_a_refund(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)->post($this->url('store'), $this->exchange($sale, 5, 'PCS', 2))->assertSessionHas('success');

        $saleReturn = $this->saleReturn();
        $exchangeSale = $this->exchangeSaleOf($saleReturn);
        $this->assertSame(['50000.00', '30000.00', 'cash'], [$saleReturn->credit_amount, $saleReturn->refund_amount, $saleReturn->refund_method->value]);
        $this->assertSame(['return_credit' => '20000.00'], $this->paymentsOf($exchangeSale));
        $this->assertSame('98.000', $this->stockInPieces($this->brush));
        $this->assertSame('20000.00', $this->expectedCash($this->adminShift));
    }

    public function test_the_rest_of_the_credit_paid_back_by_transfer_needs_the_account(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)->post($this->url('store'), $this->exchange($sale, 5, 'PCS', 2, [], ['refund_method' => 'transfer']))
            ->assertSessionHas('error', 'Isi rekening penerima untuk pengembalian uang non tunai.');
        $this->assertSame(1, $this->store->run(fn () => Sale::query()->count()));

        $this->actingAs($this->admin)->post($this->url('store'), $this->exchange($sale, 5, 'PCS', 2, [], ['refund_method' => 'transfer', 'refund_account' => 'BNI 99 a.n. Sari']))->assertSessionHas('success');
        $this->assertSame(['transfer', 'BNI 99 a.n. Sari', '30000.00'], [$this->saleReturn()->refund_method->value, $this->saleReturn()->refund_account, $this->saleReturn()->refund_amount]);
    }

    public function test_an_exchange_with_nothing_to_pay_back_needs_no_account(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)->post($this->url('store'), $this->exchange($sale, 2, 'DUS', 1, [['method' => 'cash', 'amount' => 90000]], ['refund_method' => 'transfer']))->assertSessionHas('success');

        $this->assertNull($this->saleReturn()->refund_account);
    }

    public function test_an_even_exchange_needs_no_extra_and_gives_nothing_back(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)->post($this->url('store'), $this->exchange($sale, 2, 'PCS', 2))->assertSessionHas('success');

        $saleReturn = $this->saleReturn();
        $this->assertSame('0.00', $saleReturn->refund_amount);
        $this->assertSame(['return_credit' => '20000.00'], $this->paymentsOf($this->exchangeSaleOf($saleReturn)));
        $this->assertSame('50000.00', $this->expectedCash($this->adminShift));
    }

    public function test_an_extra_payment_that_falls_short_is_refused_and_nothing_is_made(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)->post($this->url('store'), $this->exchange($sale, 2, 'DUS', 1, [['method' => 'cash', 'amount' => 50000]]))
            ->assertSessionHas('error', 'Pembayaran kurang dari total belanja Rp 110.000. Harga di layar mungkin sudah berubah: cari ulang barangnya, lalu coba lagi.');

        $this->assertSame(0, $this->store->run(fn () => SaleReturn::query()->count()));
        $this->assertSame(1, $this->store->run(fn () => Sale::query()->count()));
        $this->assertSame('95.000', $this->stockInPieces($this->brush));
    }

    public function test_an_exchange_needs_replacement_goods(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);

        $this->actingAs($this->admin)->post($this->url('store'), $this->exchange($sale, 2, 'PCS', 1, [], ['exchange_items' => []]))->assertSessionHasErrors('exchange_items');
        $this->actingAs($this->admin)->post($this->url('store'), $this->exchange($sale, 2, 'PCS', 0))->assertSessionHasErrors('exchange_items.0.quantity');
        $this->actingAs($this->admin)->post($this->url('store'), $this->exchange($sale, 2, 'PCS', 1, [['method' => 'return_credit', 'amount' => 1]]))->assertSessionHasErrors('exchange_payments.0.method');

        $this->assertSame(0, $this->store->run(fn () => SaleReturn::query()->count()));
    }

    public function test_a_cashiers_exchange_moves_nothing_until_an_admin_approves(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);

        $saleReturn = $this->store->run(fn (): SaleReturn => app(SaleReturnService::class)->create($sale, $cashierShift, $cashier, $this->exchange($sale, 2, 'DUS', 1, [['method' => 'qris', 'amount' => 90000]])));

        $this->assertSame('95.000', $this->stockInPieces($this->brush));
        $this->assertNull($saleReturn->exchange_sale_id);
        $this->assertSame(1, $this->store->run(fn () => Sale::query()->count()));

        $this->actingAs($this->admin)->post($this->url('approve', ['sale_return' => $saleReturn->id]))->assertSessionHas('success');

        $approved = $this->saleReturn();
        $exchangeSale = $this->exchangeSaleOf($approved);
        $this->assertSame('completed', $approved->status->value);
        $this->assertSame([$cashier->id, $cashierShift->id, '110000.00'], [$exchangeSale->cashier_id, $exchangeSale->cash_shift_id, $exchangeSale->total]);
        $this->assertSame(['return_credit' => '20000.00', 'qris' => '90000.00'], $this->paymentsOf($exchangeSale));
        $this->assertSame('85.000', $this->stockInPieces($this->brush));
        $this->assertSame('550000.00', $this->expectedCash($cashierShift), 'the register opens with 500.000, plus the 50.000 of the first sale; the exchange was paid by QRIS');
    }

    public function test_rejecting_a_cashiers_exchange_sells_nothing(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $saleReturn = $this->store->run(fn (): SaleReturn => app(SaleReturnService::class)->create($sale, $cashierShift, $cashier, $this->exchange($sale, 2, 'PCS', 2)));

        $this->actingAs($this->admin)->post($this->url('reject', ['sale_return' => $saleReturn->id]))->assertSessionHas('success');

        $this->assertSame(1, $this->store->run(fn () => Sale::query()->count()));
        $this->assertSame('95.000', $this->stockInPieces($this->brush));
    }

    public function test_approving_fails_cleanly_when_the_replacement_is_out_of_stock(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $saleReturn = $this->store->run(fn (): SaleReturn => app(SaleReturnService::class)->create($sale, $cashierShift, $cashier, $this->exchange($sale, 2, 'DUS', 9, [['method' => 'cash', 'amount' => 1000000]])));

        $this->actingAs($this->admin)->post($this->url('approve', ['sale_return' => $saleReturn->id]))
            ->assertSessionHas('error', fn (string $message): bool => str_starts_with($message, 'Stok Kuas tidak cukup'));

        $this->assertSame('pending', $this->saleReturn()->status->value);
        $this->assertSame('95.000', $this->stockInPieces($this->brush));
        $this->assertSame(1, $this->store->run(fn () => Sale::query()->count()));
    }

    public function test_approving_fails_when_prices_rose_and_the_payment_no_longer_covers_it(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $saleReturn = $this->store->run(fn (): SaleReturn => app(SaleReturnService::class)->create($sale, $cashierShift, $cashier, $this->exchange($sale, 2, 'DUS', 1, [['method' => 'cash', 'amount' => 90000]])));
        $this->store->run(fn () => ProductUnit::query()->whereKey($this->unitOf($this->brush, 'DUS')->id)->update(['selling_price' => 120000]));

        $this->actingAs($this->admin)->post($this->url('approve', ['sale_return' => $saleReturn->id]))->assertSessionHas('error', 'Pembayaran kurang dari total belanja Rp 120.000. Harga di layar mungkin sudah berubah: cari ulang barangnya, lalu coba lagi.');

        $this->assertSame('pending', $this->saleReturn()->status->value);
        $this->assertSame('95.000', $this->stockInPieces($this->brush));
    }

    public function test_approving_stops_when_prices_fell_and_money_is_now_owed_to_a_method_with_no_account(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $saleReturn = $this->store->run(fn (): SaleReturn => app(SaleReturnService::class)->create($sale, $cashierShift, $cashier, $this->exchange($sale, 2, 'PCS', 2, [], ['refund_method' => 'transfer'])));
        $this->assertSame('0.00', $saleReturn->refund_amount);
        $this->store->run(fn () => ProductUnit::query()->whereKey($this->unitOf($this->brush, 'PCS')->id)->update(['selling_price' => 5000]));

        $this->actingAs($this->admin)->post($this->url('approve', ['sale_return' => $saleReturn->id]))
            ->assertSessionHas('error', 'Harga barang pengganti berubah sehingga ada uang yang harus dikembalikan, tetapi rekening penerima belum diisi. Tolak retur ini, lalu minta kasir mengajukannya lagi.');

        $this->assertSame('pending', $this->saleReturn()->status->value);
        $this->assertSame('95.000', $this->stockInPieces($this->brush));
        $this->assertSame(1, $this->store->run(fn () => Sale::query()->count()));
    }

    public function test_approving_stops_when_prices_fell_and_the_customers_extra_payment_is_no_longer_needed(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $saleReturn = $this->store->run(fn (): SaleReturn => app(SaleReturnService::class)->create($sale, $cashierShift, $cashier, $this->exchange($sale, 2, 'DUS', 1, [['method' => 'cash', 'amount' => 90000]])));
        $this->store->run(fn () => ProductUnit::query()->whereKey($this->unitOf($this->brush, 'DUS')->id)->update(['selling_price' => 15000]));

        $this->actingAs($this->admin)->post($this->url('approve', ['sale_return' => $saleReturn->id]))
            ->assertSessionHas('error', 'Harga barang pengganti berubah sehingga pembayaran tambahan pelanggan tidak diperlukan lagi. Tolak retur ini, lalu minta kasir mengajukannya lagi.');

        $this->assertSame('pending', $this->saleReturn()->status->value);
        $this->assertSame(1, $this->store->run(fn () => Sale::query()->count()));
    }

    public function test_approving_when_prices_fell_refunds_the_difference_to_the_account_that_was_given(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $saleReturn = $this->store->run(fn (): SaleReturn => app(SaleReturnService::class)->create($sale, $cashierShift, $cashier, $this->exchange($sale, 2, 'PCS', 2, [], ['refund_method' => 'transfer', 'refund_account' => 'BCA 123 a.n. Budi'])));
        $this->store->run(fn () => ProductUnit::query()->whereKey($this->unitOf($this->brush, 'PCS')->id)->update(['selling_price' => 5000]));

        $this->actingAs($this->admin)->post($this->url('approve', ['sale_return' => $saleReturn->id]));

        $this->assertNull(session('error'));
        $approved = $this->saleReturn();
        $this->assertSame(['completed', '10000.00', 'BCA 123 a.n. Budi'], [$approved->status->value, $approved->refund_amount, $approved->refund_account]);
    }

    public function test_the_return_page_and_slip_show_the_replacement_and_how_it_was_settled(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);
        $this->actingAs($this->admin)->post($this->url('store'), $this->exchange($sale, 2, 'DUS', 1, [['method' => 'cash', 'amount' => 90000]]));
        $saleReturn = $this->saleReturn();
        $exchangeSale = $this->exchangeSaleOf($saleReturn);

        $expected = fn (Assert $page) => $page
            ->where('saleReturn.resolution', 'exchange')
            ->where('saleReturn.resolution_label', 'Tukar barang')
            ->where('saleReturn.credit_amount', '20000.00')
            ->where('saleReturn.exchange.total', '110000.00')
            ->where('saleReturn.exchange.items.0.name', 'Kuas')
            ->where('saleReturn.exchange.items.0.unit', 'DUS')
            ->where('saleReturn.exchange.payments.0', ['method' => 'Tunai', 'amount' => '90000.00'])
            ->where('saleReturn.exchange.sale_number', $exchangeSale->number)
            ->where('saleReturn.exchange.print_url', route('tenant.cashier.receipts.print', ['tenant' => $this->store->code, 'sale' => $exchangeSale->id]))
            ->missing('saleReturn.exchange.sheet_url');

        $this->actingAs($this->admin)->get($this->url('show', ['sale_return' => $saleReturn->id]))->assertInertia($expected);
        $this->actingAs($this->admin)->get($this->url('sheet', ['sale_return' => $saleReturn->id]))->assertInertia($expected);
        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('returns.data.0.resolution_label', 'Tukar barang')
            ->where('returns.data.0.credit_amount', '20000.00'));
    }

    public function test_the_replacement_receipt_can_be_printed_by_an_admin_and_by_the_cashier_after_approval(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $saleReturn = $this->store->run(fn (): SaleReturn => app(SaleReturnService::class)->create($sale, $cashierShift, $cashier, $this->exchange($sale, 2, 'PCS', 2)));

        $this->actingAs($this->admin)->get($this->url('show', ['sale_return' => $saleReturn->id]))
            ->assertInertia(fn (Assert $page) => $page->where('saleReturn.exchange.print_url', null));

        $this->actingAs($this->admin)->post($this->url('approve', ['sale_return' => $saleReturn->id]));
        $exchangeSale = $this->exchangeSaleOf($this->saleReturn());
        $print = route('tenant.cashier.receipts.print', ['tenant' => $this->store->code, 'sale' => $exchangeSale->id]);

        $this->actingAs($this->admin)->postJson($print)->assertOk()->assertJsonPath('data.print_count', 1);
        $sheet = $this->actingAs($cashier)->postJson($print)->assertOk()->assertJsonPath('data.is_copy', true)->json('data.sheet_url');
        $this->actingAs($cashier)->get($sheet)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Print/Receipt')
            ->where('receipt.number', $exchangeSale->number)
            ->where('receipt.is_copy', true)
            ->where('receipt.payments.0.method', 'Kredit retur'));
        $this->actingAs($this->storeUser())->postJson($print)->assertForbidden();
    }

    public function test_the_till_can_ask_for_an_exchange(): void
    {
        [$cashier, $cashierShift] = $this->cashierOnSecondRegister();
        $sale = $this->sellBrushes($cashier, $cashierShift, 5);
        $body = $this->exchange($sale, 2, 'DUS', 1, [['method' => 'cash', 'amount' => 90000]]);
        unset($body['sale_id']);

        $this->actingAs($cashier)->postJson(URL::temporarySignedRoute('tenant.cashier.receipts.returns.store', now()->addHour(), ['tenant' => $this->store->code, 'sale' => $sale->id]), $body)
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.resolution', 'exchange')
            ->assertJsonPath('data.exchange.total', '110000.00')
            ->assertJsonPath('data.exchange.sale_number', null);
    }

    public function test_the_shift_counts_the_value_of_returned_goods_in_an_exchange(): void
    {
        $sale = $this->sellBrushes($this->admin, $this->adminShift, 5);
        $this->actingAs($this->admin)->post($this->url('store'), $this->exchange($sale, 2, 'DUS', 1, [['method' => 'cash', 'amount' => 90000]]));

        $summary = $this->store->run(fn (): array => app(ShiftService::class)->summarize($this->adminShift));
        $this->assertSame(['20000.00', '0.00', 2], [$summary['returns_total'], $summary['cash_refunds'], $summary['sale_count']]);
    }
}
