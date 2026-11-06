<?php

namespace Tests\Feature\Tenancy;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\CashShift;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class CustomerOrderTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_customer_order';

    private Store $store;

    private User $cashier;

    private Product $brush;

    private Product $paint;

    private CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_customer_order', 'receipt_prefix' => 'TCO']);
        $this->cashier = $this->storeUser();
        $this->brush = $this->sellableProduct('Kuas', '50');
        $this->paint = $this->sellableProduct('Cat', '0');
        $this->shift = $this->openShiftFor($this->cashier);
    }

    private function salesUrl(): string
    {
        return route('tenant.cashier.sales.store', ['tenant' => $this->store->code]);
    }

    private function orderUrl(string $action, CustomerOrder $order): string
    {
        return route("tenant.cashier.orders.{$action}", ['tenant' => $this->store->code, 'order' => $order->id]);
    }

    /**
     * @param  list<array{0: Product, 1: int|float}>  $lines
     * @return list<array{product_unit_id: int, quantity: int|float}>
     */
    private function lines(array $lines): array
    {
        return array_map(fn (array $line): array => ['product_unit_id' => $this->unitOf($line[0], 'PCS')->id, 'quantity' => $line[1]], $lines);
    }

    /**
     * @param  list<array{0: Product, 1: int|float}>  $lines
     * @return array<string, mixed>
     */
    private function orderPayload(array $lines, int|float|null $depositAmount = null): array
    {
        return array_filter([
            'client_uuid' => (string) Str::uuid(),
            'customer_name' => 'Pak Budi',
            'customer_phone' => '08123456789',
            'source_note' => 'Beli di toko sebelah',
            'items' => $this->lines($lines),
            'deposit_amount' => $depositAmount,
        ]);
    }

    /**
     * An order alone; a deposit is paid with the method given, exactly.
     *
     * @param  list<array{0: Product, 1: int|float}>  $lines
     * @param  array{method: string, amount: int}|null  $deposit
     */
    private function placeOrder(array $lines, ?array $deposit = null): CustomerOrder
    {
        $this->actingAs($this->cashier)->post($this->salesUrl(), array_filter([
            'client_uuid' => (string) Str::uuid(),
            'order' => $this->orderPayload($lines, $deposit['amount'] ?? null),
            'payments' => $deposit === null ? null : [$deposit],
        ]))->assertSessionHas('last_order');

        return $this->store->run(fn (): CustomerOrder => CustomerOrder::query()->latest('id')->firstOrFail());
    }

    private function putInStock(Product $product, string $pieces): void
    {
        $this->store->run(fn () => Stock::query()->updateOrInsert(
            ['product_id' => $product->id, 'warehouse_id' => $this->mainWarehouse()->id],
            ['quantity' => $pieces],
        ));
    }

    private function expectedCash(): string
    {
        return $this->store->run(fn (): string => app(ShiftService::class)->summarize($this->shift)['expected_cash']);
    }

    private function reloaded(CustomerOrder $order): CustomerOrder
    {
        return $this->store->run(fn (): CustomerOrder => CustomerOrder::query()->with(['items', 'payments'])->findOrFail($order->id));
    }

    private function collect(CustomerOrder $order, array $payments): TestResponse
    {
        return $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'customer_order_id' => $order->id,
            'payments' => $payments,
        ]);
    }

    public function test_a_cart_beyond_the_stock_is_split_into_a_receipt_and_an_order(): void
    {
        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->brush, 50]]),
            'payments' => [['method' => 'cash', 'amount' => 600000]],
            'order' => $this->orderPayload([[$this->brush, 50], [$this->paint, 2]], 100000),
        ])->assertSessionHas('last_sale')->assertSessionHas('last_order');

        $this->assertSame('0.000', $this->stockInPieces($this->brush));

        $sale = $this->store->run(fn (): Sale => Sale::query()->firstOrFail());
        $order = $this->reloaded($this->store->run(fn (): CustomerOrder => CustomerOrder::query()->firstOrFail()));

        $this->assertSame('500000.00', (string) $sale->total);
        $this->assertSame(OrderStatus::Ordered, $order->status);
        $this->assertSame('520000.00', (string) $order->total);
        $this->assertCount(2, $order->items);
        $this->assertSame($sale->id, $order->origin_sale_id);
        $this->assertSame('100000.00', (string) $order->depositBalance()->toScale(2));
        $this->assertSame('0.000', $this->stockInPieces($this->paint));
        $this->assertSame('600000.00', $this->expectedCash());
    }

    public function test_the_customer_can_pay_the_receipt_and_the_whole_order_in_one_cash_payment_with_one_change(): void
    {
        $this->putInStock($this->brush, '60');

        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->brush, 60]]),
            'payments' => [['method' => 'cash', 'amount' => 1000000]],
            'order' => $this->orderPayload([[$this->paint, 20]], 200000),
        ])->assertSessionHas('last_sale')->assertSessionHas('last_order');

        $sale = $this->store->run(fn (): Sale => Sale::query()->with('payments')->firstOrFail());
        $order = $this->reloaded($this->store->run(fn (): CustomerOrder => CustomerOrder::query()->firstOrFail()));

        $this->assertSame('600000.00', (string) $sale->total);
        $this->assertSame('200000.00', (string) $sale->change_amount);
        $this->assertSame('800000.00', (string) $sale->paid_total);
        $this->assertSame('200000.00', (string) $order->depositBalance()->toScale(2));
        $this->assertSame('800000.00', $this->expectedCash());
    }

    public function test_a_deposit_paid_together_with_the_receipt_follows_the_methods_that_were_handed_over(): void
    {
        $this->putInStock($this->brush, '60');

        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->brush, 60]]),
            'payments' => [['method' => 'cash', 'amount' => 100000], ['method' => 'qris', 'amount' => 700000]],
            'order' => $this->orderPayload([[$this->paint, 20]], 200000),
        ])->assertSessionHas('last_sale');

        $order = $this->reloaded($this->store->run(fn (): CustomerOrder => CustomerOrder::query()->firstOrFail()));
        $sale = $this->store->run(fn (): Sale => Sale::query()->with('payments')->firstOrFail());

        $this->assertSame(['qris:200000.00'], $order->payments->map(fn ($payment): string => "{$payment->method->value}:{$payment->amount}")->all());
        $this->assertEquals(600000, $sale->payments->sum('amount'));
        $this->assertSame('100000.00', $this->expectedCash());
    }

    public function test_the_tender_must_cover_the_receipt_and_the_deposit_together(): void
    {
        $this->putInStock($this->brush, '60');

        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->brush, 60]]),
            'payments' => [['method' => 'cash', 'amount' => 700000]],
            'order' => $this->orderPayload([[$this->paint, 20]], 200000),
        ])->assertSessionHas('error');

        $this->assertSame(0, $this->store->run(fn (): int => Sale::query()->count()));
        $this->assertSame(0, $this->store->run(fn (): int => CustomerOrder::query()->count()));
    }

    public function test_change_for_a_deposit_cannot_come_from_a_non_cash_payment(): void
    {
        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'order' => $this->orderPayload([[$this->paint, 20]], 100000),
            'payments' => [['method' => 'qris', 'amount' => 150000]],
        ])->assertSessionHas('error', 'Kembalian hanya bisa diberikan dari pembayaran tunai.');

        $this->assertSame(0, $this->store->run(fn (): int => CustomerOrder::query()->count()));
    }

    public function test_a_deposit_cannot_be_combined_with_cod(): void
    {
        $this->putInStock($this->brush, '60');

        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->brush, 60]]),
            'payments' => [['method' => 'cod', 'amount' => 800000]],
            'delivery_note' => ['recipient_name' => 'Pak Budi', 'recipient_address' => 'Jl. Mawar'],
            'order' => $this->orderPayload([[$this->paint, 20]], 200000),
        ])->assertSessionHas('error');

        $this->assertSame(0, $this->store->run(fn (): int => Sale::query()->count()));
    }

    public function test_a_cart_with_nothing_in_stock_makes_only_an_order(): void
    {
        $order = $this->placeOrder([[$this->paint, 3]]);

        $this->assertSame(0, $this->store->run(fn (): int => Sale::query()->count()));
        $this->assertSame('30000.00', (string) $order->total);
        $this->assertSame('0.00', $this->expectedCash());
    }

    public function test_an_order_needs_the_name_and_phone_of_the_customer(): void
    {
        $payload = $this->orderPayload([[$this->paint, 1]]);
        unset($payload['customer_phone']);

        $this->actingAs($this->cashier)->post($this->salesUrl(), ['client_uuid' => (string) Str::uuid(), 'order' => $payload])
            ->assertSessionHasErrors('order.customer_phone');

        $this->assertSame(0, $this->store->run(fn (): int => CustomerOrder::query()->count()));
    }

    public function test_a_deposit_cannot_be_more_than_the_order(): void
    {
        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'order' => $this->orderPayload([[$this->paint, 1]], 10001),
            'payments' => [['method' => 'cash', 'amount' => 10001]],
        ])->assertSessionHas('error', 'Uang muka tidak boleh lebih besar dari total pesanan.');

        $this->assertSame(0, $this->store->run(fn (): int => CustomerOrder::query()->count()));
    }

    public function test_submitting_the_same_order_twice_makes_one_order(): void
    {
        $payload = ['client_uuid' => (string) Str::uuid(), 'order' => $this->orderPayload([[$this->paint, 1]], 5000), 'payments' => [['method' => 'cash', 'amount' => 5000]]];

        $this->actingAs($this->cashier)->post($this->salesUrl(), $payload)->assertSessionHas('last_order');
        $this->actingAs($this->cashier)->post($this->salesUrl(), $payload)->assertSessionHas('last_order');

        $this->assertSame(1, $this->store->run(fn (): int => CustomerOrder::query()->count()));
        $this->assertSame('5000.00', $this->expectedCash());
    }

    public function test_goods_can_only_be_marked_as_arrived_once_they_are_in_stock(): void
    {
        $order = $this->placeOrder([[$this->paint, 4]]);

        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))
            ->assertUnprocessable()
            ->assertJsonPath('code', 4224);
        $this->assertSame(OrderStatus::Ordered, $this->reloaded($order)->status);

        $this->putInStock($this->paint, '4');

        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))->assertOk();
        $this->assertSame(OrderStatus::Ready, $this->reloaded($order)->status);
    }

    public function test_goods_set_aside_for_a_ready_order_cannot_be_sold_to_someone_else(): void
    {
        $order = $this->placeOrder([[$this->paint, 4]]);
        $this->putInStock($this->paint, '5');
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))->assertOk();

        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->paint, 2]]),
            'payments' => [['method' => 'cash', 'amount' => 20000]],
        ])->assertSessionHas('error', 'Stok Cat tidak cukup, tersisa 1 PCS.');

        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->paint, 1]]),
            'payments' => [['method' => 'cash', 'amount' => 10000]],
        ])->assertSessionHas('last_sale');
    }

    public function test_collecting_an_order_makes_a_receipt_with_the_deposit_as_a_payment(): void
    {
        $order = $this->placeOrder([[$this->paint, 10]], ['method' => 'cash', 'amount' => 40000]);
        $this->putInStock($this->paint, '10');
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))->assertOk();

        $this->collect($order, [['method' => 'cash', 'amount' => 60000]])->assertSessionHas('last_sale');

        $sale = $this->store->run(fn (): Sale => Sale::query()->with('payments')->firstOrFail());
        $order = $this->reloaded($order);

        $this->assertSame('100000.00', (string) $sale->total);
        $this->assertEqualsCanonicalizing(['cash:60000.00', 'order_deposit:40000.00'], $sale->payments->map(fn ($payment): string => "{$payment->method->value}:{$payment->amount}")->all());
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame($sale->id, $order->sale_id);
        $this->assertSame('0.000', $this->stockInPieces($this->paint));
        $this->assertSame('100000.00', $this->expectedCash());
    }

    public function test_the_price_agreed_on_the_order_holds_when_the_list_price_changes(): void
    {
        $order = $this->placeOrder([[$this->paint, 10]]);
        $this->store->run(fn () => $this->unitOf($this->paint, 'PCS')->forceFill(['selling_price' => 20000])->save());
        $this->putInStock($this->paint, '10');
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))->assertOk();

        $this->collect($order, [['method' => 'cash', 'amount' => 100000]])->assertSessionHas('last_sale');

        $sale = $this->store->run(fn (): Sale => Sale::query()->with('items')->firstOrFail());

        $this->assertSame('100000.00', (string) $sale->total);
        $this->assertSame('10000.00', (string) $sale->items->first()->unit_price);
    }

    public function test_collecting_still_needs_the_rest_to_be_paid(): void
    {
        $order = $this->placeOrder([[$this->paint, 10]], ['method' => 'cash', 'amount' => 40000]);
        $this->putInStock($this->paint, '10');
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))->assertOk();

        $this->collect($order, [['method' => 'cash', 'amount' => 50000]])->assertSessionHas('error');

        $this->assertSame(OrderStatus::Ready, $this->reloaded($order)->status);
        $this->assertSame(0, $this->store->run(fn (): int => Sale::query()->count()));
    }

    public function test_an_order_whose_goods_have_not_arrived_cannot_be_collected(): void
    {
        $order = $this->placeOrder([[$this->paint, 1]]);

        $this->collect($order, [['method' => 'cash', 'amount' => 10000]])
            ->assertSessionHas('error', 'Pesanan ini berstatus Dipesan. Hanya pesanan yang siap diambil yang bisa dibayar dan diambil.');
    }

    public function test_a_collected_order_cannot_be_collected_again(): void
    {
        $order = $this->placeOrder([[$this->paint, 1]]);
        $this->putInStock($this->paint, '2');
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))->assertOk();
        $this->collect($order, [['method' => 'cash', 'amount' => 10000]])->assertSessionHas('last_sale');

        $this->collect($order, [['method' => 'cash', 'amount' => 10000]])->assertSessionHas('error');

        $this->assertSame(1, $this->store->run(fn (): int => Sale::query()->count()));
    }

    public function test_cancelling_an_order_gives_the_cash_deposit_back_from_the_drawer(): void
    {
        $order = $this->placeOrder([[$this->paint, 10]], ['method' => 'cash', 'amount' => 40000]);
        $this->assertSame('40000.00', $this->expectedCash());

        $this->actingAs($this->cashier)->postJson($this->orderUrl('cancel', $order), ['reason' => 'Pelanggan batal'])->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $this->reloaded($order)->status);
        $this->assertSame('0.00', $this->expectedCash());

        $this->actingAs($this->cashier)->postJson($this->orderUrl('cancel', $order), ['reason' => 'Lagi'])->assertUnprocessable();
        $this->assertSame('0.00', $this->expectedCash());
    }

    public function test_a_non_cash_deposit_needs_the_account_to_give_it_back_to(): void
    {
        $order = $this->placeOrder([[$this->paint, 10]], ['method' => 'transfer', 'amount' => 40000]);

        $this->actingAs($this->cashier)->postJson($this->orderUrl('cancel', $order), ['reason' => 'Batal'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Uang muka dibayar non tunai. Isi rekening penerima pengembaliannya.');
        $this->assertSame(OrderStatus::Ordered, $this->reloaded($order)->status);

        $this->actingAs($this->cashier)->postJson($this->orderUrl('cancel', $order), ['reason' => 'Batal', 'refund_account' => 'BCA 123 a.n. Budi'])->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $this->reloaded($order)->status);
        $this->assertSame('0.00', $this->expectedCash());
    }

    public function test_the_day_closing_keeps_deposits_apart_from_sales(): void
    {
        $order = $this->placeOrder([[$this->paint, 10]], ['method' => 'transfer', 'amount' => 40000]);
        $this->placeOrder([[$this->paint, 5]], ['method' => 'cash', 'amount' => 10000]);
        $this->actingAs($this->cashier)->postJson($this->orderUrl('cancel', $order), ['reason' => 'Batal', 'refund_account' => 'BCA 123'])->assertOk();

        $figures = $this->store->run(fn (): array => app(DailyClosingService::class)->summarize(today()));

        $this->assertSame('50000.00', $figures['order_deposits_received']);
        $this->assertSame('40000.00', $figures['order_deposits_refunded']);
        $this->assertSame('0.00', $figures['total_sales']);
    }

    public function test_the_open_orders_are_listed_for_the_till_with_ready_ones_first(): void
    {
        $waiting = $this->placeOrder([[$this->paint, 1]]);
        $this->putInStock($this->paint, '3');
        $ready = $this->placeOrder([[$this->paint, 2]]);
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $ready))->assertOk();

        $this->actingAs($this->cashier)->getJson(route('tenant.cashier.orders.index', ['tenant' => $this->store->code]))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.number', $ready->number)
            ->assertJsonPath('data.0.can_collect', true)
            ->assertJsonPath('data.1.number', $waiting->number);
    }

    public function test_the_order_slip_is_counted_and_printed_from_a_signed_sheet(): void
    {
        $order = $this->placeOrder([[$this->paint, 2]], ['method' => 'cash', 'amount' => 5000]);

        $sheetUrl = $this->actingAs($this->cashier)->postJson($this->orderUrl('print', $order))
            ->assertOk()
            ->assertJsonPath('data.is_copy', false)
            ->json('data.sheet_url');

        $this->actingAs($this->cashier)->get($sheetUrl)->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Print/Order')
            ->where('order.number', $order->number)
            ->where('order.deposit', '5000.00')
            ->where('order.remaining', '15000.00')
            ->where('order.is_copy', false));

        $this->actingAs($this->cashier)->get(route('tenant.cashier.orders.sheet', ['tenant' => $this->store->code, 'order' => $order->id]))->assertForbidden();

        $this->actingAs($this->cashier)->postJson($this->orderUrl('print', $order))->assertJsonPath('data.is_copy', true);
    }

    public function test_the_till_knows_how_many_orders_are_open_and_ready(): void
    {
        $this->placeOrder([[$this->paint, 1]]);
        $ready = $this->placeOrder([[$this->paint, 1]]);
        $this->putInStock($this->paint, '5');
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $ready))->assertOk();

        $this->actingAs($this->cashier)->get(route('tenant.cashier.index', ['tenant' => $this->store->code]))
            ->assertInertia(fn (Assert $page) => $page->where('open_order_count', 2)->where('ready_order_count', 1));
    }

    public function test_a_sold_out_product_shows_what_is_set_aside_for_ready_orders(): void
    {
        $order = $this->placeOrder([[$this->paint, 4]]);
        $this->putInStock($this->paint, '10');
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))->assertOk();

        $this->actingAs($this->cashier)->getJson(route('tenant.cashier.products', ['tenant' => $this->store->code, 'q' => 'Cat']))
            ->assertJsonPath('data.0.stock', '6.000')
            ->assertJsonPath('data.0.reserved', '4.000');
    }

    public function test_the_payment_methods_of_the_till_do_not_offer_the_order_deposit(): void
    {
        $this->assertNotContains(PaymentMethod::OrderDeposit->value, array_column(PaymentMethod::saleOptions(), 'value'));
        $this->assertNotContains(PaymentMethod::OrderDeposit->value, array_column(PaymentMethod::cashierOptions(), 'value'));
    }
}
