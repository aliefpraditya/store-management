<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DeliveryNoteStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\DailyClosing;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\CodSaleService;
use App\Services\DailyClosingService;
use App\Services\DeliveryNoteService;
use App\Services\SaleService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class SaleVoidTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_sale_void';

    private Store $store;

    private User $cashier;

    private User $admin;

    private Product $brush;

    private CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_sale_void', 'receipt_prefix' => 'TSV']);
        $this->cashier = $this->storeUser();
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->brush = $this->sellableProduct('Kuas', '100');
        $this->shift = $this->openShiftFor($this->cashier);
    }

    private function voidUrl(Sale $sale): string
    {
        return route('tenant.cashier.cod.void', ['tenant' => $this->store->code, 'sale' => $sale->id]);
    }

    private function adminVoidUrl(Sale $sale): string
    {
        return route('tenant.sales.void', ['tenant' => $this->store->code, 'sale' => $sale->id]);
    }

    /**
     * Ten brushes (100.000), paid as given; the rest is COD.
     *
     * @param  list<array{method: string, amount: int}>  $payments
     */
    private function sellCod(array $payments = [['method' => 'cod', 'amount' => 100000]]): Sale
    {
        return $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'customer_name' => 'Pak Budi',
            'items' => [['product_unit_id' => $this->unitOf($this->brush, 'PCS')->id, 'quantity' => 10]],
            'payments' => $payments,
            'delivery_note' => ['recipient_name' => 'Pak Budi', 'recipient_address' => 'Jl. Mawar 5'],
        ]));
    }

    private function reloaded(Sale $sale): Sale
    {
        return $this->store->run(fn (): Sale => Sale::query()->findOrFail($sale->id));
    }

    private function summaryOf(CashShift $shift): array
    {
        return $this->store->run(fn (): array => app(ShiftService::class)->summarize($shift));
    }

    /**
     * The first shift is closed and counted, so the deposit's refund has to come out of a later shift of `$user`.
     */
    private function closeFirstShiftAndOpenOneFor(User $user): CashShift
    {
        $expected = $this->summaryOf($this->shift)['expected_cash'];
        $this->store->run(fn () => app(ShiftService::class)->close($this->shift, $expected, null));

        return $this->openShiftFor($user);
    }

    public function test_a_cashier_voids_a_cod_sale_with_a_reason_and_the_goods_go_back_to_stock(): void
    {
        $sale = $this->sellCod();
        $this->assertSame('90.000', $this->stockInPieces($this->brush));

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Pelanggan tidak mau terima'])->assertOk()->assertJsonPath('data.number', $sale->number);

        $sale = $this->reloaded($sale);
        $this->assertSame([SaleStatus::Voided, 'Pelanggan tidak mau terima', $this->cashier->id, $this->cashier->name, '0.00'], [$sale->status, $sale->void_reason, $sale->voided_by, $sale->voided_by_name, $sale->void_cash_refund]);
        $this->assertNotNull($sale->voided_at);
        $this->assertSame('100.000', $this->stockInPieces($this->brush));

        $movement = $this->store->run(fn (): StockMovement => StockMovement::query()->where('type', 'sale_void')->sole());
        $this->assertSame(['10.000', '100.000', $this->cashier->id], [$movement->quantity, $movement->balance_after, $movement->user_id]);
    }

    public function test_the_delivery_note_still_on_its_way_is_cancelled_with_the_sale(): void
    {
        $sale = $this->sellCod();

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Batal'])->assertOk();

        $this->assertSame(DeliveryNoteStatus::Cancelled, $this->store->run(fn (): DeliveryNoteStatus => DeliveryNote::query()->sole()->status));
    }

    public function test_a_voided_sale_leaves_the_shift_figures_and_the_cod_list(): void
    {
        $sale = $this->sellCod();
        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Batal'])->assertOk();

        $summary = $this->summaryOf($this->shift);

        $this->assertSame([0, '0.00', 0], [$summary['sale_count'], $summary['total_sales'], $summary['cod']['undelivered']['count']]);
        $this->actingAs($this->cashier)->getJson(route('tenant.cashier.cod.index', ['tenant' => $this->store->code]))->assertJsonCount(0, 'data');
    }

    public function test_a_reason_is_required(): void
    {
        $sale = $this->sellCod();

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => ''])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertSame(SaleStatus::Completed, $this->reloaded($sale)->status);
        $this->assertSame('90.000', $this->stockInPieces($this->brush));
    }

    public function test_a_sale_of_an_earlier_day_can_be_voided_today(): void
    {
        $sale = $this->sellCod();
        $this->store->run(fn () => Sale::query()->whereKey($sale->id)->update(['business_date' => today()->subDays(3), 'sold_at' => now()->subDays(3)]));

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Diantar besoknya, ditolak'])->assertOk();

        $sale = $this->reloaded($sale);
        $this->assertSame([SaleStatus::Voided, today()->toDateString(), today()->subDays(3)->toDateString()], [$sale->status, $sale->voided_at->toDateString(), $sale->business_date->toDateString()]);
    }

    public function test_a_sale_of_a_closed_day_can_still_be_voided_on_a_later_day(): void
    {
        $sale = $this->sellCod();
        $this->store->run(function () use ($sale): void {
            Sale::query()->whereKey($sale->id)->update(['business_date' => today()->subDay()]);
            DailyClosing::query()->create(['business_date' => today()->subDay(), 'status' => 'closed', 'closed_by' => $this->admin->id, 'closed_at' => now()]);
        });

        $this->actingAs($this->admin)->post($this->adminVoidUrl($sale), ['reason' => 'Ditolak pelanggan'])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(SaleStatus::Voided, $this->reloaded($sale)->status);
    }

    public function test_an_admin_voids_from_the_receipt_page(): void
    {
        $sale = $this->sellCod();

        $this->actingAs($this->admin)->get(route('tenant.sales.show', ['tenant' => $this->store->code, 'sale' => $sale->id]))->assertInertia(fn (Assert $page) => $page
            ->where('sale.is_cod', true)
            ->where('sale.cod_amount', '100000.00')
            ->where('void_blocker', null)
            ->where('urls.void', $this->adminVoidUrl($sale))
            ->has('sale.delivery_notes', 1));

        $this->actingAs($this->admin)->post($this->adminVoidUrl($sale), ['reason' => 'Salah alamat'])->assertSessionHasNoErrors()->assertSessionHas('success', 'Nota di-void. Barangnya kembali ke stok.');

        $this->actingAs($this->admin)->get(route('tenant.sales.show', ['tenant' => $this->store->code, 'sale' => $sale->id]))->assertInertia(fn (Assert $page) => $page
            ->where('sale.status', 'voided')
            ->where('sale.voided_by_name', $this->admin->name)
            ->where('sale.void_reason', 'Salah alamat'));
    }

    public function test_a_voided_sale_cannot_be_voided_again(): void
    {
        $sale = $this->sellCod();
        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Batal'])->assertOk();

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Lagi'])->assertUnprocessable()
            ->assertExactJson([
                'code' => 4223,
                'message' => 'Nota ini sudah di-void. Muat ulang daftar COD untuk melihat keadaan terbaru.',
                'more_info' => 'Muat ulang daftar COD untuk melihat keadaan nota yang terbaru, lalu coba lagi.',
            ]);
        $this->assertSame('100.000', $this->stockInPieces($this->brush));
    }

    public function test_only_cod_sales_can_be_voided(): void
    {
        $cashSale = $this->sellCod([['method' => 'cash', 'amount' => 100000]]);

        $this->actingAs($this->cashier)->postJson($this->voidUrl($cashSale), ['reason' => 'Salah'])->assertUnprocessable()
            ->assertJsonPath('message', 'Hanya nota COD yang bisa diproses di sini. Nota lain memakai Retur.');
        $this->assertSame(SaleStatus::Completed, $this->reloaded($cashSale)->status);
    }

    public function test_a_delivered_cod_sale_cannot_be_voided(): void
    {
        $sale = $this->sellCod();
        $this->store->run(fn () => app(CodSaleService::class)->markDelivered($sale));

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Menyesal'])->assertUnprocessable()
            ->assertJsonPath('message', 'Barang nota ini sudah diantar dan diterima pelanggan, jadi tidak bisa di-void. Pakai Retur untuk barang yang dikembalikan.');

        $this->actingAs($this->admin)->get(route('tenant.sales.show', ['tenant' => $this->store->code, 'sale' => $sale->id]))->assertInertia(fn (Assert $page) => $page
            ->where('void_blocker', 'Barang nota ini sudah diantar dan diterima pelanggan, jadi tidak bisa di-void. Pakai Retur untuk barang yang dikembalikan.'));
        $this->assertSame('90.000', $this->stockInPieces($this->brush));
    }

    public function test_a_cod_sale_with_a_return_cannot_be_voided(): void
    {
        $sale = $this->sellCod();
        $this->store->run(fn () => SaleReturn::factory()->create(['sale_id' => $sale->id, 'status' => SaleReturnStatus::Completed]));

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Batal'])->assertUnprocessable()
            ->assertJsonPath('message', 'Nota ini sudah punya retur, jadi tidak bisa di-void.');
    }

    public function test_a_sale_paid_with_return_credit_cannot_be_voided(): void
    {
        $sale = $this->sellCod();
        $this->store->run(fn () => SalePayment::query()->create(['sale_id' => $sale->id, 'method' => PaymentMethod::ReturnCredit, 'amount' => '0.00']));

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Batal'])->assertUnprocessable()
            ->assertJsonPath('message', 'Nota pengganti retur tidak bisa di-void. Pakai Retur.');
        $this->assertSame(SaleStatus::Completed, $this->reloaded($sale)->status);
    }

    public function test_a_note_whose_goods_came_back_does_not_stand_in_the_way_of_the_void(): void
    {
        $sale = $this->sellCod();
        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->with('items')->sole());
        $this->store->run(function () use ($note): void {
            $service = app(DeliveryNoteService::class);
            $service->ship($note);
            $service->complete($note, [['item_id' => $note->items[0]->id, 'quantity_delivered' => 0, 'quantity_returned' => 10]]);
        });

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Dibawa pulang lagi'])->assertOk();

        $this->assertSame([SaleStatus::Voided, DeliveryNoteStatus::Returned], [$this->reloaded($sale)->status, $this->store->run(fn (): DeliveryNoteStatus => DeliveryNote::query()->sole()->status)]);
    }

    public function test_any_cashier_of_the_store_may_void_a_cod_sale_of_another(): void
    {
        $sale = $this->sellCod();
        $otherCashier = $this->storeUser();

        $this->actingAs($otherCashier)->postJson($this->voidUrl($sale), ['reason' => 'Pelanggan menelepon'])->assertOk();

        $sale = $this->reloaded($sale);
        $this->assertSame([SaleStatus::Voided, $otherCashier->id, $this->cashier->id], [$sale->status, $sale->voided_by, $sale->cashier_id]);
    }

    public function test_a_deposit_by_cash_and_by_transfer_goes_back_to_its_own_place(): void
    {
        $sale = $this->sellCod([['method' => 'cash', 'amount' => 20000], ['method' => 'transfer', 'amount' => 30000], ['method' => 'cod', 'amount' => 50000]]);

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Ditolak', 'refund_account' => 'BCA 1234567 a.n. Budi'])->assertOk();

        $sale = $this->reloaded($sale);
        $this->assertSame(['20000.00', 'BCA 1234567 a.n. Budi', $this->shift->id], [$sale->void_cash_refund, $sale->void_refund_account, $sale->void_cash_shift_id]);
    }

    public function test_the_shift_names_the_money_a_voided_receipt_took_in_apart_from_the_sales(): void
    {
        $voided = $this->sellCod([['method' => 'cash', 'amount' => 20000], ['method' => 'transfer', 'amount' => 30000], ['method' => 'cod', 'amount' => 50000]]);
        $this->sellCod();
        $this->actingAs($this->cashier)->postJson($this->voidUrl($voided), ['reason' => 'Ditolak', 'refund_account' => 'BCA 1234567 a.n. Budi'])->assertOk();

        $summary = $this->summaryOf($this->shift);

        $this->assertSame(['50000.00', '100000.00', '20000.00', '30000.00', 1], [$summary['void_deposits'], $summary['total_sales'], $summary['payments']['cash'], $summary['payments']['transfer'], $summary['sale_count']]);
    }

    public function test_the_receipt_page_tells_which_kinds_of_deposit_the_sale_holds(): void
    {
        $sale = $this->sellCod([['method' => 'cash', 'amount' => 20000], ['method' => 'transfer', 'amount' => 30000], ['method' => 'cod', 'amount' => 50000]]);
        $codOnly = $this->sellCod();
        $showUrl = fn (Sale $sale): string => route('tenant.sales.show', ['tenant' => $this->store->code, 'sale' => $sale->id]);

        $this->actingAs($this->admin)->get($showUrl($sale))->assertInertia(fn (Assert $page) => $page->where('sale.has_cash_deposit', true)->where('sale.has_non_cash_deposit', true));
        $this->actingAs($this->admin)->get($showUrl($codOnly))->assertInertia(fn (Assert $page) => $page->where('sale.has_cash_deposit', false)->where('sale.has_non_cash_deposit', false));
    }

    public function test_the_notes_of_the_sale_are_locked_while_it_is_voided(): void
    {
        $sale = $this->sellCod();
        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Batal'])->assertOk();

        $this->assertNotEmpty(array_filter($statements, fn (string $sql): bool => str_contains($sql, 'from `delivery_notes`') && str_contains($sql, 'for update')));
    }

    public function test_voiding_on_a_later_day_leaves_the_saved_figures_of_the_closed_day_as_they_were(): void
    {
        $sale = $this->sellCod();
        $yesterday = today()->subDay();
        $this->store->run(function () use ($sale, $yesterday): void {
            Sale::query()->whereKey($sale->id)->update(['business_date' => $yesterday]);
            DailyClosing::query()->create(['business_date' => $yesterday, 'status' => 'closed', 'sales_count' => 1, 'total_sales' => 100000, 'voids_count' => 0, 'closed_by' => $this->admin->id, 'closed_at' => now()]);
        });

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Ditolak besoknya'])->assertOk();

        $closing = $this->store->run(fn (): DailyClosing => DailyClosing::query()->whereDate('business_date', $yesterday)->sole());
        $this->assertSame([1, '100000.00', 0], [$closing->sales_count, $closing->total_sales, $closing->voids_count]);
        $this->assertSame(1, $this->store->run(fn (): int => app(DailyClosingService::class)->summarize(today())['voids_count']));
    }

    public function test_a_void_belongs_to_the_business_day_of_the_shift_of_the_voider_even_after_midnight(): void
    {
        $sale = $this->sellCod([['method' => 'cash', 'amount' => 40000], ['method' => 'cod', 'amount' => 60000]]);
        $shiftDay = today();

        $this->travelTo($shiftDay->copy()->addDay()->setTime(0, 30));
        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Batal'])->assertOk();

        $this->assertSame($shiftDay->toDateString(), $this->reloaded($sale)->void_business_date->toDateString());
        $this->store->run(function () use ($shiftDay): void {
            $closing = app(DailyClosingService::class);
            $this->assertSame([1, '40000.00'], [$closing->summarize($shiftDay)['voids_count'], $closing->summarize($shiftDay)['refunds_cash']]);
            $this->assertSame([0, '0.00'], [$closing->summarize($shiftDay->copy()->addDay())['voids_count'], $closing->summarize($shiftDay->copy()->addDay())['refunds_cash']]);
        });
    }

    public function test_a_void_by_an_admin_without_a_shift_belongs_to_the_day_it_was_done(): void
    {
        $sale = $this->sellCod();

        $this->travelTo(today()->addDay()->setTime(10, 0));
        $this->actingAs($this->admin)->post($this->adminVoidUrl($sale), ['reason' => 'Ditolak'])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(today()->toDateString(), $this->reloaded($sale)->void_business_date->toDateString());
        $this->assertSame([0, 1], $this->store->run(fn (): array => [
            app(DailyClosingService::class)->summarize(today()->subDay())['voids_count'],
            app(DailyClosingService::class)->summarize(today())['voids_count'],
        ]));
    }

    public function test_a_cash_deposit_goes_back_out_of_the_drawer_of_the_shift_that_voids(): void
    {
        $sale = $this->sellCod([['method' => 'cash', 'amount' => 40000], ['method' => 'cod', 'amount' => 60000]]);
        $this->assertSame('40000.00', $this->summaryOf($this->shift)['expected_cash']);

        $laterShift = $this->closeFirstShiftAndOpenOneFor($this->admin);
        $this->actingAs($this->admin)->post($this->adminVoidUrl($sale), ['reason' => 'Ditolak'])->assertSessionHasNoErrors()->assertSessionHas('success');

        $sale = $this->reloaded($sale);
        $this->assertSame([SaleStatus::Voided, $laterShift->id, '40000.00'], [$sale->status, $sale->void_cash_shift_id, $sale->void_cash_refund]);
        $this->assertSame(['-40000.00', '40000.00'], [$this->summaryOf($laterShift)['expected_cash'], $this->summaryOf($laterShift)['void_cash_refunds']]);
    }

    public function test_a_deposit_voided_in_the_same_shift_nets_out_in_the_drawer(): void
    {
        $sale = $this->sellCod([['method' => 'cash', 'amount' => 40000], ['method' => 'cod', 'amount' => 60000]]);

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Ditolak'])->assertOk();

        $this->assertSame('0.00', $this->summaryOf($this->shift)['expected_cash']);
    }

    public function test_a_cash_deposit_needs_a_running_shift_to_pay_it_back(): void
    {
        $sale = $this->sellCod([['method' => 'cash', 'amount' => 40000], ['method' => 'cod', 'amount' => 60000]]);

        $this->actingAs($this->admin)->post($this->adminVoidUrl($sale), ['reason' => 'Ditolak'])
            ->assertSessionHas('error', 'Nota ini sudah menerima DP tunai. Buka shift dulu untuk mengembalikannya dari laci.');

        $this->assertSame(SaleStatus::Completed, $this->reloaded($sale)->status);
        $this->assertSame('90.000', $this->stockInPieces($this->brush));
    }

    public function test_a_non_cash_deposit_needs_the_account_it_goes_back_to(): void
    {
        $sale = $this->sellCod([['method' => 'transfer', 'amount' => 30000], ['method' => 'cod', 'amount' => 70000]]);

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Ditolak'])->assertUnprocessable()
            ->assertJsonPath('message', 'Nota ini sudah menerima DP non tunai. Isi rekening penerima pengembaliannya.');
        $this->assertSame(SaleStatus::Completed, $this->reloaded($sale)->status);

        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Ditolak', 'refund_account' => 'BCA 1234567 a.n. Budi'])->assertOk();

        $sale = $this->reloaded($sale);
        $this->assertSame(['BCA 1234567 a.n. Budi', '0.00', null], [$sale->void_refund_account, $sale->void_cash_refund, $sale->void_cash_shift_id]);
    }

    public function test_a_closed_day_does_not_take_a_void_until_it_is_reopened(): void
    {
        $sale = $this->sellCod();
        $this->store->run(fn () => app(ShiftService::class)->close($this->shift, '0', null));
        $this->actingAs($this->admin)->post(route('tenant.daily-closings.store', ['tenant' => $this->store->code, 'date' => today()->toDateString()]))->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post($this->adminVoidUrl($sale), ['reason' => 'Ditolak'])
            ->assertSessionHas('error', 'Hari '.today()->format('d/m/Y').' sudah ditutup, jadi void hari ini belum bisa dicatat. Minta Super Admin membuka kembali hari itu.');
        $this->assertSame(SaleStatus::Completed, $this->reloaded($sale)->status);

        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($superAdmin)->post(route('tenant.daily-closings.reopen', ['tenant' => $this->store->code, 'date' => today()->toDateString()]), ['reason' => 'Ada void'])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post($this->adminVoidUrl($sale), ['reason' => 'Ditolak'])->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    public function test_the_day_shows_the_receipts_voided_on_it_and_counts_them_apart(): void
    {
        $sale = $this->sellCod([['method' => 'cash', 'amount' => 40000], ['method' => 'cod', 'amount' => 60000]]);
        $this->sellCod();
        $this->store->run(fn () => Sale::query()->whereKey($sale->id)->update(['business_date' => today()->subDay()]));
        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Ditolak di rumah'])->assertOk();

        $figures = $this->store->run(fn (): array => app(DailyClosingService::class)->summarize(today()));

        $this->assertSame([1, '100000.00', '100000.00', '100000.00'], [$figures['voids_count'], $figures['total_voids'], $figures['total_sales'], $figures['sales_cod']]);
        $this->assertSame(['40000.00', '40000.00'], [$figures['refunds_cash'], $this->store->run(fn (): string => app(DailyClosingService::class)->summarize(today()->subDay())['sales_cash'])]);

        $this->actingAs($this->admin)->get(route('tenant.daily-closings.show', ['tenant' => $this->store->code, 'date' => today()->toDateString()]))->assertInertia(fn (Assert $page) => $page
            ->has('voids', 1)
            ->where('voids.0.number', $sale->number)
            ->where('voids.0.sold_on', today()->subDay()->toDateString())
            ->where('voids.0.reason', 'Ditolak di rumah')
            ->where('voids.0.voided_by_name', $this->cashier->name)
            ->where('voids.0.total', '100000.00'));
    }

    public function test_the_cash_of_a_day_with_a_voided_deposit_still_adds_up(): void
    {
        $sale = $this->sellCod([['method' => 'cash', 'amount' => 40000], ['method' => 'cod', 'amount' => 60000]]);
        $this->actingAs($this->cashier)->postJson($this->voidUrl($sale), ['reason' => 'Ditolak'])->assertOk();
        $this->store->run(fn () => app(ShiftService::class)->close($this->shift, '0', null));

        $closing = $this->store->run(fn () => app(DailyClosingService::class)->close(today(), $this->admin, null));

        $this->assertSame(
            ['40000.00', '40000.00', '0.00', '1', '100000.00'],
            [$closing->sales_cash, $closing->refunds_cash, $closing->total_expected_cash, (string) $closing->voids_count, $closing->total_voids],
        );
    }

    public function test_a_cashier_can_use_the_till_routes_and_they_are_open_only_to_store_users(): void
    {
        $sale = $this->sellCod();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->postJson($this->voidUrl($sale), ['reason' => 'Coba'])->assertForbidden();
        $this->assertSame(SaleStatus::Completed, $this->reloaded($sale)->status);
    }
}
