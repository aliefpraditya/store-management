<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DeliveryNoteStatus;
use App\Enums\PaymentMethod;
use App\Models\CashShift;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\CodSaleService;
use App\Services\DeliveryNoteService;
use App\Services\SaleService;
use App\Services\SaleVoidService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class CodSaleTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_cod_sale';

    private Store $store;

    private User $cashier;

    private Product $brush;

    private CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_cod_sale', 'receipt_prefix' => 'TCS']);
        $this->cashier = $this->storeUser();
        $this->brush = $this->sellableProduct('Kuas', '100');
        $this->shift = $this->openShiftFor($this->cashier);
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.cashier.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

    /**
     * A sale of ten brushes (100.000) as the payment window sends it; `$overrides` replaces any field.
     *
     * @return array<string, mixed>
     */
    private function body(array $overrides = []): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'customer_name' => 'Pak Budi',
            'items' => [['product_unit_id' => $this->unitOf($this->brush, 'PCS')->id, 'quantity' => 10]],
            'payments' => [['method' => 'cod', 'amount' => 100000]],
            'delivery_note' => ['recipient_name' => 'Pak Budi', 'recipient_address' => 'Jl. Mawar 5', 'recipient_phone' => '0812', 'driver_name' => 'Joko', 'vehicle_number' => 'B 1234'],
            ...$overrides,
        ];
    }

    private function sellCod(array $overrides = []): Sale
    {
        return $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, $this->body($overrides)));
    }

    private function saleCount(): int
    {
        return $this->store->run(fn (): int => Sale::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        return $this->store->run(fn (): array => app(ShiftService::class)->summarize($this->shift));
    }

    public function test_cod_is_a_payment_method_at_the_till_and_not_a_way_to_refund(): void
    {
        $this->actingAs($this->cashier)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('payment_methods', [
                ['value' => 'cash', 'label' => 'Tunai'], ['value' => 'transfer', 'label' => 'Transfer'], ['value' => 'qris', 'label' => 'QRIS'], ['value' => 'edc', 'label' => 'EDC'], ['value' => 'cod', 'label' => 'COD'],
            ])
            ->where('urls.cod', $this->url('cod.index')));

        $this->assertNotContains(PaymentMethod::Cod, PaymentMethod::forCashier());
    }

    public function test_a_cod_sale_is_saved_with_its_delivery_note_in_one_go(): void
    {
        $this->actingAs($this->cashier)->post($this->url('sales.store'), $this->body())->assertSessionHasNoErrors()
            ->assertSessionHas('last_sale.is_cod', true)
            ->assertSessionHas('last_sale.cod_amount', '100000.00')
            ->assertSessionHas('last_sale.change_amount', '0.00');

        $sale = $this->store->run(fn (): Sale => Sale::query()->with(['payments', 'deliveryNotes.items'])->sole());
        $note = $sale->deliveryNotes->sole();

        $this->assertSame([PaymentMethod::Cod, '100000.00', '100000.00'], [$sale->payments[0]->method, $sale->payments[0]->amount, $sale->paid_total]);
        $this->assertSame([DeliveryNoteStatus::Draft, 'Pak Budi', 'Jl. Mawar 5', 'Joko', 'B 1234', '10.000'], [$note->status, $note->recipient_name, $note->recipient_address, $note->driver_name, $note->vehicle_number, $note->items[0]->quantity]);
        $this->assertSame('90.000', $this->stockInPieces($this->brush));
    }

    public function test_the_sale_response_offers_the_note_to_print(): void
    {
        $this->actingAs($this->cashier)->post($this->url('sales.store'), $this->body())->assertSessionHasNoErrors();

        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->sole());
        $lastSale = session('last_sale');

        $this->assertSame([['id' => $note->id, 'number' => $note->number, 'print_url' => $this->url('delivery-notes.print', ['delivery_note' => $note->id])]], $lastSale['delivery_notes']);
    }

    public function test_a_cod_sale_cannot_be_saved_without_who_and_where_to_deliver(): void
    {
        $post = fn (array $overrides) => $this->actingAs($this->cashier)->post($this->url('sales.store'), $this->body($overrides));

        $post(['delivery_note' => null])->assertSessionHasErrors(['delivery_note.recipient_name', 'delivery_note.recipient_address']);
        $post(['delivery_note' => ['recipient_name' => 'Pak Budi']])->assertSessionHasErrors(['delivery_note.recipient_address' => 'Alamat pengantaran wajib diisi untuk nota COD.']);
        $post(['delivery_note' => ['recipient_address' => 'Jl. Mawar 5']])->assertSessionHasErrors(['delivery_note.recipient_name' => 'Nama penerima wajib diisi untuk nota COD.']);

        $this->assertSame(0, $this->saleCount());
    }

    public function test_the_service_itself_refuses_a_cod_sale_without_a_note(): void
    {
        $this->expectExceptionMessage('Nota COD harus punya surat jalan. Isi penerima dan alamat pengantaran.');

        $this->sellCod(['delivery_note' => []]);
    }

    public function test_a_sale_without_cod_needs_no_delivery_note(): void
    {
        $this->actingAs($this->cashier)->post($this->url('sales.store'), $this->body(['payments' => [['method' => 'cash', 'amount' => 100000]], 'delivery_note' => null]))->assertSessionHasNoErrors();

        $this->assertSame(0, $this->store->run(fn (): int => DeliveryNote::query()->count()));
    }

    public function test_part_of_the_bill_can_be_paid_now_and_the_rest_on_delivery(): void
    {
        $this->actingAs($this->cashier)->post($this->url('sales.store'), $this->body(['payments' => [['method' => 'cash', 'amount' => 40000], ['method' => 'cod', 'amount' => 60000]]]))->assertSessionHasNoErrors();

        $sale = $this->store->run(fn (): Sale => Sale::query()->with('payments')->sole());
        $this->assertSame(['60000.00', true], [$sale->codAmount(), $sale->isCod()]);
        $this->assertSame(['cash', 'cod'], $sale->payments->map(fn ($payment) => $payment->method->value)->all());
    }

    public function test_cod_has_to_be_exactly_the_rest_of_the_bill_and_only_once(): void
    {
        $post = fn (array $payments) => $this->actingAs($this->cashier)->post($this->url('sales.store'), $this->body(['payments' => $payments]));
        $message = 'Jumlah COD harus sisa tagihan setelah pembayaran lain, tidak lebih dan tidak kurang.';

        $post([['method' => 'cash', 'amount' => 40000], ['method' => 'cod', 'amount' => 50000]])->assertSessionHas('error', $message);
        $post([['method' => 'cash', 'amount' => 50000], ['method' => 'cod', 'amount' => 60000]])->assertSessionHas('error', $message);
        $post([['method' => 'cod', 'amount' => 50000], ['method' => 'cod', 'amount' => 50000]])->assertSessionHas('error', 'COD hanya boleh satu baris pembayaran.');

        $this->assertSame(0, $this->saleCount());
    }

    public function test_a_repeated_submit_of_a_cod_sale_makes_one_sale_and_one_note(): void
    {
        $body = $this->body();

        $this->actingAs($this->cashier)->post($this->url('sales.store'), $body)->assertSessionHasNoErrors();
        $this->actingAs($this->cashier)->post($this->url('sales.store'), $body)->assertSessionHasNoErrors();

        $this->assertSame([1, 1], [$this->saleCount(), $this->store->run(fn (): int => DeliveryNote::query()->count())]);
    }

    public function test_a_repeated_submit_still_offers_the_note_to_print(): void
    {
        $body = $this->body();

        $this->actingAs($this->cashier)->post($this->url('sales.store'), $body)->assertSessionHasNoErrors();
        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->sole());

        $this->actingAs($this->cashier)->post($this->url('sales.store'), $body)->assertSessionHasNoErrors();

        $this->assertSame([['id' => $note->id, 'number' => $note->number, 'print_url' => $this->url('delivery-notes.print', ['delivery_note' => $note->id])]], session('last_sale')['delivery_notes']);
    }

    public function test_the_shift_summary_splits_cod_by_whether_it_was_delivered(): void
    {
        $delivered = $this->sellCod(['payments' => [['method' => 'cash', 'amount' => 40000], ['method' => 'cod', 'amount' => 60000]]]);
        $this->sellCod();
        $this->sellCod();
        $this->store->run(fn () => app(CodSaleService::class)->markDelivered($delivered));

        $summary = $this->summary();

        $this->assertSame(['delivered' => ['count' => 1, 'amount' => '60000.00'], 'undelivered' => ['count' => 2, 'amount' => '200000.00']], $summary['cod']);
        $this->assertSame('300000.00', $summary['total_sales']);
        $this->assertSame('40000.00', $summary['expected_cash']);
    }

    public function test_cod_never_counts_as_cash_in_the_drawer(): void
    {
        $this->sellCod();

        $summary = $this->summary();

        $this->assertSame(['0.00', '0.00', '0.00'], [$summary['expected_cash'], $summary['payments']['cash'], $summary['payments']['transfer']]);
        $this->assertSame(['count' => 1, 'amount' => '100000.00'], $summary['cod']['undelivered']);
    }

    public function test_the_close_shift_figures_reach_the_cashier_screen(): void
    {
        $this->sellCod();

        $this->actingAs($this->cashier)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.cod.undelivered.count', 1)
            ->where('summary.cod.undelivered.amount', '100000.00')
            ->where('summary.cod.delivered.count', 0));
    }

    public function test_a_cashier_marks_a_cod_sale_as_delivered(): void
    {
        $sale = $this->sellCod();

        $this->actingAs($this->cashier)->postJson($this->url('cod.delivered', ['sale' => $sale->id]))->assertOk()->assertJsonPath('data.number', $sale->number);

        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->with('items')->sole());
        $this->assertSame([DeliveryNoteStatus::Delivered, '10.000', '0.000'], [$note->status, $note->items[0]->quantity_delivered, $note->items[0]->quantity_returned]);
        $this->assertSame(['count' => 1, 'amount' => '100000.00'], $this->summary()['cod']['delivered']);

        $this->actingAs($this->cashier)->postJson($this->url('cod.delivered', ['sale' => $sale->id]))->assertUnprocessable()
            ->assertJsonPath('message', 'Nota ini tidak punya surat jalan yang masih berjalan.');
    }

    public function test_marking_delivered_only_works_on_cod_sales(): void
    {
        $cashSale = $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, $this->body(['payments' => [['method' => 'cash', 'amount' => 100000]]])));

        $this->actingAs($this->cashier)->postJson($this->url('cod.delivered', ['sale' => $cashSale->id]))->assertUnprocessable()
            ->assertJsonPath('message', 'Hanya nota COD yang bisa diproses di sini. Nota lain memakai Retur.');
    }

    public function test_a_refused_till_action_answers_with_the_standard_error_body(): void
    {
        $sale = $this->sellCod();
        $this->store->run(fn () => app(CodSaleService::class)->markDelivered($sale));

        $this->actingAs($this->cashier)->postJson($this->url('cod.delivered', ['sale' => $sale->id]))->assertUnprocessable()
            ->assertExactJson([
                'code' => 4223,
                'message' => 'Nota ini tidak punya surat jalan yang masih berjalan.',
                'more_info' => 'Muat ulang daftar COD untuk melihat keadaan nota yang terbaru, lalu coba lagi.',
            ]);
    }

    public function test_a_note_that_is_already_on_its_way_is_finished_as_delivered(): void
    {
        $sale = $this->sellCod();
        $this->store->run(fn () => app(DeliveryNoteService::class)->ship(DeliveryNote::query()->sole()));

        $this->actingAs($this->cashier)->postJson($this->url('cod.delivered', ['sale' => $sale->id]))->assertOk();

        $this->assertSame(DeliveryNoteStatus::Delivered, $this->store->run(fn (): DeliveryNoteStatus => DeliveryNote::query()->sole()->status));
    }

    public function test_a_voided_sale_cannot_be_marked_delivered(): void
    {
        $sale = $this->sellCod();
        $this->store->run(fn () => app(SaleVoidService::class)->void($sale, $this->cashier, 'Batal', null));

        $this->actingAs($this->cashier)->postJson($this->url('cod.delivered', ['sale' => $sale->id]))->assertUnprocessable()
            ->assertJsonPath('message', 'Nota ini sudah di-void. Muat ulang daftar COD untuk melihat keadaan terbaru.');
    }

    public function test_the_cod_list_holds_undelivered_cod_sales_of_any_day(): void
    {
        $waiting = $this->sellCod(['customer_name' => 'Pak Budi']);
        $this->store->run(fn () => Sale::query()->whereKey($waiting->id)->update(['business_date' => today()->subDays(2), 'sold_at' => now()->subDays(2)]));
        $delivered = $this->sellCod();
        $this->store->run(fn () => app(CodSaleService::class)->markDelivered($delivered));
        $this->store->run(fn () => app(SaleService::class)->create($this->shift, $this->cashier, $this->body(['payments' => [['method' => 'cash', 'amount' => 100000]]])));

        $response = $this->actingAs($this->cashier)->getJson($this->url('cod.index'))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.number', $waiting->number)
            ->assertJsonPath('data.0.cod_amount', '100000.00')
            ->assertJsonPath('data.0.recipient_name', 'Pak Budi')
            ->assertJsonPath('data.0.delivery_status_label', 'Draft')
            ->assertJsonPath('data.0.can_mark_delivered', true)
            ->assertJsonPath('data.0.has_cash_deposit', false);

        $this->assertStringEndsWith('/cashier/cod/'.$waiting->id.'/voids', $response->json('data.0.void_url'));
    }

    public function test_a_cod_sale_whose_goods_came_back_stays_on_the_list_until_it_is_voided(): void
    {
        $sale = $this->sellCod();
        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->with('items')->sole());
        $this->store->run(function () use ($note): void {
            $service = app(DeliveryNoteService::class);
            $service->ship($note);
            $service->complete($note, [['item_id' => $note->items[0]->id, 'quantity_delivered' => 0, 'quantity_returned' => 10]]);
        });

        $this->actingAs($this->cashier)->getJson($this->url('cod.index'))->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $sale->number)
            ->assertJsonPath('data.0.delivery_status_label', 'Kembali')
            ->assertJsonPath('data.0.can_mark_delivered', false);
        $this->assertSame(['count' => 1, 'amount' => '100000.00'], $this->summary()['cod']['undelivered']);
    }
}
