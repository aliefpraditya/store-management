<?php

namespace Tests\Feature\Tenancy;

use App\Enums\ApiErrorCode;
use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\Register;
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

class ShiftReceiptTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_shift_receipts';

    private Store $store;

    private User $cashier;

    private CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_shift_receipts', 'receipt_prefix' => 'TSR', 'address' => 'Jl. Uji 1', 'phone' => '021-123']);
        $this->cashier = $this->storeUser();
        $this->shift = $this->openShiftFor($this->cashier);
    }

    private function url(string $name, array $parameters = []): string
    {
        return route($name, ['tenant' => $this->store->code, ...$parameters]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeSale(CashShift $shift, User $cashier, array $attributes = []): Sale
    {
        return $this->store->run(function () use ($shift, $cashier, $attributes): Sale {
            $sale = Sale::factory()->create([
                'cash_shift_id' => $shift->id, 'register_id' => $shift->register_id, 'cashier_id' => $cashier->id, 'cashier_name' => $cashier->name, ...$attributes,
            ]);
            SaleItem::factory()->create(['sale_id' => $sale->id, 'product_name' => 'Kuas', 'quantity' => 2, 'unit_price' => 50000, 'subtotal' => 100000]);
            SalePayment::factory()->create(['sale_id' => $sale->id, 'amount' => $sale->total]);

            return $sale;
        });
    }

    private function printCount(Sale $sale): int
    {
        return $this->store->run(fn () => Sale::query()->findOrFail($sale->id)->print_count);
    }

    public function test_the_dialog_lists_the_receipts_of_the_running_shift_newest_first_with_their_lines(): void
    {
        $this->makeSale($this->shift, $this->cashier, ['number' => 'INV/1', 'sold_at' => now()->subHour(), 'customer_name' => 'Pak Budi']);
        $this->makeSale($this->shift, $this->cashier, ['number' => 'INV/2', 'sold_at' => now()]);

        $response = $this->actingAs($this->cashier)->getJson($this->url('tenant.cashier.receipts'))->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.number', 'INV/2')
            ->assertJsonPath('data.1.number', 'INV/1')
            ->assertJsonPath('data.1.customer_name', 'Pak Budi')
            ->assertJsonPath('data.1.items.0.name', 'Kuas')
            ->assertJsonPath('data.1.payments.0.method', 'Tunai')
            ->assertJsonPath('data.1.print_count', 0);

        $receipt = $response->json('data.1');
        $this->assertNull($receipt['show_url']);
        $this->assertStringEndsWith('/cashier/receipts/'.$receipt['id'].'/print', $receipt['print_url']);
        $this->assertStringEndsWith('/cashier/receipts/'.$receipt['id'].'/sheet', $receipt['sheet_url']);
    }

    public function test_only_an_admin_gets_the_link_to_the_full_receipt(): void
    {
        $admin = $this->storeUser(UserRole::StoreAdmin);
        $secondRegister = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $this->mainWarehouse()->id]));
        $adminShift = $this->openShiftFor($admin, $secondRegister);
        $sale = $this->makeSale($adminShift, $admin);

        $this->actingAs($admin)->getJson($this->url('tenant.cashier.receipts'))
            ->assertJsonPath('data.0.show_url', $this->url('tenant.sales.show', ['sale' => $sale->id]));
    }

    public function test_receipts_of_other_shifts_are_not_listed(): void
    {
        $this->makeSale($this->shift, $this->cashier, ['number' => 'INV/THIS-SHIFT']);

        $otherCashier = $this->storeUser();
        $this->store->run(fn () => $this->shift->forceFill(['status' => 'closed'])->save());
        $otherShift = $this->openShiftFor($otherCashier);
        $this->makeSale($otherShift, $otherCashier, ['number' => 'INV/OTHER']);

        $this->actingAs($otherCashier)->getJson($this->url('tenant.cashier.receipts'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.number', 'INV/OTHER');
        $this->actingAs($this->cashier)->getJson($this->url('tenant.cashier.receipts'))->assertStatus(409);
    }

    public function test_the_dialog_can_search_by_number_or_customer(): void
    {
        $this->makeSale($this->shift, $this->cashier, ['number' => 'INV/MU/K1/2610/0125', 'customer_name' => 'Pak Budi']);
        $this->makeSale($this->shift, $this->cashier, ['number' => 'INV/MU/K1/2610/0126', 'customer_name' => 'Bu Sari']);

        $this->actingAs($this->cashier)->getJson($this->url('tenant.cashier.receipts', ['q' => '0125']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.number', 'INV/MU/K1/2610/0125');
        $this->actingAs($this->cashier)->getJson($this->url('tenant.cashier.receipts', ['q' => 'sari']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.number', 'INV/MU/K1/2610/0126');
    }

    public function test_the_dialog_needs_an_open_shift(): void
    {
        $this->actingAs($this->storeUser())->getJson($this->url('tenant.cashier.receipts'))
            ->assertStatus(409)
            ->assertJsonPath('code', ApiErrorCode::ShiftNotOpen->value);
    }

    public function test_the_first_print_is_the_original_and_later_ones_are_copies(): void
    {
        $sale = $this->makeSale($this->shift, $this->cashier, ['number' => 'INV/1']);
        $printUrl = $this->url('tenant.cashier.receipts.print', ['sale' => $sale->id]);

        $this->actingAs($this->cashier)->postJson($printUrl)->assertOk()->assertJsonPath('data.is_copy', false)->assertJsonPath('data.print_count', 1);
        $this->actingAs($this->cashier)->postJson($printUrl)->assertOk()->assertJsonPath('data.is_copy', true)->assertJsonPath('data.print_count', 2);
        $this->assertSame(2, $this->printCount($sale));
    }

    public function test_the_sheet_is_the_receipt_with_the_store_and_says_copy_after_the_first_print(): void
    {
        $sale = $this->makeSale($this->shift, $this->cashier, ['number' => 'INV/1']);
        $printUrl = $this->url('tenant.cashier.receipts.print', ['sale' => $sale->id]);
        $sheetUrl = $this->url('tenant.cashier.receipts.sheet', ['sale' => $sale->id]);

        $this->actingAs($this->cashier)->postJson($printUrl);
        $this->actingAs($this->cashier)->get($sheetUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Print/Receipt')
            ->where('receipt.number', 'INV/1')
            ->where('receipt.is_copy', false)
            ->where('receipt.store.name', $this->store->name)
            ->where('receipt.store.address', 'Jl. Uji 1')
            ->where('receipt.store.phone', '021-123')
            ->where('receipt.items.0.name', 'Kuas'));

        $this->actingAs($this->cashier)->postJson($printUrl);
        $this->actingAs($this->cashier)->get($sheetUrl)->assertInertia(fn (Assert $page) => $page->where('receipt.is_copy', true));
    }

    public function test_a_voided_receipt_can_still_be_printed_and_says_so(): void
    {
        $sale = $this->makeSale($this->shift, $this->cashier, ['status' => 'voided']);

        $this->actingAs($this->cashier)->postJson($this->url('tenant.cashier.receipts.print', ['sale' => $sale->id]))->assertOk();
        $this->actingAs($this->cashier)->get($this->url('tenant.cashier.receipts.sheet', ['sale' => $sale->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('receipt.status', 'voided'));
    }

    public function test_a_cashier_cannot_print_someone_elses_receipt_but_an_admin_can(): void
    {
        $other = $this->storeUser();
        $otherShift = $this->store->run(fn () => CashShift::factory()->create(['cashier_id' => $other->id, 'cashier_name' => $other->name, 'register_id' => $this->register()->id, 'status' => 'closed']));
        $theirs = $this->makeSale($otherShift, $other);
        $printUrl = $this->url('tenant.cashier.receipts.print', ['sale' => $theirs->id]);

        $this->actingAs($this->cashier)->postJson($printUrl)->assertForbidden();
        $this->actingAs($this->cashier)->get($this->url('tenant.cashier.receipts.sheet', ['sale' => $theirs->id]))->assertForbidden();
        $this->assertSame(0, $this->printCount($theirs));

        $this->actingAs($this->storeUser(UserRole::StoreAdmin))->postJson($printUrl)->assertOk();
        $this->assertSame(1, $this->printCount($theirs));
    }

    public function test_a_guest_is_refused(): void
    {
        $this->getJson($this->url('tenant.cashier.receipts'))->assertUnauthorized();
    }
}
