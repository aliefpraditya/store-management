<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ApiErrorCode;
use App\Enums\Permission;
use App\Exceptions\ShiftException;
use App\Http\Controllers\Concerns\AnswersPrints;
use App\Http\Controllers\Controller;
use App\Http\Resources\SaleDetailResource;
use App\Models\Sale;
use App\Models\Store;
use App\Services\PrintCounter;
use App\Services\ReceiptSheetService;
use App\Services\SaleHistoryService;
use App\Services\SaleReturnService;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        private readonly ReceiptSheetService $receiptSheets,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $shift = $this->shiftService->openShiftOf($request->user());

        if ($shift === null) {
            return response()->json([
                'code' => ApiErrorCode::ShiftNotOpen->value,
                'message' => 'Buka shift dulu untuk melihat nota shift ini. '.ShiftException::AWAITING_REVIEW_HINT,
                'more_info' => 'Daftar ini berisi nota dari shift yang sedang terbuka.',
            ], Response::HTTP_CONFLICT);
        }

        /** @var Store $store */
        $store = tenant();
        $isAdmin = $request->user()->hasPermissionIn($store, Permission::SalesView);
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

        return Inertia::render('Tenant/Print/Receipt', [
            'receipt' => $this->receiptSheets->forSale($sale, $this->printNumberOf($request) > 1),
        ]);
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
            'delivery_mode_url' => route('tenant.cashier.receipts.delivery-mode', ['tenant' => tenant('code'), 'sale' => $sale->id]),
            'print_url' => route('tenant.cashier.receipts.print', ['tenant' => tenant('code'), 'sale' => $sale->id]),
            ...$this->returnService->signedLinksFor($sale),
        ];
    }
}
