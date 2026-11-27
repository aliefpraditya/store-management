<?php

namespace Tests\Feature\Tenancy;

use App\Models\CashShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Store;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

/**
 * A line sold in a decimal quantity (0.37 kg) is rounded up to the store's selling price step, on the receipt, in the
 * reports that read it, and in what a return gives back; a whole quantity never is.
 */
class DecimalQuantityRoundingTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_decimal_rounding';

    private Store $store;

    private User $cashier;

    private CashShift $shift;

    private Product $brush;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_decimal_rounding', 'receipt_prefix' => 'TDR']);
        $this->cashier = $this->storeUser();
        $this->shift = $this->openShiftFor($this->cashier);
        $this->brush = $this->sellableProduct('Kuas', '100');
    }

    private function setSellingPriceStep(int $step): void
    {
        $this->store->run(fn () => StoreSetting::query()->updateOrCreate(['key' => 'selling_price_step'], ['value' => (string) $step]));
    }

    private function setPieceUnitPrice(string $price): void
    {
        $this->store->run(fn () => $this->unitOf($this->brush, 'PCS')->forceFill(['selling_price' => $price])->save());
    }

    /**
     * @param  list<float|int|string>  $quantities  one line of the brush in pieces for each
     */
    private function sell(array $quantities, string $paid): Sale
    {
        $pcs = $this->unitOf($this->brush, 'PCS');

        return $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => array_map(fn (float|int|string $quantity): array => ['product_unit_id' => $pcs->id, 'quantity' => $quantity], $quantities),
            'payments' => [['method' => 'cash', 'amount' => $paid]],
        ])->load('items'));
    }

    /**
     * @return list<string> the subtotal of each receipt line
     */
    private function subtotalsOf(Sale $sale): array
    {
        return $sale->items->pluck('subtotal')->map(fn ($subtotal): string => (string) $subtotal)->all();
    }

    private function askForReturn(Sale $sale, string $quantity): SaleReturn
    {
        return $this->store->run(fn (): SaleReturn => app(SaleReturnService::class)->create($sale, $this->shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'refund_method' => 'cash',
            'items' => [['sale_item_id' => $sale->items[0]->id, 'quantity' => $quantity]],
        ])->load('items'));
    }

    public function test_a_decimal_quantity_line_is_rounded_up_to_the_stores_step(): void
    {
        $this->setSellingPriceStep(1000);

        $sale = $this->sell(['0.37'], '4000');

        $this->assertSame(['4000.00'], $this->subtotalsOf($sale));
        $this->assertSame('4000.00', (string) $sale->total);
    }

    public function test_the_total_is_the_sum_of_the_rounded_lines(): void
    {
        $this->setSellingPriceStep(1000);

        $sale = $this->sell(['0.37', '0.21'], '7000');

        $this->assertSame(['4000.00', '3000.00'], $this->subtotalsOf($sale));
        $this->assertSame('7000.00', (string) $sale->total);
    }

    public function test_an_amount_already_on_the_step_stays_as_it_is(): void
    {
        $this->setSellingPriceStep(1000);

        $this->assertSame(['5000.00'], $this->subtotalsOf($this->sell(['0.5'], '5000')));
    }

    public function test_a_whole_quantity_is_never_rounded(): void
    {
        $this->setSellingPriceStep(1000);
        $this->setPieceUnitPrice('3333');

        $this->assertSame(['9999.00'], $this->subtotalsOf($this->sell([3], '9999')));
    }

    public function test_a_decimal_quantity_of_an_odd_price_is_rounded_after_it_is_worked_out_to_the_sen(): void
    {
        $this->setSellingPriceStep(1000);
        $this->setPieceUnitPrice('3333');

        $this->assertSame(['9000.00'], $this->subtotalsOf($this->sell(['2.5'], '9000')));
    }

    public function test_nothing_is_rounded_while_the_store_has_no_step(): void
    {
        $this->assertSame(['3700.00'], $this->subtotalsOf($this->sell(['0.37'], '3700')));
    }

    public function test_returning_all_of_a_rounded_line_gives_back_what_the_customer_paid(): void
    {
        $this->setSellingPriceStep(1000);
        $sale = $this->sell(['0.37'], '4000');

        $saleReturn = $this->askForReturn($sale, '0.37');

        $this->assertSame(['4000.00'], $saleReturn->items->pluck('refund_subtotal')->map(fn ($amount): string => (string) $amount)->all());
    }

    public function test_returning_a_rounded_line_in_two_parts_gives_back_exactly_what_was_paid_in_all(): void
    {
        $this->setSellingPriceStep(1000);
        $sale = $this->sell(['0.37'], '4000');

        $first = $this->askForReturn($sale, '0.2');
        $second = $this->askForReturn($sale, '0.17');

        $this->assertSame('2162.16', (string) $first->items[0]->refund_subtotal);
        $this->assertSame('1837.84', (string) $second->items[0]->refund_subtotal);
    }

    public function test_a_line_that_was_not_rounded_is_given_back_at_price_times_quantity(): void
    {
        $this->setSellingPriceStep(1000);
        $this->setPieceUnitPrice('3333');
        $sale = $this->sell([3], '9999');

        $saleReturn = $this->askForReturn($sale, '1');

        $this->assertSame('3333.00', (string) $saleReturn->items[0]->refund_subtotal);
    }

    public function test_the_return_window_gets_what_it_needs_to_value_a_return(): void
    {
        $this->setSellingPriceStep(1000);
        $sale = $this->sell(['0.37'], '4000');
        $this->askForReturn($sale, '0.2');

        $line = $this->store->run(fn (): array => app(SaleReturnService::class)->returnableLines($sale->fresh('items'))[0]);

        $this->assertSame('4000.00', $line['subtotal']);
        $this->assertSame('2162.16', $line['refunded']);
        $this->assertSame('0.170', $line['remaining']);
    }

    public function test_the_screen_is_told_the_step_to_work_out_the_same_amount(): void
    {
        $this->setSellingPriceStep(500);

        $this->actingAs($this->cashier)->get(route('tenant.cashier.index', ['tenant' => $this->store->code]))
            ->assertInertia(fn (Assert $page) => $page->where('store.selling_price_step', 500));
    }
}
