<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ApiErrorCode;
use App\Enums\OrderStatus;
use App\Http\Controllers\Concerns\AnswersPrints;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerOrderResource;
use App\Http\Resources\SaleDetailResource;
use App\Models\CustomerOrder;
use App\Models\Sale;
use App\Models\Store;
use App\Services\PrintCounter;
use App\Services\SaleHistoryService;
use App\Services\SaleReturnService;
use App\Services\ShiftService;
use App\Services\StoreSettingService;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The receipts of the cashier's running shift, for the till's receipt dialog, and printing them again.
 */
class ShiftReceiptController extends Controller
{
    use AnswersPrints;

    private const RESULT_LIMIT = 50;

    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly SaleHistoryService $saleHistoryService,
        private readonly PrintCounter $printCounter,
        private readonly SaleReturnService $returnService,
        private readonly StoreSettingService $settings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $shift = $this->shiftService->openShiftOf($request->user());

        if ($shift === null) {
            return response()->json([
                'code' => ApiErrorCode::ShiftNotOpen->value,
                'message' => 'Buka shift dulu untuk melihat nota shift ini.',
                'more_info' => 'Daftar ini berisi nota dari shift yang sedang terbuka.',
            ], Response::HTTP_CONFLICT);
        }

        /** @var Store $store */
        $store = tenant();
        $isAdmin = $request->user()->isAdminIn($store);
        $sales = $this->saleHistoryService->ofShift($shift, $request->string('q')->toString(), self::RESULT_LIMIT);

        return response()->json(['data' => $sales->map(fn (Sale $sale): array => $this->withLinks(SaleDetailResource::make($sale)->resolve(), $sale, $isAdmin))->all()]);
    }

    /**
     * Notes one more print and answers the sheet to open in a new window. A cashier may print their own receipts, an
     * admin any.
     */
    public function print(Request $request, Sale $sale): JsonResponse
    {
        if (! $this->canHandle($request, $sale)) {
            return response()->json([
                'code' => ApiErrorCode::ReceiptNotYours->value,
                'message' => 'Nota ini bukan nota Anda, jadi tidak bisa dicetak ulang.',
                'more_info' => 'Kasir hanya bisa mencetak nota yang dibuatnya sendiri. Minta Admin kalau perlu mencetak nota orang lain.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->recordPrintAnswer($this->printCounter, $sale, 'tenant.cashier.receipts.sheet', 'sale');
    }

    /**
     * The printable receipt, opened in its own window and printed there. From the second print on it says SALINAN.
     */
    public function sheet(Request $request, Sale $sale): InertiaResponse
    {
        abort_unless($this->canHandle($request, $sale), Response::HTTP_FORBIDDEN);

        $receipt = SaleDetailResource::make($sale->load(['items', 'payments', 'register']))->resolve();
        $order = $this->orderMadeWith($sale);

        return Inertia::render('Tenant/Print/Receipt', [
            'receipt' => [
                ...$receipt,
                'payments' => $order === null ? $receipt['payments'] : $this->paymentsWithDeposit($receipt['payments'], $order),
                'order' => $order === null ? null : CustomerOrderResource::make($order)->resolve(),
                'is_copy' => $this->printNumberOf($request) > 1,
                'store' => $this->settings->letterhead(),
                'footer' => $this->settings->receiptFooter(),
                'paper_width' => $this->settings->paperWidth(),
            ],
        ]);
    }

    /**
     * The customer order made in the same checkout as the receipt, unless it was cancelled. It is printed on the receipt
     * itself, so the customer holds one paper for what was paid and what was ordered.
     */
    private function orderMadeWith(Sale $sale): ?CustomerOrder
    {
        return CustomerOrder::query()
            ->with(['items', 'payments'])
            ->where('origin_sale_id', $sale->id)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->first();
    }

    /**
     * What the customer handed over for the receipt and the order's deposit together, one line per method, so the paper
     * adds up: payments minus the change is the total plus the deposit.
     *
     * @param  list<array{method: string, amount: string, reference: string|null}>  $receiptPayments
     * @return list<array{method: string, amount: string, reference: string|null}>
     */
    private function paymentsWithDeposit(array $receiptPayments, CustomerOrder $order): array
    {
        return collect([...$receiptPayments, ...CustomerOrderResource::depositPaymentsOf($order)])
            ->groupBy('method')
            ->map(fn (Collection $payments, string $method): array => [
                'method' => $method,
                'amount' => (string) $payments->reduce(fn (BigDecimal $sum, array $payment): BigDecimal => $sum->plus($payment['amount']), BigDecimal::zero())->toScale(2),
                'reference' => $payments->pluck('reference')->filter()->first(),
            ])
            ->values()
            ->all();
    }

    private function canHandle(Request $request, Sale $sale): bool
    {
        /** @var Store $store */
        $store = tenant();

        return $request->user()->canHandleReceipt($sale, $store);
    }

    /**
     * The full receipt page is for admins only, so a cashier gets no link to it. There is no address for the sheet: the
     * print step answers a signed one for the exact print it counted.
     *
     * @param  array<string, mixed>  $receipt
     * @return array<string, mixed>
     */
    private function withLinks(array $receipt, Sale $sale, bool $isAdmin): array
    {
        return [
            ...$receipt,
            'show_url' => $isAdmin ? route('tenant.sales.show', ['tenant' => tenant('code'), 'sale' => $sale->id]) : null,
            'delivery_url' => route('tenant.cashier.receipts.delivery', ['tenant' => tenant('code'), 'sale' => $sale->id]),
            'delivery_note_store_url' => route('tenant.cashier.receipts.delivery-notes.store', ['tenant' => tenant('code'), 'sale' => $sale->id]),
            'print_url' => route('tenant.cashier.receipts.print', ['tenant' => tenant('code'), 'sale' => $sale->id]),
            ...$this->returnService->signedLinksFor($sale),
        ];
    }
}
