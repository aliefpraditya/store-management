<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\SalePayment;
use App\Models\Store;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

/**
 * What speeds the till up: the items to add without typing and the receipt that prints by itself.
 */
class CashierQuickAccessTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_cashier_quick';

    private Store $store;

    private User $cashier;

    private User $admin;

    private CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_cashier_quick', 'receipt_prefix' => 'TCQ']);
        $this->cashier = $this->storeUser();
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->shift = $this->openShiftFor($this->cashier);
    }

    private function sell(Product $product, int $pieces, ?string $soldAt = null): void
    {
        $sale = $this->store->run(fn () => app(SaleService::class)->create($this->shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($product, 'PCS')->id, 'quantity' => $pieces]],
            'payments' => [['method' => 'cash', 'amount' => $pieces * 10000]],
        ]));

        if ($soldAt !== null) {
            $this->store->run(fn () => $sale->forceFill(['sold_at' => $soldAt])->save());
        }
    }

    private function cashierScreen(): TestResponse
    {
        return $this->actingAs($this->cashier)->get(route('tenant.cashier.index', ['tenant' => $this->store->code]));
    }

    public function test_the_empty_till_offers_the_best_selling_items_first(): void
    {
        $brush = $this->sellableProduct('Kuas', '100');
        $paint = $this->sellableProduct('Cat', '100');
        $roller = $this->sellableProduct('Roll', '100');

        $this->sell($paint, 1);
        $this->sell($paint, 1);
        $this->sell($paint, 1);
        $this->sell($brush, 1);
        $this->sell($brush, 1);
        $this->sell($roller, 1);

        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page
            ->where('best_sellers.0.name', 'Cat')
            ->where('best_sellers.1.name', 'Kuas')
            ->where('best_sellers.2.name', 'Roll')
            ->has('best_sellers.0.units.0.is_default_sale'));
    }

    public function test_sales_older_than_a_month_do_not_count_and_a_new_store_has_no_best_sellers(): void
    {
        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page->where('best_sellers', []));

        $brush = $this->sellableProduct('Kuas', '100');
        $this->sell($brush, 1, now()->subDays(40)->toDateTimeString());

        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page->where('best_sellers', []));
    }

    public function test_an_item_that_cannot_be_sold_any_more_is_left_out(): void
    {
        $brush = $this->sellableProduct('Kuas', '100');
        $this->sell($brush, 1);
        $this->store->run(fn () => $brush->forceFill(['is_active' => false])->save());

        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page->where('best_sellers', []));
    }

    public function test_the_same_method_can_be_paid_on_two_lines(): void
    {
        $brush = $this->sellableProduct('Kuas', '100');

        $this->actingAs($this->cashier)->post(route('tenant.cashier.sales.store', ['tenant' => $this->store->code]), [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_unit_id' => $this->unitOf($brush, 'PCS')->id, 'quantity' => 10]],
            'payments' => [
                ['method' => 'cash', 'amount' => 30000],
                ['method' => 'cash', 'amount' => 20000],
                ['method' => 'transfer', 'amount' => 30000, 'reference' => 'BCA 1'],
                ['method' => 'transfer', 'amount' => 20000, 'reference' => 'BCA 2'],
            ],
        ])->assertSessionHas('last_sale')->assertSessionHasNoErrors();

        $payments = $this->store->run(fn (): array => SalePayment::query()->orderBy('id')->get()->map(fn ($payment): string => "{$payment->method->value}:{$payment->amount}")->all());

        $this->assertSame(['cash:30000.00', 'cash:20000.00', 'transfer:30000.00', 'transfer:20000.00'], $payments);
    }

    public function test_the_receipt_prints_by_itself_only_when_the_store_chooses_so(): void
    {
        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page->where('is_receipt_printed_automatically', false));

        $this->actingAs($this->admin)->put(route('tenant.settings.update', ['tenant' => $this->store->code]), [
            'paper_width' => 80,
            'lock_sales_during_opname' => false,
            'auto_print_receipt' => true,
        ])->assertSessionHasNoErrors();

        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page->where('is_receipt_printed_automatically', true));
    }
}
