<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\SaleDetailResource;
use App\Models\Sale;
use App\Models\Store;
use App\Services\PrintCounter;
use App\Services\SaleHistoryService;
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
    private const RESULT_LIMIT = 50;

    public function __construct(
        private readonly ShiftService $shifts,
        private readonly SaleHistoryService $history,
        private readonly PrintCounter $prints,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $shift = $this->shifts->openShiftOf($request->user());

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
        $sales = $this->history->ofShift($shift, $request->string('q')->toString(), self::RESULT_LIMIT);

        return response()->json(['data' => $sales->map(fn (Sale $sale): array => $this->withLinks(SaleDetailResource::make($sale)->resolve(), $sale, $isAdmin))->all()]);
    }

    /**
     * Notes one more print. A cashier may print their own receipts, an admin any. The browser then opens the sheet
     * in a new window, which shows the receipt as printed.
     */
    public function print(Request $request, Sale $sale): JsonResponse
    {
        $this->authorizeFor($request, $sale);

        $isCopy = $this->prints->record($sale);

        return response()->json(['data' => ['is_copy' => $isCopy, 'print_count' => $sale->refresh()->print_count]]);
    }

    /**
     * The printable receipt, opened in its own window and printed there. From the second print on it says SALINAN.
     */
    public function sheet(Request $request, Sale $sale): InertiaResponse
    {
        $this->authorizeFor($request, $sale);

        /** @var Store $store */
        $store = tenant();

        return Inertia::render('Tenant/Print/Receipt', [
            'receipt' => [
                ...SaleDetailResource::make($sale->load(['items', 'payments', 'register']))->resolve(),
                'is_copy' => $sale->print_count > 1,
                'store' => ['name' => $store->name, 'address' => $store->address, 'phone' => $store->phone],
            ],
        ]);
    }

    private function authorizeFor(Request $request, Sale $sale): void
    {
        /** @var Store $store */
        $store = tenant();

        abort_unless($request->user()->isAdminIn($store) || $sale->cashier_id === $request->user()->id, 403);
    }

    /**
     * The full receipt page is for admins only, so a cashier gets no link to it.
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
            'sheet_url' => route('tenant.cashier.receipts.sheet', ['tenant' => tenant('code'), 'sale' => $sale->id]),
            'return_url' => route('tenant.cashier.receipts.return', ['tenant' => tenant('code'), 'sale' => $sale->id]),
            'return_store_url' => route('tenant.cashier.receipts.returns.store', ['tenant' => tenant('code'), 'sale' => $sale->id]),
        ];
    }
}
