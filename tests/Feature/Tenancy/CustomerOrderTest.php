<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DeliveryNoteStatus;
use App\Enums\OrderPaymentKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderPayment;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Services\DeliveryNoteService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->assertInertia(fn (Assert $page) => $page->where('order_counts', ['open' => 2, 'ready' => 1, 'arrived' => 1]));
    }

    public function test_the_list_tells_which_waiting_orders_the_stock_already_covers_oldest_first(): void
    {
        $first = $this->placeOrder([[$this->paint, 4]]);
        $second = $this->placeOrder([[$this->paint, 4]]);
        $this->putInStock($this->paint, '5');

        $response = $this->actingAs($this->cashier)->getJson(route('tenant.cashier.orders.index', ['tenant' => $this->store->code]))->assertOk();

        $response->assertJsonPath('data.0.number', $first->number)
            ->assertJsonPath('data.0.stock_check.is_covered', true)
            ->assertJsonPath('data.1.number', $second->number)
            ->assertJsonPath('data.1.stock_check.is_covered', false)
            ->assertJsonPath('data.1.stock_check.shortages.0.name', 'Cat')
            ->assertJsonPath('data.1.stock_check.shortages.0.text', '3 PCS');

        $this->actingAs($this->cashier)->get(route('tenant.cashier.index', ['tenant' => $this->store->code]))
            ->assertInertia(fn (Assert $page) => $page->where('order_counts.arrived', 1));
    }

    public function test_an_order_with_no_stock_at_all_is_short_by_everything(): void
    {
        $this->placeOrder([[$this->paint, 2]]);

        $this->actingAs($this->cashier)->getJson(route('tenant.cashier.orders.index', ['tenant' => $this->store->code]))
            ->assertJsonPath('data.0.stock_check.is_covered', false)
            ->assertJsonPath('data.0.stock_check.shortages.0.text', '2 PCS');
    }

    public function test_returns_waiting_for_approval_are_counted_for_the_till_and_the_sidebar(): void
    {
        $this->store->run(function (): void {
            $sale = Sale::factory()->create();

            SaleReturn::factory()->create(['sale_id' => $sale->id, 'register_id' => $sale->register_id, 'cash_shift_id' => $this->shift->id]);
            SaleReturn::factory()->create(['sale_id' => $sale->id, 'register_id' => $sale->register_id, 'cash_shift_id' => $sale->cash_shift_id]);
        });

        $this->actingAs($this->cashier)->get(route('tenant.cashier.index', ['tenant' => $this->store->code]))
            ->assertInertia(fn (Assert $page) => $page->where('pending_return_count', 1)->where('menu_badges.returns', 2));
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

    public function test_an_order_keeps_the_address_and_its_cod_note_is_made_without_driver_or_vehicle(): void
    {
        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'order' => [...$this->orderPayload([[$this->paint, 20]], 150000), 'customer_address' => 'Jl. Mawar 5, Sidoarjo'],
            'payments' => [['method' => 'cash', 'amount' => 150000]],
        ])->assertSessionHas('last_order');

        $order = $this->reloaded($this->store->run(fn (): CustomerOrder => CustomerOrder::query()->firstOrFail()));
        $this->assertSame('Jl. Mawar 5, Sidoarjo', $order->customer_address);

        $this->putInStock($this->paint, '20');
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))->assertOk();

        $this->actingAs($this->cashier)->getJson(route('tenant.cashier.orders.index', ['tenant' => $this->store->code]))
            ->assertJsonPath('data.0.customer_address', 'Jl. Mawar 5, Sidoarjo');

        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'customer_order_id' => $order->id,
            'payments' => [['method' => 'cod', 'amount' => 50000]],
            'delivery_note' => ['recipient_name' => 'Pak Budi', 'recipient_phone' => '08123456789', 'recipient_address' => 'Jl. Mawar 5, Sidoarjo'],
        ])->assertSessionHas('last_sale');

        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->sole());

        $this->assertSame('Jl. Mawar 5, Sidoarjo', $note->recipient_address);
        $this->assertNull($note->driver_name);
        $this->assertNull($note->vehicle_number);

        $admin = $this->storeUser(UserRole::StoreAdmin);

        $this->actingAs($admin)->post(route('tenant.delivery-notes.ship', ['tenant' => $this->store->code, 'delivery_note' => $note->id]));

        $shipped = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->findOrFail($note->id));

        $this->assertSame(DeliveryNoteStatus::Shipped, $shipped->status);
        $this->assertNull($shipped->driver_name);
    }

    /**
     * A receipt for what was in stock (50 brushes, paid, not delivered) made together with an order for 20 paint tins that
     * has since arrived and is ready to be collected.
     *
     * @return array{0: Sale, 1: CustomerOrder}
     */
    private function receiptAndReadyOrder(): array
    {
        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->brush, 50]]),
            'payments' => [['method' => 'cash', 'amount' => 500000]],
            'order' => $this->orderPayload([[$this->paint, 20]]),
        ])->assertSessionHas('last_order');

        $order = $this->store->run(fn (): CustomerOrder => CustomerOrder::query()->firstOrFail());
        $this->putInStock($this->paint, '20');
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))->assertOk();

        return [$this->store->run(fn (): Sale => Sale::query()->findOrFail($order->origin_sale_id)), $order];
    }

    private function collectByCod(CustomerOrder $order, bool $isIncludingOriginSale): TestResponse
    {
        return $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'customer_order_id' => $order->id,
            'payments' => [['method' => 'cod', 'amount' => 200000]],
            'delivery_note' => ['recipient_name' => 'Pak Budi', 'recipient_address' => 'Jl. Mawar 5', 'include_linked_sale_items' => $isIncludingOriginSale],
        ]);
    }

    public function test_the_order_list_shows_what_of_the_receipt_made_with_it_is_still_to_be_delivered(): void
    {
        [$sale, $order] = $this->receiptAndReadyOrder();

        $this->actingAs($this->cashier)->getJson(route('tenant.cashier.orders.index', ['tenant' => $this->store->code]))
            ->assertJsonPath('data.0.origin_sale_number', $sale->number)
            ->assertJsonPath('data.0.origin_sale_undelivered.0.name', 'Kuas')
            ->assertJsonPath('data.0.origin_sale_undelivered.0.remaining', '50.000');
    }

    public function test_one_delivery_note_can_carry_the_order_and_the_undelivered_goods_of_the_receipt_made_with_it(): void
    {
        [$sale, $order] = $this->receiptAndReadyOrder();

        $this->collectByCod($order, true)->assertSessionHas('last_sale');

        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->with('items')->sole());
        $collectingSale = $this->store->run(fn (): Sale => Sale::query()->findOrFail($this->reloaded($order)->sale_id));

        $this->assertSame($collectingSale->id, $note->sale_id);
        $this->assertEqualsCanonicalizing(['Cat:20.000', 'Kuas:50.000'], $note->items->map(fn ($item): string => "{$item->product_name}:{$item->quantity}")->all());

        $stillToDeliver = $this->store->run(fn (): array => app(DeliveryNoteService::class)->deliverableLines($collectingSale));
        $this->assertSame(['0.000', '0.000'], array_column($stillToDeliver, 'remaining'));
        $this->assertSame(1, $this->store->run(fn (): int => DeliveryNote::query()->count()));
    }

    public function test_the_receipt_of_the_order_stays_off_the_note_unless_the_cashier_includes_it(): void
    {
        [$sale, $order] = $this->receiptAndReadyOrder();

        $this->collectByCod($order, false)->assertSessionHas('last_sale');

        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->with('items')->sole());

        $this->assertSame(['Cat'], $note->items->pluck('product_name')->all());
        $this->assertSame('50.000', $this->store->run(fn (): string => collect(app(DeliveryNoteService::class)->deliverableLines($sale))->firstWhere('name', 'Kuas')['remaining']));
    }

    public function test_cancelling_the_combined_note_frees_the_goods_of_both_receipts(): void
    {
        [$sale, $order] = $this->receiptAndReadyOrder();
        $this->collectByCod($order, true)->assertSessionHas('last_sale');
        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->sole());

        $this->store->run(fn () => app(DeliveryNoteService::class)->cancel($note));

        $this->assertSame('50.000', $this->store->run(fn (): string => collect(app(DeliveryNoteService::class)->deliverableLines($sale))->firstWhere('name', 'Kuas')['remaining']));
    }

    public function test_the_receipt_and_the_order_made_with_it_print_on_one_paper_that_adds_up(): void
    {
        $this->putInStock($this->brush, '60');

        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->brush, 60]]),
            'payments' => [['method' => 'cash', 'amount' => 1000000]],
            'order' => $this->orderPayload([[$this->paint, 20]], 200000),
        ])->assertSessionHas('last_sale');

        $sale = $this->store->run(fn (): Sale => Sale::query()->firstOrFail());
        $order = $this->store->run(fn (): CustomerOrder => CustomerOrder::query()->firstOrFail());

        $sheetUrl = $this->actingAs($this->cashier)->postJson(route('tenant.cashier.receipts.print', ['tenant' => $this->store->code, 'sale' => $sale->id]))->json('data.sheet_url');

        $this->actingAs($this->cashier)->get($sheetUrl)->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Print/Receipt')
            ->where('receipt.total', '600000.00')
            ->where('receipt.change_amount', '200000.00')
            ->where('receipt.payments', [['method' => 'Tunai', 'amount' => '1000000.00', 'reference' => null]])
            ->where('receipt.order.number', $order->number)
            ->where('receipt.order.deposit', '200000.00')
            ->where('receipt.order.remaining', '0.00'));
    }

    public function test_a_receipt_without_an_order_prints_as_before(): void
    {
        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->brush, 5]]),
            'payments' => [['method' => 'cash', 'amount' => 50000]],
        ])->assertSessionHas('last_sale');

        $sale = $this->store->run(fn (): Sale => Sale::query()->firstOrFail());
        $sheetUrl = $this->actingAs($this->cashier)->postJson(route('tenant.cashier.receipts.print', ['tenant' => $this->store->code, 'sale' => $sale->id]))->json('data.sheet_url');

        $this->actingAs($this->cashier)->get($sheetUrl)->assertInertia(fn (Assert $page) => $page->where('receipt.order', null)->has('receipt.payments', 1));
    }

    /**
     * A COD receipt for 50 brushes made together with an order for 20 paint tins, with the delivery held for the order,
     * and the order's goods arrived and ready.
     *
     * @return array{0: Sale, 1: CustomerOrder}
     */
    private function heldCodReceiptAndReadyOrder(): array
    {
        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->brush, 50]]),
            'payments' => [['method' => 'cod', 'amount' => 500000]],
            'hold_delivery' => true,
            'order' => $this->orderPayload([[$this->paint, 20]]),
        ])->assertSessionHas('last_sale');

        $order = $this->store->run(fn (): CustomerOrder => CustomerOrder::query()->firstOrFail());
        $this->putInStock($this->paint, '20');
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))->assertOk();

        return [$this->store->run(fn (): Sale => Sale::query()->findOrFail($order->origin_sale_id)), $order];
    }

    private function codUrl(string $action, Sale $sale): string
    {
        return route("tenant.cashier.cod.{$action}", ['tenant' => $this->store->code, 'sale' => $sale->id]);
    }

    public function test_a_cod_receipt_made_with_an_order_can_wait_for_the_order_without_a_delivery_note(): void
    {
        [$sale] = $this->heldCodReceiptAndReadyOrder();

        $this->assertSame(0, $this->store->run(fn (): int => DeliveryNote::query()->count()));
        $this->assertSame('0.000', $this->stockInPieces($this->brush));
        $this->assertTrue($this->store->run(fn (): bool => Sale::query()->with('payments')->findOrFail($sale->id)->isCod()));
    }

    public function test_delivery_cannot_be_held_without_an_order(): void
    {
        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->brush, 5]]),
            'payments' => [['method' => 'cod', 'amount' => 50000]],
            'hold_delivery' => true,
        ])->assertSessionHasErrors('hold_delivery');

        $this->assertSame(0, $this->store->run(fn (): int => Sale::query()->count()));
    }

    public function test_one_delivery_note_and_one_delivered_mark_serve_both_cod_receipts(): void
    {
        [$heldSale, $order] = $this->heldCodReceiptAndReadyOrder();

        $this->collectByCod($order, true)->assertSessionHas('last_sale');
        $collectingSale = $this->store->run(fn (): Sale => Sale::query()->findOrFail($this->reloaded($order)->sale_id));

        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->with('items')->sole());
        $this->assertCount(2, $note->items);
        $this->assertEqualsCanonicalizing([$heldSale->id, $collectingSale->id], $note->sales()->pluck('sales.id')->all());

        $waiting = $this->actingAs($this->cashier)->getJson(route('tenant.cashier.cod.index', ['tenant' => $this->store->code]))->json('data.*.number');
        $this->assertEqualsCanonicalizing([$heldSale->number, $collectingSale->number], $waiting);

        $this->actingAs($this->cashier)->postJson($this->codUrl('delivered', $collectingSale))->assertOk();

        $this->actingAs($this->cashier)->getJson(route('tenant.cashier.cod.index', ['tenant' => $this->store->code]))->assertJsonPath('meta.total', 0);
    }

    public function test_a_receipt_on_a_combined_note_cannot_be_voided_until_the_note_is_cancelled(): void
    {
        [$heldSale, $order] = $this->heldCodReceiptAndReadyOrder();
        $this->collectByCod($order, true)->assertSessionHas('last_sale');
        $collectingSale = $this->store->run(fn (): Sale => Sale::query()->findOrFail($this->reloaded($order)->sale_id));

        $this->actingAs($this->cashier)->postJson($this->codUrl('void', $heldSale), ['reason' => 'Batal'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 4223);

        $this->actingAs($this->cashier)->postJson($this->codUrl('void', $collectingSale), ['reason' => 'Batal'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Nota pengambilan pesanan tidak bisa di-void. Pakai Retur.');

        $this->store->run(fn () => app(DeliveryNoteService::class)->cancel(DeliveryNote::query()->sole()));

        $this->actingAs($this->cashier)->postJson($this->codUrl('void', $heldSale), ['reason' => 'Batal'])->assertOk();
        $this->assertSame('50.000', $this->stockInPieces($this->brush));
        $this->assertSame(OrderStatus::Completed, $this->reloaded($order)->status);
    }

    public function test_a_receipt_on_a_combined_note_that_came_back_whole_can_be_voided(): void
    {
        [$heldSale, $order] = $this->heldCodReceiptAndReadyOrder();
        $this->collectByCod($order, true)->assertSessionHas('last_sale');
        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->with('items')->sole());

        $this->store->run(function () use ($note): void {
            $service = app(DeliveryNoteService::class);
            $service->ship($note);
            $service->complete($note, $note->items->map(fn ($item): array => ['item_id' => $item->id, 'quantity_delivered' => '0', 'quantity_returned' => (string) $item->quantity])->all());
        });

        $this->assertSame(DeliveryNoteStatus::Returned, $this->store->run(fn (): DeliveryNoteStatus => DeliveryNote::query()->findOrFail($note->id)->status));

        $this->actingAs($this->cashier)->postJson($this->codUrl('void', $heldSale), ['reason' => 'Batal'])->assertOk();
    }

    public function test_whoever_may_handle_either_receipt_of_a_combined_note_may_print_it(): void
    {
        [$heldSale, $order] = $this->heldCodReceiptAndReadyOrder();
        $this->collectByCod($order, true)->assertSessionHas('last_sale');
        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->sole());
        $ownerOfHeldSale = $this->storeUser();
        $stranger = $this->storeUser();
        $this->store->run(fn () => Sale::query()->whereKey($heldSale->id)->update(['cashier_id' => $ownerOfHeldSale->id]));

        $print = fn (User $user): TestResponse => $this->actingAs($user)->postJson(route('tenant.cashier.delivery-notes.print', ['tenant' => $this->store->code, 'delivery_note' => $note->id]));

        $print($ownerOfHeldSale)->assertOk();
        $print($this->cashier)->assertOk();
        $print($stranger)->assertForbidden();
    }

    public function test_a_combined_note_cannot_take_more_of_a_line_than_is_left_across_both_receipts(): void
    {
        [$sale, $order] = $this->receiptAndReadyOrder();
        $this->collectByCod($order, false)->assertSessionHas('last_sale');
        $collectingSale = $this->store->run(fn (): Sale => Sale::query()->findOrFail($this->reloaded($order)->sale_id));
        $brushLine = $this->store->run(fn (): SaleItem => SaleItem::query()->where('sale_id', $sale->id)->sole());
        $admin = $this->storeUser(UserRole::StoreAdmin);
        $makeNote = fn (int $quantity): TestResponse => $this->actingAs($admin)->post(route('tenant.delivery-notes.store', ['tenant' => $this->store->code]), [
            'client_uuid' => (string) Str::uuid(),
            'sale_id' => $collectingSale->id,
            'recipient_name' => 'Pak Budi',
            'items' => [['sale_item_id' => $brushLine->id, 'quantity' => $quantity]],
        ]);

        $makeNote(30)->assertSessionHasNoErrors()->assertSessionHas('success');
        $makeNote(30)->assertSessionHas('error');

        $this->assertSame(2, $this->store->run(fn (): int => DeliveryNote::query()->count()));
        $this->assertSame('20.000', $this->store->run(fn (): string => collect(app(DeliveryNoteService::class)->deliverableLines($sale))->firstWhere('name', 'Kuas')['remaining']));
    }

    public function test_the_note_list_finds_a_combined_note_by_the_number_of_either_receipt(): void
    {
        [$sale, $order] = $this->receiptAndReadyOrder();
        $this->collectByCod($order, true)->assertSessionHas('last_sale');
        $collectingSale = $this->store->run(fn (): Sale => Sale::query()->findOrFail($this->reloaded($order)->sale_id));
        $admin = $this->storeUser(UserRole::StoreAdmin);

        foreach ([$sale->number, $collectingSale->number] as $number) {
            $this->actingAs($admin)->get(route('tenant.delivery-notes.index', ['tenant' => $this->store->code, 'search' => $number]))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('notes.data', 1)
                    ->where('notes.data.0.sale_number', $collectingSale->number)
                    ->where('notes.data.0.other_sale_numbers', [$sale->number]));
        }
    }

    public function test_the_note_page_sends_each_return_to_the_receipt_the_goods_were_sold_on(): void
    {
        [$sale, $order] = $this->receiptAndReadyOrder();
        $this->collectByCod($order, true)->assertSessionHas('last_sale');
        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->with('items')->sole());
        $admin = $this->storeUser(UserRole::StoreAdmin);

        $this->store->run(function () use ($note): void {
            $service = app(DeliveryNoteService::class);
            $service->ship($note);
            $service->complete($note, $note->items->map(fn ($item): array => [
                'item_id' => $item->id,
                'quantity_delivered' => $item->product_name === 'Kuas' ? '40' : (string) $item->quantity,
                'quantity_returned' => $item->product_name === 'Kuas' ? '10' : '0',
            ])->all());
        });

        $this->actingAs($admin)->get(route('tenant.delivery-notes.show', ['tenant' => $this->store->code, 'delivery_note' => $note->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('note.return_sales', 1)
                ->where('note.return_sales.0.number', $sale->number)
                ->where('note.return_sales.0.new_return_url', route('tenant.returns.create', ['tenant' => $this->store->code, 'sale' => $sale->id])));
    }

    public function test_the_deliverable_lines_of_many_receipts_cost_the_same_queries_as_those_of_one(): void
    {
        [$sale, $order] = $this->receiptAndReadyOrder();
        $this->collectByCod($order, false)->assertSessionHas('last_sale');
        $collectingSale = $this->store->run(fn (): Sale => Sale::query()->findOrFail($this->reloaded($order)->sale_id));
        $this->store->run(fn () => Sale::factory()->count(4)->create()->each(fn (Sale $extraSale) => SaleItem::factory()->create(['sale_id' => $extraSale->id])));

        $queriesFor = fn (array $saleIds): int => $this->store->run(function () use ($saleIds): int {
            $sales = Sale::query()->with('items')->findMany($saleIds);

            DB::flushQueryLog();
            DB::enableQueryLog();
            app(DeliveryNoteService::class)->deliverableLinesOf($sales);
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        });

        $allSaleIds = $this->store->run(fn (): array => Sale::query()->pluck('id')->all());

        $this->assertSame($queriesFor([$collectingSale->id]), $queriesFor($allSaleIds));
        $this->assertGreaterThan(2, count($allSaleIds));
    }

    public function test_the_receipt_that_collects_an_order_cannot_be_voided_even_when_no_deposit_was_paid(): void
    {
        $order = $this->placeOrder([[$this->paint, 20]]);
        $this->putInStock($this->paint, '20');
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))->assertOk();
        $this->collectByCod($order, false)->assertSessionHas('last_sale');
        $collectingSale = $this->store->run(fn (): Sale => Sale::query()->with('payments')->findOrFail($this->reloaded($order)->sale_id));

        $this->assertNotContains(PaymentMethod::OrderDeposit, $collectingSale->payments->pluck('method')->all());

        $this->actingAs($this->cashier)->postJson($this->codUrl('void', $collectingSale), ['reason' => 'Batal'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 4223)
            ->assertJsonPath('message', 'Nota pengambilan pesanan tidak bisa di-void. Pakai Retur.');

        $this->assertSame(SaleStatus::Completed, $this->store->run(fn (): SaleStatus => Sale::query()->findOrFail($collectingSale->id)->status));
        $this->assertSame(OrderStatus::Completed, $this->reloaded($order)->status);
        $this->assertSame('0.000', $this->stockInPieces($this->paint));
    }

    public function test_a_deposit_cannot_be_given_back_by_someone_without_a_running_shift(): void
    {
        $order = $this->placeOrder([[$this->paint, 10]], ['method' => 'cash', 'amount' => 40000]);
        $admin = $this->storeUser(UserRole::StoreAdmin);

        $this->actingAs($admin)->postJson($this->orderUrl('cancel', $order), ['reason' => 'Batal'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 4224);

        $this->assertSame(OrderStatus::Ordered, $this->reloaded($order)->status);
        $this->assertSame(0, $this->store->run(fn (): int => CustomerOrderPayment::query()->where('kind', OrderPaymentKind::Refund)->count()));
        $this->assertSame('40000.00', $this->expectedCash());
    }

    public function test_a_deposit_cannot_be_given_back_on_a_closed_day(): void
    {
        $order = $this->placeOrder([[$this->paint, 10]], ['method' => 'cash', 'amount' => 40000]);
        $this->store->run(fn () => app(ShiftService::class)->close($this->shift, '40000', null));
        $admin = $this->storeUser(UserRole::StoreAdmin);
        $this->actingAs($admin)->post(route('tenant.daily-closings.store', ['tenant' => $this->store->code, 'date' => today()->toDateString()]))->assertSessionHasNoErrors();

        $this->actingAs($this->cashier)->postJson($this->orderUrl('cancel', $order), ['reason' => 'Batal'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Hari '.today()->format('d/m/Y').' sudah ditutup, jadi uang muka belum bisa dikembalikan hari ini. Minta Super Admin membuka kembali hari itu.');

        $this->assertSame(OrderStatus::Ordered, $this->reloaded($order)->status);
    }

    public function test_cancelling_a_ready_order_sets_its_goods_free_for_sale_again(): void
    {
        $order = $this->placeOrder([[$this->paint, 4]]);
        $this->putInStock($this->paint, '5');
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))->assertOk();
        $sellTwo = fn () => $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->paint, 2]]),
            'payments' => [['method' => 'cash', 'amount' => 20000]],
        ]);

        $sellTwo()->assertSessionHas('error');

        $this->actingAs($this->cashier)->postJson($this->orderUrl('cancel', $order), ['reason' => 'Pelanggan batal'])->assertOk();

        $sellTwo()->assertSessionHas('last_sale');
        $this->assertSame('3.000', $this->stockInPieces($this->paint));
    }

    public function test_the_refund_is_booked_in_the_shift_of_the_one_who_cancels(): void
    {
        $order = $this->placeOrder([[$this->paint, 10]], ['method' => 'cash', 'amount' => 40000]);
        $this->store->run(fn () => app(ShiftService::class)->close($this->shift, '40000', null));
        $admin = $this->storeUser(UserRole::StoreAdmin);
        $laterShift = $this->openShiftFor($admin);

        $this->actingAs($admin)->postJson($this->orderUrl('cancel', $order), ['reason' => 'Batal'])->assertOk();

        $refund = $this->store->run(fn (): CustomerOrderPayment => CustomerOrderPayment::query()->where('kind', OrderPaymentKind::Refund)->sole());
        $summaryOf = fn (CashShift $shift): array => $this->store->run(fn (): array => app(ShiftService::class)->summarize($shift));

        $this->assertSame($laterShift->id, $refund->cash_shift_id);
        $this->assertSame('-40000.00', $summaryOf($laterShift)['order_deposit_cash']);
        $this->assertSame('40000.00', $summaryOf($this->shift)['order_deposit_cash']);
    }

    public function test_the_admin_page_of_a_counted_shift_shows_the_cash_deposits(): void
    {
        $this->placeOrder([[$this->paint, 10]], ['method' => 'cash', 'amount' => 40000]);
        $this->store->run(fn () => app(ShiftService::class)->close($this->shift, '40000', null));
        $admin = $this->storeUser(UserRole::StoreAdmin);

        $this->actingAs($admin)->get(route('tenant.shifts.show', ['tenant' => $this->store->code, 'shift' => $this->shift->id]))
            ->assertInertia(fn (Assert $page) => $page->where('shift.order_deposit_cash', '40000.00')->where('shift.expected_cash', '40000.00'));
    }

    public function test_a_tender_of_several_cash_payments_takes_the_change_out_of_the_last_one(): void
    {
        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->brush, 6]]),
            'payments' => [['method' => 'cash', 'amount' => 50000], ['method' => 'qris', 'amount' => 20000], ['method' => 'cash', 'amount' => 30000]],
            'order' => $this->orderPayload([[$this->paint, 2]], 20000),
        ])->assertSessionHas('last_sale')->assertSessionHas('last_order');

        $sale = $this->store->run(fn (): Sale => Sale::query()->firstOrFail());
        $order = $this->reloaded($this->store->run(fn (): CustomerOrder => CustomerOrder::query()->firstOrFail()));

        $this->assertSame('20000.00', (string) $sale->change_amount);
        $this->assertSame(['qris:10000.00', 'cash:10000.00'], $order->payments->map(fn ($payment): string => "{$payment->method->value}:{$payment->amount}")->all());
        $this->assertSame('60000.00', $this->expectedCash());
    }

    public function test_submitting_a_receipt_with_an_order_and_deposit_twice_returns_the_first_ones_even_after_a_product_is_switched_off(): void
    {
        $payload = [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->brush, 5]]),
            'payments' => [['method' => 'cash', 'amount' => 70000]],
            'order' => $this->orderPayload([[$this->paint, 2]], 20000),
        ];

        $this->actingAs($this->cashier)->post($this->salesUrl(), $payload)->assertSessionHas('last_sale')->assertSessionHas('last_order');
        $this->store->run(fn () => $this->brush->forceFill(['is_active' => false])->save());

        $this->actingAs($this->cashier)->post($this->salesUrl(), $payload)->assertSessionHas('last_sale')->assertSessionHas('last_order')->assertSessionMissing('error');

        $this->assertSame(1, $this->store->run(fn (): int => Sale::query()->count()));
        $this->assertSame(1, $this->store->run(fn (): int => CustomerOrder::query()->count()));
        $this->assertSame(1, $this->store->run(fn (): int => CustomerOrderPayment::query()->count()));
        $this->assertSame('70000.00', $this->expectedCash());
    }

    public function test_a_second_order_for_the_same_goods_cannot_be_marked_as_arrived_while_the_first_holds_them(): void
    {
        $first = $this->placeOrder([[$this->paint, 4]]);
        $second = $this->placeOrder([[$this->paint, 4]]);
        $this->putInStock($this->paint, '5');

        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $first))->assertOk();
        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $second))
            ->assertUnprocessable()
            ->assertJsonPath('code', 4224)
            ->assertJsonPath('message', 'Stok Cat belum cukup untuk pesanan ini, tersedia 1 PCS. Masukkan barang yang datang lewat stok opname dulu.');

        $this->assertSame(OrderStatus::Ordered, $this->reloaded($second)->status);
    }

    public function test_the_goods_set_aside_are_read_with_a_locking_read_after_the_stock_rows_are_locked(): void
    {
        $order = $this->placeOrder([[$this->paint, 4]]);
        $this->putInStock($this->paint, '5');
        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $this->actingAs($this->cashier)->postJson($this->orderUrl('ready', $order))->assertOk();
        $this->actingAs($this->cashier)->post($this->salesUrl(), [
            'client_uuid' => (string) Str::uuid(),
            'items' => $this->lines([[$this->paint, 1]]),
            'payments' => [['method' => 'cash', 'amount' => 10000]],
        ])->assertSessionHas('last_sale');

        $isStockLock = fn (string $sql): bool => str_contains($sql, 'from `stocks`') && str_contains($sql, 'for update');
        $isLockedReservationRead = fn (string $sql): bool => str_contains($sql, 'join `customer_orders`') && str_contains($sql, 'lock in share mode');

        $reservationReads = array_keys(array_filter($statements, $isLockedReservationRead));
        $stockLocks = array_keys(array_filter($statements, $isStockLock));

        $this->assertCount(2, $reservationReads, 'Marking the order as arrived and the sale each read what is set aside.');
        $this->assertLessThan($reservationReads[0], $stockLocks[0]);
        $this->assertLessThan($reservationReads[1], $stockLocks[1]);
    }

    public function test_the_list_finds_an_order_by_number_name_or_phone_on_the_server(): void
    {
        $first = $this->placeOrder([[$this->paint, 1]]);
        $this->placeOrder([[$this->paint, 2]]);
        $url = fn (string $search): string => route('tenant.cashier.orders.index', ['tenant' => $this->store->code, 'q' => $search]);

        $this->actingAs($this->cashier)->getJson($url($first->number))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $first->number)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.counts.open', 2);

        $this->actingAs($this->cashier)->getJson($url('budi'))->assertJsonCount(2, 'data');
        $this->actingAs($this->cashier)->getJson($url('0812345'))->assertJsonCount(2, 'data');
        $this->actingAs($this->cashier)->getJson($url('tidak ada'))->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }

    public function test_a_waiting_order_the_stock_covers_is_listed_before_an_older_one_it_does_not(): void
    {
        $notCovered = $this->placeOrder([[$this->paint, 10]]);
        $covered = $this->placeOrder([[$this->brush, 1]]);

        $this->actingAs($this->cashier)->getJson(route('tenant.cashier.orders.index', ['tenant' => $this->store->code]))
            ->assertJsonPath('data.0.number', $covered->number)
            ->assertJsonPath('data.1.number', $notCovered->number)
            ->assertJsonPath('meta.counts', ['open' => 2, 'ready' => 0, 'arrived' => 1]);
    }

    public function test_the_payment_methods_of_the_till_do_not_offer_the_order_deposit(): void
    {
        $this->assertNotContains(PaymentMethod::OrderDeposit->value, array_column(PaymentMethod::saleOptions(), 'value'));
        $this->assertNotContains(PaymentMethod::OrderDeposit->value, array_column(PaymentMethod::cashierOptions(), 'value'));
    }
}
