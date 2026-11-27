<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\SaleStatus;
use App\Exceptions\CodSaleException;
use App\Exceptions\DeliveryNoteException;
use App\Http\Controllers\Concerns\AnswersPrints;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureUserCanAccessStore;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Http\Requests\Tenant\ReceiveCodSettlementRequest;
use App\Http\Requests\Tenant\VoidSaleRequest;
use App\Http\Resources\SaleDetailResource;
use App\Http\Resources\SaleHistoryRowResource;
use App\Models\DeliveryNote;
use App\Models\Sale;
use App\Models\Store;
use App\Services\CodSettlementService;
use App\Services\DeliveryNoteService;
use App\Services\PrintCounter;
use App\Services\ReceiptSheetService;
use App\Services\SaleHistoryService;
use App\Services\SaleVoidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The receipt history of the whole store, for admins only. A cashier looks up receipts of their running shift in
 * the dialog on the cashier screen instead.
 */
class SaleHistoryController extends Controller
{
    use AnswersPrints;

    private const PER_PAGE = 25;

    public function __construct(
        private readonly SaleHistoryService $saleHistoryService,
        private readonly SaleVoidService $saleVoids,
        private readonly CodSettlementService $codSettlements,
        private readonly DeliveryNoteService $deliveryNotes,
        private readonly PrintCounter $printCounter,
        private readonly ReceiptSheetService $receiptSheets,
    ) {}

    public function index(DateRangeRequest $request): Response
    {
        $filters = [
            'from' => $request->has('from') ? $request->fromDate()?->toDateString() : today()->toDateString(),
            'to' => $request->has('to') ? $request->toDate()?->toDateString() : today()->toDateString(),
            'search' => $request->string('search')->trim()->toString() ?: null,
            'cashier_id' => $request->integer('cashier') ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'delivery' => $request->string('delivery')->toString() ?: null,
        ];

        return Inertia::render('Tenant/Sales/Index', [
            'sales' => $this->saleHistoryService->paginate($filters, self::PER_PAGE)
                ->through(fn (Sale $sale): array => SaleHistoryRowResource::make($sale)->resolve()),
            'totals' => $this->saleHistoryService->totals($filters),
            'filters' => $filters,
            'cashiers' => $this->saleHistoryService->cashiers(),
            'statuses' => collect(SaleStatus::cases())->map(fn (SaleStatus $status): array => ['value' => $status->value, 'label' => $status->label()])->all(),
        ]);
    }

    public function show(Request $request, Sale $sale): Response
    {
        /** @var Store $store */
        $store = tenant();

        $sale->load(['items', 'payments', 'register', 'deliveryNotes', 'codSettlement', 'codAdvancePayments']);

        return Inertia::render('Tenant/Sales/Show', [
            'sale' => SaleDetailResource::make($sale)->resolve(),
            'void_blocker' => $sale->isCod() ? $this->saleVoids->voidBlocker($sale)?->getMessage() : null,
            'cod_advance' => $this->codAdvanceOf($request, $sale),
            'urls' => [
                'index' => route('tenant.sales.index', ['tenant' => $store->code]),
                'new_delivery_note' => route('tenant.delivery-notes.create', ['tenant' => $store->code, 'sale' => $sale->id]),
                'delivery_mode' => route('tenant.sales.delivery-mode', ['tenant' => $store->code, 'sale' => $sale->id]),
                'new_return' => route('tenant.returns.create', ['tenant' => $store->code, 'sale' => $sale->id]),
                'void' => route('tenant.sales.void', ['tenant' => $store->code, 'sale' => $sale->id]),
                'print' => route('tenant.sales.print', ['tenant' => $store->code, 'sale' => $sale->id]),
            ],
        ]);
    }

    /**
     * Notes one more print of a receipt and answers the signed address of its sheet. Whoever may see the history may print
     * any receipt in it, whichever day it was made and whoever sold it.
     */
    public function print(Sale $sale): JsonResponse
    {
        return $this->recordPrintAnswer($this->printCounter, $sale, 'tenant.sales.sheet', 'sale');
    }

    /**
     * The printable receipt, opened in the hidden frame that prints it. From the second print on it says SALINAN.
     */
    public function sheet(Request $request, Sale $sale): Response
    {
        return Inertia::render('Tenant/Print/Receipt', [
            'receipt' => $this->receiptSheets->forSale($sale, $this->printNumberOf($request) > 1),
        ]);
    }

    /**
     * Voids a COD sale, whichever day it was made.
     */
    public function void(VoidSaleRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $this->saleVoids->void($sale, $request->user(), $request->validated('reason'), $request->validated('refund_account'));
        } catch (CodSaleException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Nota di-void. Barangnya kembali ke stok.');
    }

    /**
     * Marks a receipt taken at the store as delivered after all, so its delivery note can be made.
     */
    public function changeToDelivery(Sale $sale): RedirectResponse
    {
        try {
            $this->deliveryNotes->changeToDelivery($sale);
        } catch (DeliveryNoteException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Nota diubah menjadi diantar. Buat surat jalannya.');
    }

    /**
     * Takes part of the bill of a COD sale before its goods are delivered. What is left is still collected on delivery.
     */
    public function receiveCodAdvance(ReceiveCodSettlementRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $this->codSettlements->receiveAdvance($sale, $request->user(), $request->validated());
        } catch (CodSaleException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pembayaran di muka dicatat. Sisanya ditagih saat barang diantar.');
    }

    /**
     * Approves the difference in the money that was received for a COD sale.
     */
    public function approveCodSettlement(Request $request, Sale $sale): RedirectResponse
    {
        try {
            $this->codSettlements->approve($sale, $request->user());
        } catch (CodSaleException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Penerimaan uang COD disetujui.');
    }

    /**
     * What the receipt page needs to take part of the bill in advance: how much of it is left and where to send the payment.
     * Null when no advance can be taken (not a COD sale, not completed, already delivered, nothing left) or the user may
     * not receive money.
     *
     * @return array{amount_due: string, methods: list<array<string, string>>, receive_url: string}|null
     */
    private function codAdvanceOf(Request $request, Sale $sale): ?array
    {
        $isDelivered = $sale->deliveryNotes->contains(fn (DeliveryNote $note): bool => $note->status->hasDeliveredGoods());

        if ($sale->status !== SaleStatus::Completed || ! $sale->isCod() || $isDelivered || ! EnsureUserCanAccessStore::grants($request, Permission::CodReceive)) {
            return null;
        }

        $remaining = $this->codSettlements->amountDueOf($sale);

        if ($remaining->isZero()) {
            return null;
        }

        return [
            'amount_due' => (string) $remaining->toScale(2),
            'methods' => PaymentMethod::cashierOptions(),
            'receive_url' => route('tenant.sales.cod-advance', ['tenant' => tenant('code'), 'sale' => $sale->id]),
        ];
    }
}
