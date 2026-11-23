<?php

namespace Tests\Feature\Tenancy;

use App\Enums\CodSettlementStatus;
use App\Enums\DeliveryNoteStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\CodSettlement;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Store;
use App\Models\User;
use App\Services\CodSaleService;
use App\Services\DailyClosingService;
use App\Services\DeliveryNoteService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class CodSettlementTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_cod_settlement';

    private Store $store;

    private User $cashier;

    private User $admin;

    private Product $brush;

    private CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_cod_settlement', 'receipt_prefix' => 'TCT']);
        $this->cashier = $this->storeUser();
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
        $this->brush = $this->sellableProduct('Kuas', '100');
        $this->shift = $this->openShiftFor($this->cashier);
    }

    private function url(string $name, array $parameters = []): string
    {
        return route("tenant.cashier.{$name}", ['tenant' => $this->store->code, ...$parameters]);
    }

    private function grant(User $user, Permission ...$permissions): void
    {
        $central = DB::connection('mysql');

        foreach ($permissions as $permission) {
            $central->table('permission_user')->insert(['user_id' => $user->id, 'permission_id' => $central->table('permissions')->where('name', $permission->value)->value('id')]);
        }
    }

    /**
     * Ten brushes (100.000) on COD, as the payment window sends it.
     */
    private function sellCod(): Sale
    {
        return $this->store->run(fn (): Sale => app(SaleService::class)->create($this->shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'customer_name' => 'Pak Budi',
            'items' => [['product_unit_id' => $this->unitOf($this->brush, 'PCS')->id, 'quantity' => 10]],
            'payments' => [['method' => 'cod', 'amount' => 100000]],
            'delivery_note' => ['recipient_name' => 'Pak Budi', 'recipient_address' => 'Jl. Mawar 5', 'recipient_phone' => '0812'],
        ]));
    }

    private function sellDeliveredCod(): Sale
    {
        $sale = $this->sellCod();
        $this->store->run(fn () => app(CodSaleService::class)->markDelivered($sale));

        return $sale;
    }

    /**
     * @return array<string, mixed>
     */
    private function receiveBody(array $overrides = []): array
    {
        return ['client_uuid' => (string) Str::uuid(), 'method' => 'cash', 'amount' => 100000, ...$overrides];
    }

    private function settlementOf(Sale $sale): ?CodSettlement
    {
        return $this->store->run(fn (): ?CodSettlement => CodSettlement::query()->where('sale_id', $sale->id)->first());
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        return $this->store->run(fn (): array => app(ShiftService::class)->summarize($this->shift));
    }

    private function returnBrushes(Sale $sale, int $quantity, ?User $by = null): SaleReturn
    {
        $by ??= $this->admin;

        return $this->store->run(fn (): SaleReturn => app(SaleReturnService::class)->createApproved($sale, $this->shift, $by, [
            'client_uuid' => (string) Str::uuid(),
            'refund_method' => 'cash',
            'items' => [['sale_item_id' => $sale->items()->value('id'), 'quantity' => $quantity]],
        ]));
    }

    public function test_the_money_cannot_be_received_before_the_goods_are_delivered(): void
    {
        $sale = $this->sellCod();
        $this->grant($this->cashier, Permission::CodReceive);

        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody())->assertUnprocessable()
            ->assertJsonPath('code', 4223)
            ->assertJsonPath('message', 'Barang nota ini belum diantar, jadi uangnya belum bisa diterima. Tandai sudah diantar dulu.');

        $this->assertNull($this->settlementOf($sale));
    }

    public function test_a_cashier_without_the_permission_can_neither_receive_the_money_nor_see_the_list(): void
    {
        $sale = $this->sellDeliveredCod();

        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody())->assertForbidden();
        $this->actingAs($this->cashier)->getJson($this->url('cod.settlements'))->assertForbidden();
        $this->actingAs($this->cashier)->postJson($this->url('cod.approve', ['sale' => $sale->id]))->assertForbidden();
        $this->assertNull($this->settlementOf($sale));
    }

    public function test_the_cash_received_lands_in_the_drawer_of_the_one_who_took_it(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->grant($this->cashier, Permission::CodReceive);

        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody(['notes' => 'Disetor sopir Andi']))->assertOk()
            ->assertJsonPath('data.number', $sale->number)
            ->assertJsonPath('data.state', 'confirmed');

        $settlement = $this->settlementOf($sale);
        $this->assertSame([CodSettlementStatus::Confirmed, '100000.00', '100000.00', $this->shift->id, 'Disetor sopir Andi'], [$settlement->status, $settlement->amount, $settlement->expected_amount, $settlement->cash_shift_id, $settlement->notes]);

        $summary = $this->summary();
        $this->assertSame(['cash' => '100000.00', 'transfer' => '0.00', 'qris' => '0.00', 'edc' => '0.00', 'total' => '100000.00'], $summary['cod_received']);
        $this->assertSame('100000.00', $summary['expected_cash']);
        $this->assertSame(['count' => 1, 'amount' => '100000.00'], $summary['cod']['delivered']);
    }

    public function test_money_by_transfer_stays_out_of_the_drawer_and_needs_no_shift(): void
    {
        $sale = $this->sellDeliveredCod();

        $this->actingAs($this->admin)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody(['method' => 'transfer', 'reference' => 'BCA 1234 a.n. Budi']))->assertOk()
            ->assertJsonPath('data.state', 'confirmed');

        $settlement = $this->settlementOf($sale);
        $this->assertNull($settlement->cash_shift_id);
        $this->assertSame(today()->toDateString(), $settlement->business_date->toDateString());
        $this->assertSame('BCA 1234 a.n. Budi', $settlement->reference);
        $this->assertSame('0.00', $this->summary()['expected_cash']);
    }

    public function test_cash_needs_a_running_shift_of_the_one_who_receives_it(): void
    {
        $sale = $this->sellDeliveredCod();

        $this->actingAs($this->admin)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody())->assertUnprocessable()
            ->assertJsonPath('code', 4223);

        $this->assertNull($this->settlementOf($sale));
    }

    public function test_a_difference_needs_a_reason(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->grant($this->cashier, Permission::CodReceive);

        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody(['amount' => 90000]))->assertUnprocessable()
            ->assertJsonPath('message', 'Jumlah yang diterima berbeda dari yang seharusnya. Isi alasan selisihnya.');

        $this->assertNull($this->settlementOf($sale));
    }

    public function test_a_difference_taken_by_someone_who_may_not_approve_waits_for_an_approval_but_counts_in_the_drawer(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->grant($this->cashier, Permission::CodReceive);

        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody(['amount' => 90000, 'difference_reason' => 'Uang kembalian sopir kececer']))->assertOk()
            ->assertJsonPath('data.state', 'awaiting_approval');

        $settlement = $this->settlementOf($sale);
        $this->assertSame([CodSettlementStatus::AwaitingApproval, '90000.00', '100000.00', 'Uang kembalian sopir kececer', null], [$settlement->status, $settlement->amount, $settlement->expected_amount, $settlement->difference_reason, $settlement->approved_by]);
        $this->assertSame('90000.00', $this->summary()['expected_cash']);

        $checklist = $this->store->run(fn (): array => app(DailyClosingService::class)->checklist(today()));
        $this->assertFalse($checklist['can_close']);
        $this->assertSame([['label' => $sale->number, 'amount' => '90000.00', 'show_url' => route('tenant.sales.show', ['tenant' => $this->store->code, 'sale' => $sale->id])]], $checklist['pending_cod_approvals']);

        $this->actingAs($this->cashier)->postJson($this->url('cod.approve', ['sale' => $sale->id]))->assertForbidden();
    }

    public function test_an_admin_approves_the_difference_and_the_day_is_no_longer_held_by_it(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->grant($this->cashier, Permission::CodReceive);
        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody(['amount' => 90000, 'difference_reason' => 'Kececer']))->assertOk();

        $this->actingAs($this->admin)->postJson($this->url('cod.approve', ['sale' => $sale->id]))->assertOk();

        $settlement = $this->settlementOf($sale);
        $this->assertSame([CodSettlementStatus::Confirmed, $this->admin->id, $this->admin->name], [$settlement->status, $settlement->approved_by, $settlement->approved_by_name]);
        $this->assertNotNull($settlement->approved_at);
        $this->assertSame([], $this->store->run(fn (): array => app(DailyClosingService::class)->checklist(today()))['pending_cod_approvals']);

        $this->actingAs($this->admin)->postJson($this->url('cod.approve', ['sale' => $sale->id]))->assertUnprocessable()
            ->assertJsonPath('message', 'Penerimaan uang COD nota ini sudah disetujui.');
    }

    public function test_a_difference_taken_by_someone_who_may_approve_needs_no_one_else(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->grant($this->cashier, Permission::CodReceive, Permission::CodApprove);

        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody(['amount' => 110000, 'difference_reason' => 'Pelanggan membulatkan']))->assertOk()
            ->assertJsonPath('data.state', 'confirmed');

        $settlement = $this->settlementOf($sale);
        $this->assertSame([CodSettlementStatus::Confirmed, $this->cashier->id], [$settlement->status, $settlement->approved_by]);
    }

    public function test_the_money_of_a_sale_is_received_once_and_a_double_submit_returns_the_first(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->grant($this->cashier, Permission::CodReceive);
        $body = $this->receiveBody();

        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $body)->assertOk();
        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $body)->assertOk();
        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody())->assertUnprocessable()
            ->assertJsonPath('message', 'Uang COD nota ini sudah diterima. Muat ulang daftar COD untuk melihat keadaan terbaru.');

        $this->assertSame(1, $this->store->run(fn (): int => CodSettlement::query()->count()));
        $this->assertSame('100000.00', $this->summary()['cod_received']['total']);
    }

    public function test_only_cash_transfer_qris_and_edc_can_receive_the_money(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->grant($this->cashier, Permission::CodReceive);

        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody(['method' => 'cod']))->assertUnprocessable()->assertJsonValidationErrors('method');
        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody(['amount' => 0]))->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_the_list_holds_delivered_sales_whose_money_is_waiting_and_those_waiting_for_an_approval(): void
    {
        $unreceived = $this->sellDeliveredCod();
        $withDifference = $this->sellDeliveredCod();
        $received = $this->sellDeliveredCod();
        $this->sellCod();
        $this->grant($this->cashier, Permission::CodReceive);
        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $withDifference->id]), $this->receiveBody(['amount' => 95000, 'difference_reason' => 'Kurang']))->assertOk();
        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $received->id]), $this->receiveBody())->assertOk();

        $response = $this->actingAs($this->admin)->getJson($this->url('cod.settlements'))->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.awaiting_approval_total', 1)
            ->assertJsonPath('data.0.number', $withDifference->number)
            ->assertJsonPath('data.0.state', 'awaiting_approval')
            ->assertJsonPath('data.0.settlement.amount', '95000.00')
            ->assertJsonPath('data.0.settlement.difference_reason', 'Kurang')
            ->assertJsonPath('data.1.number', $unreceived->number)
            ->assertJsonPath('data.1.state', 'unsettled')
            ->assertJsonPath('data.1.amount_due', '100000.00')
            ->assertJsonPath('data.1.offset_amount', '0.00')
            ->assertJsonPath('data.1.has_unreturned_goods', false)
            ->assertJsonPath('data.1.settlement', null);

        $this->assertStringEndsWith('/cashier/cod/'.$unreceived->id.'/settlements', $response->json('data.1.settle_url'));
        $this->assertStringEndsWith('/cashier/cod/'.$unreceived->id.'/settlements/approval', $response->json('data.1.approve_url'));
    }

    private function noteUrl(string $name, Sale $sale): string
    {
        $note = $this->store->run(fn (): DeliveryNote => DeliveryNote::query()->where('sale_id', $sale->id)->firstOrFail());

        return route("tenant.delivery-notes.{$name}", ['tenant' => $this->store->code, 'delivery_note' => $note->id]);
    }

    public function test_the_money_is_received_from_the_delivery_note_page(): void
    {
        $sale = $this->sellDeliveredCod();

        $this->actingAs($this->admin)->get($this->noteUrl('show', $sale))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('cod.state', 'unsettled')
            ->where('cod.is_goods_delivered', true)
            ->where('cod.cod_amount', '100000.00')
            ->where('cod.amount_due', '100000.00')
            ->where('cod.can_receive', true)
            ->where('cod.can_approve', true));

        $this->actingAs($this->admin)->post($this->noteUrl('cod-settlement', $sale), $this->receiveBody(['method' => 'transfer']))
            ->assertSessionHas('success', 'Uang COD diterima.');

        $settlement = $this->settlementOf($sale);
        $this->assertSame([PaymentMethod::Transfer, CodSettlementStatus::Confirmed], [$settlement->method, $settlement->status]);

        $this->actingAs($this->admin)->get($this->noteUrl('show', $sale))->assertInertia(fn (Assert $page) => $page
            ->where('cod.state', 'settled')
            ->where('cod.settlement.method_label', 'Transfer'));
    }

    public function test_the_note_page_holds_the_money_back_until_the_goods_are_delivered(): void
    {
        $sale = $this->sellCod();

        $this->actingAs($this->admin)->get($this->noteUrl('show', $sale))->assertInertia(fn (Assert $page) => $page
            ->where('cod.state', 'unsettled')
            ->where('cod.is_goods_delivered', false));

        $this->actingAs($this->admin)->post($this->noteUrl('cod-settlement', $sale), $this->receiveBody())->assertSessionHas('error');
        $this->assertNull($this->settlementOf($sale));
    }

    public function test_the_amount_due_on_the_note_page_follows_the_returns(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->returnBrushes($sale, 1);

        $this->actingAs($this->admin)->get($this->noteUrl('show', $sale))->assertInertia(fn (Assert $page) => $page
            ->where('cod.offset_amount', '10000.00')
            ->where('cod.amount_due', '90000.00'));
    }

    public function test_someone_who_may_view_notes_but_not_handle_the_money_gets_no_money_panel_and_cannot_receive(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->grant($this->cashier, Permission::DeliveryNotesView);

        $this->actingAs($this->cashier)->get($this->noteUrl('show', $sale))->assertOk()->assertInertia(fn (Assert $page) => $page->where('cod', null));
        $this->actingAs($this->cashier)->post($this->noteUrl('cod-settlement', $sale), $this->receiveBody())->assertForbidden();
        $this->actingAs($this->cashier)->post($this->noteUrl('cod-settlement.approve', $sale))->assertForbidden();
        $this->assertNull($this->settlementOf($sale));
    }

    public function test_a_difference_received_from_the_note_page_waits_for_an_admin_who_approves_it_there(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->grant($this->cashier, Permission::DeliveryNotesView, Permission::CodReceive);

        $this->actingAs($this->cashier)->post($this->noteUrl('cod-settlement', $sale), $this->receiveBody(['amount' => 95000, 'difference_reason' => 'Kurang']))
            ->assertSessionHas('success', 'Uang COD tercatat. Selisihnya menunggu persetujuan.');

        $this->actingAs($this->cashier)->get($this->noteUrl('show', $sale))->assertInertia(fn (Assert $page) => $page
            ->where('cod.state', 'awaiting_approval')
            ->where('cod.can_receive', true)
            ->where('cod.can_approve', false));

        $this->actingAs($this->admin)->post($this->noteUrl('cod-settlement.approve', $sale))->assertSessionHas('success', 'Penerimaan uang COD disetujui.');

        $this->actingAs($this->admin)->get($this->noteUrl('show', $sale))->assertInertia(fn (Assert $page) => $page->where('cod.state', 'settled'));
    }

    public function test_both_cod_lists_name_the_delivery_note_with_its_status_and_a_link_for_those_who_may_view_it(): void
    {
        $waiting = $this->sellCod();
        $delivered = $this->sellDeliveredCod();
        $notes = $this->store->run(fn () => DeliveryNote::query()->pluck('number', 'sale_id')->all());

        $delivery = $this->actingAs($this->admin)->getJson($this->url('cod.index'))->assertOk();
        $settlements = $this->actingAs($this->admin)->getJson($this->url('cod.settlements'))->assertOk();

        $this->assertSame(
            [$notes[$waiting->id], 'Draft'],
            [$delivery->json('data.0.delivery_note.number'), $delivery->json('data.0.delivery_note.status_label')],
        );
        $this->assertSame(
            [$notes[$delivered->id], 'Terkirim'],
            [$settlements->json('data.0.delivery_note.number'), $settlements->json('data.0.delivery_note.status_label')],
        );
        $this->assertStringContainsString('/delivery-notes/', $settlements->json('data.0.delivery_note.show_url'));
    }

    public function test_the_delivery_notes_menu_counts_the_cod_money_that_waits_but_only_for_those_who_may_handle_it(): void
    {
        $this->sellDeliveredCod();
        $received = $this->sellDeliveredCod();
        $this->sellCod();
        $this->actingAs($this->admin)->post($this->noteUrl('cod-settlement', $received), $this->receiveBody(['method' => 'transfer']));

        $this->actingAs($this->admin)->get(route('tenant.dashboard', ['tenant' => $this->store->code]))
            ->assertInertia(fn (Assert $page) => $page->where('menu_badges.delivery_notes', 1));
        $this->actingAs($this->cashier)->get($this->url('index'))
            ->assertInertia(fn (Assert $page) => $page->where('menu_badges.delivery_notes', 0));
    }

    public function test_the_cashier_screen_counts_the_cod_that_waits_and_counts_money_only_for_who_may_handle_it(): void
    {
        $this->sellCod();
        $this->sellDeliveredCod();

        $this->actingAs($this->admin)->get($this->url('index'))
            ->assertInertia(fn (Assert $page) => $page->where('cod_counts', ['undelivered' => 1, 'unreceived' => 1]));
        $this->actingAs($this->cashier)->get($this->url('index'))
            ->assertInertia(fn (Assert $page) => $page->where('cod_counts', ['undelivered' => 1, 'unreceived' => 0]));
    }

    public function test_the_delivery_note_list_tells_where_the_money_stands(): void
    {
        $unreceived = $this->sellDeliveredCod();
        $undelivered = $this->sellCod();
        $received = $this->sellDeliveredCod();
        $awaiting = $this->sellDeliveredCod();
        $plain = $this->store->run(function (): Sale {
            $sale = Sale::factory()->create();
            DeliveryNote::factory()->create(['sale_id' => $sale->id]);

            return $sale;
        });
        $this->actingAs($this->admin)->post($this->noteUrl('cod-settlement', $received), $this->receiveBody(['method' => 'transfer']));
        $this->grant($this->cashier, Permission::CodReceive);
        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $awaiting->id]), $this->receiveBody(['amount' => 95000, 'difference_reason' => 'Kurang']))->assertOk();

        $this->actingAs($this->admin)->get(route('tenant.delivery-notes.index', ['tenant' => $this->store->code]))->assertInertia(fn (Assert $page) => $page
            ->where('notes.data', fn ($rows) => $rows->pluck('cod_state', 'sale_number')->all() === [
                $plain->number => null,
                $awaiting->number => 'awaiting_approval',
                $received->number => 'received',
                $undelivered->number => 'not_delivered',
                $unreceived->number => 'unreceived',
            ]));
    }

    public function test_a_refund_return_of_an_unpaid_cod_sale_cuts_the_bill_and_pays_nothing_out(): void
    {
        $sale = $this->sellDeliveredCod();

        $saleReturn = $this->returnBrushes($sale, 1);

        $this->assertSame(['10000.00', '0.00', '10000.00'], [$saleReturn->credit_amount, $saleReturn->refund_amount, $saleReturn->cod_offset_amount]);
        $this->assertSame('0.00', $this->summary()['cash_refunds']);
        $this->assertSame('0.00', $this->summary()['expected_cash']);

        $this->actingAs($this->admin)->getJson($this->url('cod.settlements'))
            ->assertJsonPath('data.0.amount_due', '90000.00')
            ->assertJsonPath('data.0.offset_amount', '10000.00');

        $this->grant($this->cashier, Permission::CodReceive);
        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody(['amount' => 90000]))->assertOk()
            ->assertJsonPath('data.state', 'confirmed');
        $this->assertSame('90000.00', $this->summary()['expected_cash']);
    }

    public function test_two_returns_never_cut_more_than_the_bill(): void
    {
        $sale = $this->sellDeliveredCod();

        $first = $this->returnBrushes($sale, 6);
        $second = $this->returnBrushes($sale, 4);

        $this->assertSame([['60000.00', '0.00'], ['40000.00', '0.00']], [[$first->cod_offset_amount, $first->refund_amount], [$second->cod_offset_amount, $second->refund_amount]]);

        $this->grant($this->cashier, Permission::CodReceive);
        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody())->assertUnprocessable()
            ->assertJsonPath('message', 'Seluruh tagihan COD nota ini sudah terpotong retur, jadi tidak ada uang yang perlu diterima.');
        $this->actingAs($this->admin)->getJson($this->url('cod.settlements'))->assertJsonCount(0, 'data');
    }

    public function test_a_return_after_the_money_was_received_pays_out_as_usual(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->grant($this->cashier, Permission::CodReceive);
        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody())->assertOk();

        $saleReturn = $this->returnBrushes($sale, 1);

        $this->assertSame(['10000.00', '0.00'], [$saleReturn->refund_amount, $saleReturn->cod_offset_amount]);
        $this->assertSame('10000.00', $this->summary()['cash_refunds']);
        $this->assertSame('90000.00', $this->summary()['expected_cash']);
    }

    public function test_the_money_waits_while_a_return_of_the_sale_waits_for_its_decision(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->store->run(fn () => app(SaleReturnService::class)->create($sale, $this->shift, $this->cashier, [
            'client_uuid' => (string) Str::uuid(),
            'refund_method' => 'cash',
            'items' => [['sale_item_id' => $sale->items()->value('id'), 'quantity' => 1]],
        ]));
        $this->grant($this->cashier, Permission::CodReceive);

        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody(['amount' => 90000]))->assertUnprocessable()
            ->assertJsonPath('message', 'Nota ini punya retur yang menunggu persetujuan. Setujui atau tolak retur itu dulu, supaya jumlah yang harus diterima sudah pasti.');
    }

    public function test_goods_that_came_back_on_the_note_without_a_return_are_flagged_on_the_list(): void
    {
        $sale = $this->sellCod();
        $this->store->run(function (): void {
            $note = DeliveryNote::query()->with('items')->sole();
            $service = app(DeliveryNoteService::class);
            $service->ship($note);
            $service->complete($note, [['item_id' => $note->items[0]->id, 'quantity_delivered' => 8, 'quantity_returned' => 2]]);
        });
        $this->assertSame(DeliveryNoteStatus::PartiallyDelivered, $this->store->run(fn (): DeliveryNoteStatus => DeliveryNote::query()->sole()->status));

        $this->actingAs($this->admin)->getJson($this->url('cod.settlements'))
            ->assertJsonPath('data.0.number', $sale->number)
            ->assertJsonPath('data.0.has_unreturned_goods', true)
            ->assertJsonPath('data.0.amount_due', '100000.00');

        $this->returnBrushes($sale, 2);

        $this->actingAs($this->admin)->getJson($this->url('cod.settlements'))
            ->assertJsonPath('data.0.has_unreturned_goods', false)
            ->assertJsonPath('data.0.amount_due', '80000.00');
    }

    public function test_the_day_closing_figures_hold_what_was_received_per_method(): void
    {
        $first = $this->sellDeliveredCod();
        $second = $this->sellDeliveredCod();
        $this->grant($this->cashier, Permission::CodReceive);
        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $first->id]), $this->receiveBody())->assertOk();
        $this->actingAs($this->admin)->postJson($this->url('cod.settle', ['sale' => $second->id]), $this->receiveBody(['method' => 'qris']))->assertOk();

        $figures = $this->store->run(fn (): array => app(DailyClosingService::class)->summarize(today()));
        $checklist = $this->store->run(fn (): array => app(DailyClosingService::class)->checklist(today()));

        $this->assertSame(['100000.00', '0.00', '100000.00', '0.00'], [$figures['cod_received_cash'], $figures['cod_received_transfer'], $figures['cod_received_qris'], $figures['cod_received_edc']]);
        $this->assertSame(0, $checklist['unsettled_cod_count']);
    }

    public function test_the_checklist_reminds_of_delivered_cod_whose_money_is_not_received_yet_without_holding_the_day(): void
    {
        $this->sellDeliveredCod();

        $checklist = $this->store->run(fn (): array => app(DailyClosingService::class)->checklist(today()));

        $this->assertSame(1, $checklist['unsettled_cod_count']);
        $this->assertSame([], $checklist['pending_cod_approvals']);
    }

    public function test_the_receipt_page_shows_the_money_received_and_an_admin_can_approve_it_there(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->grant($this->cashier, Permission::CodReceive);
        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody(['amount' => 90000, 'difference_reason' => 'Kurang']))->assertOk();
        $approveUrl = route('tenant.sales.cod-settlement.approve', ['tenant' => $this->store->code, 'sale' => $sale->id]);

        $this->actingAs($this->admin)->get(route('tenant.sales.show', ['tenant' => $this->store->code, 'sale' => $sale->id]))
            ->assertInertia(fn ($page) => $page
                ->where('sale.cod_settlement.status', 'awaiting_approval')
                ->where('sale.cod_settlement.amount', '90000.00')
                ->where('sale.cod_settlement.approve_url', $approveUrl));

        $this->actingAs($this->admin)->post($approveUrl)->assertSessionHas('success', 'Penerimaan uang COD disetujui.');
        $this->assertSame(CodSettlementStatus::Confirmed, $this->settlementOf($sale)->status);
    }

    public function test_the_dashboard_lists_the_money_waiting_for_an_approval(): void
    {
        $sale = $this->sellDeliveredCod();
        $this->grant($this->cashier, Permission::CodReceive);
        $this->actingAs($this->cashier)->postJson($this->url('cod.settle', ['sale' => $sale->id]), $this->receiveBody(['amount' => 90000, 'difference_reason' => 'Kurang']))->assertOk();

        $this->actingAs($this->admin)->get(route('tenant.dashboard', ['tenant' => $this->store->code]))
            ->assertInertia(fn ($page) => $page
                ->where('summary.pending.cod_approvals', 1)
                ->where('summary.pending.total', 1)
                ->where('summary.approvals.0', [
                    'kind' => 'cod_settlement',
                    'reference' => $sale->number,
                    'amount' => '90000.00',
                    'show_url' => route('tenant.sales.show', ['tenant' => $this->store->code, 'sale' => $sale->id]),
                ]));
    }

    public function test_the_default_way_to_refund_never_is_cod(): void
    {
        $sale = $this->sellDeliveredCod();

        $method = $this->store->run(fn () => app(SaleReturnService::class)->defaultRefundMethod($sale->load('payments')));

        $this->assertSame('cash', $method->value);
    }
}
