<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\SalePayment;
use App\Models\Store;
use App\Models\User;
use App\Services\SaleService;
use App\Services\StoreSettingService;
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
            ->where('quick_access.items.0.name', 'Cat')
            ->where('quick_access.items.1.name', 'Kuas')
            ->where('quick_access.items.2.name', 'Roll')
            ->has('quick_access.items.0.units.0.is_default_sale'));
    }

    public function test_sales_older_than_a_month_do_not_count_and_a_new_store_has_no_best_sellers(): void
    {
        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page->where('quick_access', ['is_pinned' => false, 'items' => []]));

        $brush = $this->sellableProduct('Kuas', '100');
        $this->sell($brush, 1, now()->subDays(40)->toDateTimeString());

        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page->where('quick_access', ['is_pinned' => false, 'items' => []]));
    }

    public function test_only_items_in_stock_are_offered_even_when_a_sold_out_one_sold_best(): void
    {
        $soldOut = $this->sellableProduct('Habis', '2');
        $inStock = $this->sellableProduct('Ada', '100');

        $this->sell($soldOut, 1);
        $this->sell($soldOut, 1);
        $this->sell($inStock, 1);

        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page
            ->has('quick_access.items', 1)
            ->where('quick_access.items.0.name', 'Ada')
            ->where('quick_access.items.0.stock', '99.000'));
    }

    public function test_at_most_twenty_best_sellers_are_offered_most_sold_first(): void
    {
        $products = collect(range(1, 22))->map(fn (int $number): Product => $this->sellableProduct(sprintf('Barang %02d', $number), '100'));

        foreach ($products as $index => $product) {
            foreach (range(1, 23 - $index) as $ignored) {
                $this->sell($product, 1);
            }
        }

        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page
            ->has('quick_access.items', 20)
            ->where('quick_access.items.0.name', 'Barang 01')
            ->where('quick_access.items.19.name', 'Barang 20'));
    }

    public function test_an_item_that_cannot_be_sold_any_more_is_left_out(): void
    {
        $brush = $this->sellableProduct('Kuas', '100');
        $this->sell($brush, 1);
        $this->store->run(fn () => $brush->forceFill(['is_active' => false])->save());

        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page->where('quick_access', ['is_pinned' => false, 'items' => []]));
    }

    private function setQuickAccess(User $user, Product $product, bool $isInQuickAccess): TestResponse
    {
        return $this->actingAs($user)->put(route('tenant.products.quick-access', ['tenant' => $this->store->code, 'product' => $product->id]), ['is_in_quick_access' => $isInQuickAccess]);
    }

    private function quickAccessPositionOf(Product $product): ?int
    {
        return $this->store->run(fn (): ?int => Product::query()->findOrFail($product->id)->quick_access_position);
    }

    public function test_quick_access_items_take_the_place_of_the_best_sellers_in_the_order_they_were_added(): void
    {
        $brush = $this->sellableProduct('Kuas', '100');
        $paint = $this->sellableProduct('Cat', '100');
        $roller = $this->sellableProduct('Roll', '100');

        $this->sell($roller, 1);
        $this->setQuickAccess($this->admin, $paint, true)->assertSessionHasNoErrors();
        $this->setQuickAccess($this->admin, $brush, true)->assertSessionHasNoErrors();

        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page
            ->where('quick_access.is_pinned', true)
            ->has('quick_access.items', 2)
            ->where('quick_access.items.0.name', 'Cat')
            ->where('quick_access.items.1.name', 'Kuas'));
    }

    public function test_a_sold_out_quick_access_item_stays_on_the_till_but_an_inactive_one_goes(): void
    {
        $soldOut = $this->sellableProduct('Habis', '0');
        $inactive = $this->sellableProduct('Mati', '100');

        $this->setQuickAccess($this->admin, $soldOut, true);
        $this->setQuickAccess($this->admin, $inactive, true);
        $this->store->run(fn () => $inactive->forceFill(['is_active' => false])->save());

        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page
            ->where('quick_access.is_pinned', true)
            ->has('quick_access.items', 1)
            ->where('quick_access.items.0.name', 'Habis')
            ->where('quick_access.items.0.stock', '0.000'));
    }

    public function test_the_till_goes_back_to_the_best_sellers_when_no_quick_access_item_can_be_sold(): void
    {
        $inactive = $this->sellableProduct('Mati', '100');
        $brush = $this->sellableProduct('Kuas', '100');
        $this->sell($brush, 1);

        $this->setQuickAccess($this->admin, $inactive, true);
        $this->store->run(fn () => $inactive->forceFill(['is_active' => false])->save());

        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page
            ->where('quick_access.is_pinned', false)
            ->where('quick_access.items.0.name', 'Kuas'));
    }

    public function test_an_item_goes_in_after_the_others_comes_out_and_asking_twice_changes_nothing(): void
    {
        $brush = $this->sellableProduct('Kuas', '100');
        $paint = $this->sellableProduct('Cat', '100');

        $this->setQuickAccess($this->admin, $brush, true);
        $this->setQuickAccess($this->admin, $brush, true);
        $this->setQuickAccess($this->admin, $paint, true);

        $this->assertSame(1, $this->quickAccessPositionOf($brush));
        $this->assertSame(2, $this->quickAccessPositionOf($paint));

        $this->setQuickAccess($this->admin, $brush, false);
        $this->setQuickAccess($this->admin, $brush, false);

        $this->assertNull($this->quickAccessPositionOf($brush));
        $this->assertSame(2, $this->quickAccessPositionOf($paint));
    }

    public function test_quick_access_does_not_count_as_an_edit_of_the_item(): void
    {
        $brush = $this->sellableProduct('Kuas', '100');
        $versionBefore = $this->store->run(fn (): int => Product::query()->findOrFail($brush->id)->version);

        $this->setQuickAccess($this->admin, $brush, true);

        $this->assertSame($versionBefore, $this->store->run(fn (): int => Product::query()->findOrFail($brush->id)->version));
    }

    public function test_quick_access_holds_at_most_twenty_items(): void
    {
        $products = collect(range(1, Product::QUICK_ACCESS_LIMIT + 1))->map(fn (int $number): Product => $this->sellableProduct(sprintf('Barang %02d', $number), '100'));

        foreach ($products->take(Product::QUICK_ACCESS_LIMIT) as $product) {
            $this->setQuickAccess($this->admin, $product, true)->assertSessionHasNoErrors();
        }

        $this->setQuickAccess($this->admin, $products->last(), true)->assertSessionHas('error');

        $this->assertNull($this->quickAccessPositionOf($products->last()));

        $this->setQuickAccess($this->admin, $products->first(), false);
        $this->setQuickAccess($this->admin, $products->last(), true)->assertSessionHasNoErrors();

        $this->assertNotNull($this->quickAccessPositionOf($products->last()));
    }

    public function test_only_who_may_manage_items_can_change_quick_access(): void
    {
        $brush = $this->sellableProduct('Kuas', '100');

        $this->setQuickAccess($this->cashier, $brush, true)->assertForbidden();

        $this->assertNull($this->quickAccessPositionOf($brush));
    }

    public function test_the_item_list_tells_which_items_are_in_quick_access(): void
    {
        $brush = $this->sellableProduct('Kuas', '100');
        $this->sellableProduct('Cat', '100');
        $this->setQuickAccess($this->admin, $brush, true);

        $this->actingAs($this->admin)->get(route('tenant.products.index', ['tenant' => $this->store->code, 'sort' => 'name']))->assertInertia(fn (Assert $page) => $page
            ->where('quick_access', ['count' => 1, 'limit' => Product::QUICK_ACCESS_LIMIT])
            ->where('products.data.0.name', 'Cat')
            ->where('products.data.0.is_in_quick_access', false)
            ->where('products.data.1.name', 'Kuas')
            ->where('products.data.1.is_in_quick_access', true));
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
        // Read through the service, not the till: the router keeps the till's controller (and the settings it read) alive between requests of one test, which a real request never does.
        $this->assertFalse($this->store->run(fn (): bool => app(StoreSettingService::class)->isReceiptPrintedAutomatically()));

        $this->actingAs($this->admin)->put(route('tenant.settings.update', ['tenant' => $this->store->code]), [
            'paper_width' => 80,
            'is_qz_tray_enabled' => false,
            'lock_sales_during_opname' => false,
            'auto_print_receipt' => true,
        ])->assertSessionHasNoErrors();

        $this->cashierScreen()->assertInertia(fn (Assert $page) => $page->where('is_receipt_printed_automatically', true));
    }
}
