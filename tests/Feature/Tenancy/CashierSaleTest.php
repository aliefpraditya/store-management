<?php

namespace Tests\Feature\Tenancy;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\ProductUnit;
use App\Models\Register;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class CashierSaleTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_cashier_sale';

    private Store $store;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_cashier_sale', 'receipt_prefix' => 'TCS']);
        $this->cashier = $this->storeUser();
        $this->openShiftFor($this->cashier);
    }

    private function url(string $route, array $parameters = []): string
    {
        return route("tenant.cashier.{$route}", ['tenant' => $this->store->code, ...$parameters]);
    }

    /**
     * @param  list<array{0: ProductUnit, 1: string|int|float}>  $lines
     * @param  list<array{0: string, 1: string|int|float}>  $payments
     * @param  array<string, mixed>  $extra
     */
    private function sell(array $lines, array $payments, array $extra = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->cashier)->post($this->url('sales.store'), [
            'client_uuid' => (string) Str::uuid(),
            'items' => array_map(fn (array $line): array => ['product_unit_id' => $line[0]->id, 'quantity' => $line[1]], $lines),
            'payments' => array_map(fn (array $payment): array => ['method' => $payment[0], 'amount' => $payment[1]], $payments),
            ...$extra,
        ]);
    }

    private function salesCount(): int
    {
        return $this->store->run(fn () => Sale::query()->count());
    }

    public function test_a_sale_stores_the_receipt_its_lines_its_payments_and_takes_the_stock(): void
    {
        $brush = $this->sellableProduct('Kuas', '100');
        $piece = $this->unitOf($brush, 'PCS');
        $dozen = $this->unitOf($brush, 'DUS');

        $this->sell([[$piece, 2], [$dozen, 1]], [['cash', 200000]], ['customer_name' => ' Pak Budi '])
            ->assertRedirect($this->url('index'))
            ->assertSessionHas('last_sale', fn (array $receipt): bool => $receipt['total'] === '130000.00' && $receipt['change_amount'] === '70000.00');

        $this->store->run(function () use ($brush, $piece, $dozen): void {
            $sale = Sale::query()->with(['items', 'payments'])->sole();

            $this->assertSame(['INV/TCS/K1/'.now()->format('ym').'/0001', '130000.00', '130000.00', '200000.00', '70000.00', SaleStatus::Completed, 'Pak Budi'], [
                $sale->number, $sale->subtotal, $sale->total, $sale->paid_total, $sale->change_amount, $sale->status, $sale->customer_name,
            ]);
            $this->assertSame([$this->cashier->id, $this->cashier->name, $this->register()->id, $this->mainWarehouse()->id], [$sale->cashier_id, $sale->cashier_name, $sale->register_id, $sale->warehouse_id]);

            $lines = $sale->items->keyBy('product_unit_id');
            $this->assertSame(['Kuas', 'PCS', '2.000', '1.000', '2.000', '10000.00', '12000.00', '20000.00'], [
                $lines[$piece->id]->product_name, $lines[$piece->id]->unit_name, $lines[$piece->id]->quantity, $lines[$piece->id]->conversion_snapshot,
                $lines[$piece->id]->base_quantity, $lines[$piece->id]->unit_price, $lines[$piece->id]->cost_total, $lines[$piece->id]->subtotal,
            ]);
            $this->assertSame(['DUS', '12.000', '12.000', '110000.00', '70000.00', '110000.00'], [
                $lines[$dozen->id]->unit_name, $lines[$dozen->id]->conversion_snapshot, $lines[$dozen->id]->base_quantity,
                $lines[$dozen->id]->unit_price, $lines[$dozen->id]->cost_total, $lines[$dozen->id]->subtotal,
            ]);

            $this->assertSame([PaymentMethod::Cash], $sale->payments->pluck('method')->all());
            $this->assertSame('86.000', (string) Stock::query()->where('product_id', $brush->id)->value('quantity'));

            $movement = StockMovement::query()->where('type', StockMovementType::Sale)->orderBy('id')->get();
            $this->assertSame(['-2.000', '-12.000'], $movement->pluck('quantity')->all());
            $this->assertSame(['98.000', '86.000'], $movement->pluck('balance_after')->all());
            $this->assertSame(['sale', $sale->id, $this->cashier->id], [$movement[0]->reference_type, $movement[0]->reference_id, $movement[0]->user_id]);
        });
    }

    public function test_prices_come_from_the_database_not_from_the_screen(): void
    {
        $brush = $this->sellableProduct();

        $this->actingAs($this->cashier)->post($this->url('sales.store'), [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($brush, 'PCS')->id, 'quantity' => 1, 'unit_price' => 1, 'subtotal' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10000]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('10000.00', $this->store->run(fn () => Sale::query()->sole()->total));
    }

    public function test_receipt_numbers_run_on_per_register(): void
    {
        $unit = $this->unitOf($this->sellableProduct(), 'PCS');

        $this->sell([[$unit, 1]], [['cash', 10000]]);
        $this->sell([[$unit, 1]], [['cash', 10000]]);

        $this->assertSame(
            ['INV/TCS/K1/'.now()->format('ym').'/0001', 'INV/TCS/K1/'.now()->format('ym').'/0002'],
            $this->store->run(fn () => Sale::query()->orderBy('id')->pluck('number')->all()),
        );
    }

    public function test_a_sale_can_be_paid_with_several_methods(): void
    {
        $unit = $this->unitOf($this->sellableProduct(), 'PCS');

        $this->sell([[$unit, 13]], [['cash', 50000], ['qris', 80000, 'QR-1']]);

        $this->store->run(function (): void {
            $sale = Sale::query()->with('payments')->sole();
            $this->assertSame(['130000.00', '130000.00', '0.00'], [$sale->total, $sale->paid_total, $sale->change_amount]);
            $this->assertSame(['cash', 'qris'], $sale->payments->map(fn ($payment) => $payment->method->value)->all());
        });
    }

    public function test_a_decimal_quantity_is_sold_and_taken_from_the_stock(): void
    {
        $brush = $this->sellableProduct('Paku', '10');

        $this->sell([[$this->unitOf($brush, 'PCS'), '1.5']], [['cash', 15000]])->assertSessionHasNoErrors();

        $this->assertSame('8.500', $this->stockInPieces($brush));
        $this->assertSame('15000.00', $this->store->run(fn () => Sale::query()->sole()->total));
    }

    public function test_the_payment_must_cover_the_total(): void
    {
        $unit = $this->unitOf($this->sellableProduct(), 'PCS');

        $this->sell([[$unit, 2]], [['cash', 19999]])->assertSessionHas('error', 'Pembayaran kurang dari total belanja.');
        $this->assertSame(0, $this->salesCount());
    }

    public function test_change_can_only_come_from_cash(): void
    {
        $brush = $this->sellableProduct();
        $unit = $this->unitOf($brush, 'PCS');

        $this->sell([[$unit, 1]], [['qris', 15000]])->assertSessionHas('error', 'Kembalian hanya bisa diberikan dari pembayaran tunai.');
        $this->sell([[$unit, 1]], [['cash', 1000], ['qris', 12000]])->assertSessionHas('error');
        $this->assertSame(0, $this->salesCount());
        $this->assertSame('100.000', $this->stockInPieces($brush));

        $this->sell([[$unit, 1]], [['cash', 5000], ['qris', 5000]])->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame(1, $this->salesCount());
    }

    public function test_a_sale_is_refused_when_the_warehouse_does_not_have_enough(): void
    {
        $brush = $this->sellableProduct('Kuas', '5');
        $piece = $this->unitOf($brush, 'PCS');

        $this->sell([[$piece, 6]], [['cash', 60000]])->assertSessionHas('error', 'Stok Kuas tidak cukup, tersisa 5 PCS.');
        $this->assertSame(0, $this->salesCount());
        $this->assertSame('5.000', $this->stockInPieces($brush));

        $this->sell([[$piece, 5]], [['cash', 50000]])->assertSessionMissing('error');
        $this->assertSame('0.000', $this->stockInPieces($brush));
    }

    public function test_lines_of_one_product_in_different_units_count_together_against_the_stock(): void
    {
        $brush = $this->sellableProduct('Kuas', '20');

        $this->sell([[$this->unitOf($brush, 'DUS'), 1], [$this->unitOf($brush, 'PCS'), 9]], [['cash', 300000]])
            ->assertSessionHas('error', 'Stok Kuas tidak cukup, tersisa 20 PCS.');

        $this->assertSame(0, $this->salesCount());
        $this->assertSame('20.000', $this->stockInPieces($brush));
    }

    public function test_a_failed_sale_changes_nothing_even_when_a_later_line_is_refused(): void
    {
        $enough = $this->sellableProduct('Kuas', '50');
        $short = $this->sellableProduct('Cat', '1');

        $this->sell([[$this->unitOf($enough, 'PCS'), 5], [$this->unitOf($short, 'PCS'), 2]], [['cash', 100000]])->assertSessionHas('error');

        $this->assertSame(0, $this->salesCount());
        $this->assertSame(0, $this->store->run(fn () => StockMovement::query()->count()));
        $this->assertSame('50.000', $this->stockInPieces($enough));
    }

    public function test_an_inactive_item_or_a_unit_not_for_sale_is_refused(): void
    {
        $inactive = $this->sellableProduct('Lama', '10', ['is_active' => false]);
        $this->sell([[$this->unitOf($inactive, 'PCS'), 1]], [['cash', 10000]])->assertSessionHas('error', 'Lama tidak bisa dijual dengan satuan itu.');

        $brush = $this->sellableProduct('Kuas', '10');
        $dozen = $this->unitOf($brush, 'DUS');
        $this->store->run(fn () => $dozen->update(['is_sellable' => false]));
        $this->sell([[$dozen, 1]], [['cash', 110000]])->assertSessionHas('error', 'Kuas tidak bisa dijual dengan satuan itu.');

        $this->assertSame(0, $this->salesCount());
    }

    public function test_selling_needs_an_open_shift_of_your_own(): void
    {
        $unit = $this->unitOf($this->sellableProduct(), 'PCS');

        $this->sell([[$unit, 1]], [['cash', 10000]], as: $this->storeUser())->assertSessionHas('error', 'Buka shift dulu sebelum berjualan.');

        $this->assertSame(0, $this->salesCount());
    }

    public function test_sending_the_same_receipt_twice_sells_once(): void
    {
        $brush = $this->sellableProduct();
        $payload = [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($brush, 'PCS')->id, 'quantity' => 3]],
            'payments' => [['method' => 'cash', 'amount' => 30000]],
        ];

        $this->actingAs($this->cashier)->post($this->url('sales.store'), $payload)->assertSessionHas('last_sale');
        $this->actingAs($this->cashier)->post($this->url('sales.store'), $payload)->assertSessionHas('last_sale');

        $this->assertSame(1, $this->salesCount());
        $this->assertSame('97.000', $this->stockInPieces($brush));
        $this->assertSame(1, $this->store->run(fn () => StockMovement::query()->where('type', StockMovementType::Sale)->count()));
    }

    public function test_the_sale_input_is_validated(): void
    {
        $unit = $this->unitOf($this->sellableProduct(), 'PCS');
        $valid = ['client_uuid' => (string) Str::uuid(), 'items' => [['product_unit_id' => $unit->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 10000]]];
        $post = fn (array $overrides) => $this->actingAs($this->cashier)->post($this->url('sales.store'), [...$valid, ...$overrides]);

        $post(['client_uuid' => 'not-a-uuid'])->assertSessionHasErrors('client_uuid');
        $post(['items' => []])->assertSessionHasErrors(['items' => 'Keranjang masih kosong.']);
        $post(['items' => [['product_unit_id' => 9999, 'quantity' => 1]]])->assertSessionHasErrors('items.0.product_unit_id');
        $post(['items' => [['product_unit_id' => $unit->id, 'quantity' => 0]]])->assertSessionHasErrors('items.0.quantity');
        $post(['items' => [['product_unit_id' => $unit->id, 'quantity' => 1.2345]]])->assertSessionHasErrors('items.0.quantity');
        $post(['payments' => []])->assertSessionHasErrors('payments');
        $post(['payments' => [['method' => 'return_credit', 'amount' => 10000]]])->assertSessionHasErrors('payments.0.method');
        $post(['payments' => [['method' => 'cash', 'amount' => 0]]])->assertSessionHasErrors('payments.0.amount');
        $post(['payments' => [['method' => 'cash', 'amount' => 10.555]]])->assertSessionHasErrors('payments.0.amount');

        $this->assertSame(0, $this->salesCount());
    }

    public function test_a_store_admin_can_sell_from_their_own_shift(): void
    {
        $admin = $this->storeUser(UserRole::StoreAdmin);
        $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));
        $this->openShiftFor($admin, $this->store->run(fn () => Register::query()->where('code', 'K2')->firstOrFail()));
        $unit = $this->unitOf($this->sellableProduct(), 'PCS');

        $this->sell([[$unit, 1]], [['cash', 10000]], as: $admin)->assertSessionHasNoErrors();

        $this->assertSame('INV/TCS/K2/'.now()->format('ym').'/0001', $this->store->run(fn () => Sale::query()->sole()->number));
    }

    public function test_the_shift_summary_counts_sales_by_method_and_expected_cash(): void
    {
        $unit = $this->unitOf($this->sellableProduct(), 'PCS');
        $this->sell([[$unit, 13]], [['cash', 200000]]);
        $this->sell([[$unit, 5]], [['qris', 50000]]);

        $this->actingAs($this->cashier)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.sale_count', 2)
            ->where('summary.total_sales', '180000.00')
            ->where('summary.payments.cash', '130000.00')
            ->where('summary.payments.qris', '50000.00')
            ->where('summary.payments.transfer', '0.00')
            ->where('summary.expected_cash', '130000.00'));
    }

    public function test_closing_a_shift_after_sales_compares_the_cash_with_what_the_receipts_say(): void
    {
        $unit = $this->unitOf($this->sellableProduct(), 'PCS');
        $this->sell([[$unit, 13]], [['cash', 200000]]);
        $shift = $this->store->run(fn () => CashShift::query()->sole());

        $this->actingAs($this->cashier)->post($this->url('shifts.close', ['shift' => $shift->id]), ['counted_cash' => '130000'])
            ->assertSessionHas('closed_shift', ['expected_cash' => '130000.00', 'counted_cash' => '130000.00', 'cash_difference' => '0.00', 'deposit_amount' => '130000.00']);
    }

    public function test_a_closed_shift_can_no_longer_sell(): void
    {
        $unit = $this->unitOf($this->sellableProduct(), 'PCS');
        $shift = $this->store->run(fn () => CashShift::query()->sole());
        $this->actingAs($this->cashier)->post($this->url('shifts.close', ['shift' => $shift->id]), ['counted_cash' => '0', 'closing_note' => 'kosong']);

        $this->sell([[$unit, 1]], [['cash', 10000]])->assertSessionHas('error', 'Buka shift dulu sebelum berjualan.');
    }

    public function test_the_item_lookup_finds_items_with_their_sellable_units_and_stock(): void
    {
        $brush = $this->sellableProduct('Kuas Cat', '30');
        $this->sellableProduct('Paku', '5', ['is_active' => false]);
        $this->store->run(fn () => $this->unitOf($brush, 'DUS')->update(['barcode' => '8990000000012']));

        $this->actingAs($this->cashier)->getJson($this->url('products', ['q' => 'kuas']))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kuas Cat')
            ->assertJsonPath('data.0.stock', '30.000')
            ->assertJsonPath('data.0.base_unit', 'PCS')
            ->assertJsonPath('data.0.matched_unit_id', null)
            ->assertJsonPath('data.0.units.0.code', 'PCS')
            ->assertJsonPath('data.0.units.0.price', '10000.00')
            ->assertJsonPath('data.0.units.1.conversion_factor', '12.000');

        $this->actingAs($this->cashier)->getJson($this->url('products', ['q' => 'paku']))->assertJsonCount(0, 'data');
        $this->actingAs($this->cashier)->getJson($this->url('products', ['q' => '']))->assertJsonCount(0, 'data');
    }

    public function test_scanning_a_barcode_finds_that_item_and_the_scanned_unit(): void
    {
        $brush = $this->sellableProduct('Kuas Cat', '30');
        $this->sellableProduct('Kuas Lain', '30');
        $dozen = $this->unitOf($brush, 'DUS');
        $this->store->run(fn () => $dozen->update(['barcode' => '8990000000012']));

        $this->actingAs($this->cashier)->getJson($this->url('products', ['q' => '8990000000012']))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kuas Cat')
            ->assertJsonPath('data.0.matched_unit_id', $dozen->id);
    }

    public function test_a_unit_not_for_sale_is_left_out_of_the_lookup(): void
    {
        $brush = $this->sellableProduct('Kuas Cat', '30');
        $this->store->run(fn () => $this->unitOf($brush, 'DUS')->update(['is_sellable' => false]));

        $this->actingAs($this->cashier)->getJson($this->url('products', ['q' => 'kuas']))
            ->assertJsonCount(1, 'data.0.units')
            ->assertJsonPath('data.0.units.0.code', 'PCS');
    }

    public function test_the_item_lookup_needs_an_open_shift(): void
    {
        $this->actingAs($this->storeUser())->getJson($this->url('products', ['q' => 'kuas']))->assertStatus(409);
    }

    public function test_the_lookup_reads_the_stock_of_the_register_warehouse_only(): void
    {
        $brush = $this->sellableProduct('Kuas Cat', '30');
        $this->store->run(fn () => Stock::query()->updateOrInsert(
            ['product_id' => $brush->id, 'warehouse_id' => Warehouse::query()->where('code', 'DEAD')->value('id')],
            ['quantity' => 99],
        ));

        $this->actingAs($this->cashier)->getJson($this->url('products', ['q' => 'kuas']))->assertJsonPath('data.0.stock', '30.000');
    }

    /**
     * @return list<string> the names the lookup returns for the term, in order
     */
    private function lookupNames(string $term): array
    {
        return $this->actingAs($this->cashier)->getJson($this->url('products', ['q' => $term]))->json('data.*.name');
    }

    public function test_items_in_stock_come_before_sold_out_ones_in_the_lookup(): void
    {
        $this->sellableProduct('Cat A', '0');
        $this->sellableProduct('Cat B', '5');
        $this->sellableProduct('Cat C', '0');
        $this->sellableProduct('Cat D', '3');

        $this->assertSame(['Cat B', 'Cat D', 'Cat A', 'Cat C'], $this->lookupNames('cat'));
    }

    public function test_sold_out_items_do_not_crowd_in_stock_ones_out_of_the_result_limit(): void
    {
        foreach (range(1, 22) as $number) {
            $this->sellableProduct(sprintf('Cat Habis %02d', $number), '0');
        }

        $this->sellableProduct('Cat Ada', '4');

        $names = $this->lookupNames('cat');

        $this->assertCount(20, $names);
        $this->assertSame('Cat Ada', $names[0]);
    }

    public function test_a_specific_search_still_shows_items_when_all_matches_are_sold_out(): void
    {
        $this->sellableProduct('Cat Avian 1', '0');
        $this->sellableProduct('Cat Avian 2', '0');
        $this->sellableProduct('Cat Lain', '9');

        $this->assertSame(['Cat Avian 1', 'Cat Avian 2'], $this->lookupNames('cat avian'));
        $this->assertSame('Cat Lain', $this->lookupNames('cat')[0]);
    }

    public function test_stock_in_another_warehouse_does_not_count_as_in_stock(): void
    {
        $elsewhere = $this->sellableProduct('Cat A', '0');
        $this->sellableProduct('Cat B', '2');
        $this->store->run(fn () => Stock::query()->updateOrInsert(
            ['product_id' => $elsewhere->id, 'warehouse_id' => Warehouse::query()->where('code', 'DEAD')->value('id')],
            ['quantity' => 50],
        ));

        $this->assertSame(['Cat B', 'Cat A'], $this->lookupNames('cat'));
    }
}
