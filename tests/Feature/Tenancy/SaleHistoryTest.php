<?php

namespace Tests\Feature\Tenancy;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class SaleHistoryTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_sale_history';

    private Store $store;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_sale_history']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->cashier = $this->storeUser();
    }

    private function url(string $name, array $parameters = []): string
    {
        return route($name, ['tenant' => $this->store->code, ...$parameters]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeSale(User $cashier, array $attributes = [], PaymentMethod $method = PaymentMethod::Cash): Sale
    {
        return $this->store->run(function () use ($cashier, $attributes, $method): Sale {
            $sale = Sale::factory()->create(['cashier_id' => $cashier->id, 'cashier_name' => $cashier->name, ...$attributes]);
            SaleItem::factory()->create(['sale_id' => $sale->id, 'product_name' => 'Kuas', 'quantity' => 2, 'unit_price' => 50000, 'subtotal' => 100000]);
            SalePayment::factory()->create(['sale_id' => $sale->id, 'method' => $method, 'amount' => $sale->total]);

            return $sale;
        });
    }

    /**
     * @return list<string> receipt numbers in the list, in order
     */
    private function listedNumbers(User $as, array $query = []): array
    {
        $response = $this->actingAs($as)->get($this->url('tenant.sales.index', $query))->assertOk();

        return array_column(Assert::fromTestResponse($response)->toArray()['props']['sales']['data'], 'number');
    }

    public function test_a_guest_is_sent_to_the_login_page_and_an_outsider_is_refused(): void
    {
        $this->get($this->url('tenant.sales.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($this->url('tenant.sales.index'))->assertForbidden();
    }

    public function test_the_list_shows_todays_receipts_newest_first_with_how_they_were_paid(): void
    {
        $this->makeSale($this->cashier, ['number' => 'INV/1', 'sold_at' => now()->subHour(), 'total' => 100000], PaymentMethod::Cash);
        $this->makeSale($this->cashier, ['number' => 'INV/2', 'sold_at' => now(), 'total' => 250000, 'customer_name' => 'Pak Budi'], PaymentMethod::Qris);
        $this->makeSale($this->cashier, ['number' => 'INV/OLD', 'business_date' => today()->subDay(), 'sold_at' => now()->subDay()]);

        $this->actingAs($this->admin)->get($this->url('tenant.sales.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Sales/Index')
            ->has('sales.data', 2)
            ->where('sales.data.0.number', 'INV/2')
            ->where('sales.data.0.total', '250000.00')
            ->where('sales.data.0.customer_name', 'Pak Budi')
            ->where('sales.data.0.items_count', 1)
            ->where('sales.data.0.payment_methods', ['QRIS'])
            ->where('sales.data.0.status_label', 'Selesai')
            ->where('sales.data.1.number', 'INV/1')
            ->where('sales.data.1.payment_methods', ['Tunai'])
            ->where('filters.from', today()->toDateString())
            ->where('filters.to', today()->toDateString()));
    }

    public function test_an_admin_sees_every_cashiers_receipts_and_can_filter_by_cashier(): void
    {
        $other = $this->storeUser();
        $this->makeSale($this->cashier, ['number' => 'INV/MINE']);
        $this->makeSale($other, ['number' => 'INV/THEIRS']);

        $this->assertEqualsCanonicalizing(['INV/MINE', 'INV/THEIRS'], $this->listedNumbers($this->admin));
        $this->assertSame(['INV/THEIRS'], $this->listedNumbers($this->admin, ['cashier' => $other->id]));

        $this->actingAs($this->admin)->get($this->url('tenant.sales.index'))->assertInertia(fn (Assert $page) => $page->has('cashiers', 2));
    }

    public function test_receipts_can_be_found_by_a_date_range_or_across_all_dates(): void
    {
        $this->makeSale($this->cashier, ['number' => 'INV/TODAY']);
        $this->makeSale($this->cashier, ['number' => 'INV/LAST-WEEK', 'business_date' => today()->subDays(7), 'sold_at' => now()->subDays(7)]);
        $this->makeSale($this->cashier, ['number' => 'INV/LAST-MONTH', 'business_date' => today()->subDays(30), 'sold_at' => now()->subDays(30)]);

        $this->assertSame(['INV/TODAY', 'INV/LAST-WEEK'], $this->listedNumbers($this->admin, ['from' => today()->subDays(8)->toDateString(), 'to' => today()->toDateString()]));
        $this->assertSame(['INV/LAST-WEEK'], $this->listedNumbers($this->admin, ['from' => today()->subDays(8)->toDateString(), 'to' => today()->subDays(6)->toDateString()]));
        $this->assertCount(3, $this->listedNumbers($this->admin, ['from' => '', 'to' => '']));
    }

    public function test_receipts_can_be_searched_by_number_or_customer_name(): void
    {
        $this->makeSale($this->cashier, ['number' => 'INV/MU/K1/2610/0125', 'customer_name' => 'Pak Budi']);
        $this->makeSale($this->cashier, ['number' => 'INV/MU/K1/2610/0126', 'customer_name' => 'Bu Sari']);

        $this->assertSame(['INV/MU/K1/2610/0125'], $this->listedNumbers($this->admin, ['search' => '0125']));
        $this->assertSame(['INV/MU/K1/2610/0126'], $this->listedNumbers($this->admin, ['search' => 'sari']));
        $this->assertCount(2, $this->listedNumbers($this->admin, ['search' => 'K1']));
    }

    public function test_voided_receipts_are_listed_but_left_out_of_the_totals(): void
    {
        $this->makeSale($this->cashier, ['number' => 'INV/OK-1', 'total' => 100000]);
        $this->makeSale($this->cashier, ['number' => 'INV/OK-2', 'total' => 50000]);
        $this->makeSale($this->cashier, ['number' => 'INV/VOID', 'total' => 999000, 'status' => 'voided']);

        $this->actingAs($this->admin)->get($this->url('tenant.sales.index'))->assertInertia(fn (Assert $page) => $page
            ->has('sales.data', 3)
            ->where('totals', ['receipt_count' => 2, 'total' => '150000.00']));

        $this->assertSame(['INV/VOID'], $this->listedNumbers($this->admin, ['status' => 'voided']));
        $this->assertCount(2, $this->listedNumbers($this->admin, ['status' => 'completed']));
    }

    public function test_the_totals_follow_the_filters(): void
    {
        $other = $this->storeUser();
        $this->makeSale($this->cashier, ['total' => 100000]);
        $this->makeSale($other, ['total' => 40000]);

        $this->actingAs($this->admin)->get($this->url('tenant.sales.index'))
            ->assertInertia(fn (Assert $page) => $page->where('totals', ['receipt_count' => 2, 'total' => '140000.00']));
        $this->actingAs($this->admin)->get($this->url('tenant.sales.index', ['cashier' => $other->id]))
            ->assertInertia(fn (Assert $page) => $page->where('totals', ['receipt_count' => 1, 'total' => '40000.00']));
    }

    public function test_the_list_is_paginated(): void
    {
        foreach (range(1, 27) as $number) {
            $this->makeSale($this->cashier, ['number' => sprintf('INV/%03d', $number), 'sold_at' => now()->addSeconds($number)]);
        }

        $this->actingAs($this->admin)->get($this->url('tenant.sales.index'))->assertInertia(fn (Assert $page) => $page
            ->has('sales.data', 25)
            ->where('sales.total', 27)
            ->where('sales.data.0.number', 'INV/027'));
    }

    public function test_a_receipt_opens_with_its_lines_and_payments(): void
    {
        $sale = $this->makeSale($this->cashier, ['number' => 'INV/1', 'total' => 100000, 'paid_total' => 120000, 'change_amount' => 20000, 'customer_name' => 'Pak Budi']);

        $this->actingAs($this->admin)->get($this->url('tenant.sales.show', ['sale' => $sale->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Sales/Show')
            ->where('sale.number', 'INV/1')
            ->where('sale.cashier_name', $this->cashier->name)
            ->where('sale.customer_name', 'Pak Budi')
            ->where('sale.total', '100000.00')
            ->where('sale.change_amount', '20000.00')
            ->where('sale.status_label', 'Selesai')
            ->has('sale.register.code')
            ->has('sale.items', 1)
            ->where('sale.items.0.name', 'Kuas')
            ->where('sale.items.0.subtotal', '100000.00')
            ->has('sale.payments', 1)
            ->where('sale.payments.0.method', 'Tunai')
            ->where('urls.index', $this->url('tenant.sales.index')));
    }

    public function test_a_cashier_can_open_neither_the_history_nor_a_receipt_not_even_their_own(): void
    {
        $mine = $this->makeSale($this->cashier);

        $this->actingAs($this->cashier)->get($this->url('tenant.sales.index'))->assertForbidden();
        $this->actingAs($this->cashier)->get($this->url('tenant.sales.show', ['sale' => $mine->id]))->assertForbidden();
    }

    public function test_the_history_stays_open_for_an_admin_while_a_shift_is_open(): void
    {
        $this->openShiftFor($this->admin);

        $this->actingAs($this->admin)->get($this->url('tenant.sales.index'))->assertOk();
    }
}
