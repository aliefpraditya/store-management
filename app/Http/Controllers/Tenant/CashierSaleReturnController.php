<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ApiErrorCode;
use App\Enums\PaymentMethod;
use App\Enums\ReturnDisposition;
use App\Enums\ReturnResolution;
use App\Enums\SaleStatus;
use App\Exceptions\SaleReturnException;
use App\Http\Controllers\Concerns\AnswersJsonErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveCashierSaleReturnRequest;
use App\Http\Resources\PrintableSaleReturnResource;
use App\Http\Resources\SaleReturnResource;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Store;
use App\Services\SaleReturnService;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns from the till: a cashier asks for a return on a receipt the customer brings, found by its number, without
 * leaving the cashier screen, and it waits for an admin's approval. An admin using the till is approved at once. Deciding and listing returns
 * stays with the admin pages.
 *
 * The load and save addresses of a receipt are signed (see {@see SaleReturnService::signedLinksFor()}), so a cashier
 * cannot reach receipts by guessing their ids.
 */
class CashierSaleReturnController extends Controller
{
    use AnswersJsonErrors;

    public function __construct(
        private readonly SaleReturnService $returnService,
        private readonly ShiftService $shiftService,
    ) {}

    /**
     * Finds the receipt a customer brings back by its full number. Only an exact number finds anything, so the till
     * can start a return for any receipt of the store without being able to browse the old ones.
     */
    public function lookup(Request $request): JsonResponse
    {
        $number = $request->string('number')->trim()->toString();

        $sale = $number === '' ? null : Sale::query()->where('number', $number)->first();

        if ($sale === null) {
            return $this->errorResponse(ApiErrorCode::ReceiptNotFound, 'Nota tidak ditemukan. Ketik nomor nota selengkapnya.', 'Nota hanya ditemukan lewat nomor yang lengkap dan persis.', Response::HTTP_NOT_FOUND);
        }

        if ($sale->status !== SaleStatus::Completed) {
            return $this->refusedResponse(SaleReturnException::saleNotCompleted());
        }

        return response()->json(['data' => [
            'number' => $sale->number,
            ...$this->returnService->signedLinksFor($sale),
        ]]);
    }

    /**
     * What the dialog needs for one receipt: the lines with what can still be returned, the ways to pay back and
     * the goods destinations, and the returns already asked for. Any receipt of the store may be returned from the
     * till; an admin decides every return a cashier asks for.
     */
    public function show(Request $request, Sale $sale): JsonResponse
    {
        if ($sale->status !== SaleStatus::Completed) {
            return $this->refusedResponse(SaleReturnException::saleNotCompleted());
        }

        $sale->load(['items', 'payments']);

        return response()->json(['data' => [
            'number' => $sale->number,
            'customer_name' => $sale->customer_name,
            'default_refund_method' => $this->returnService->defaultRefundMethod($sale)->value,
            'is_approved_at_once' => $this->isAdmin($request),
            'refund_methods' => PaymentMethod::cashierOptions(),
            'dispositions' => ReturnDisposition::options(),
            'resolutions' => ReturnResolution::options(),
            'lines' => $this->returnService->returnableLines($sale),
            'returns' => SaleReturn::query()
                ->with(['sale', 'cashShift', 'items.saleItem'])
                ->where('sale_id', $sale->id)
                ->orderBy('id')
                ->get()
                ->map(fn (SaleReturn $saleReturn): array => $this->returnSummary($saleReturn))
                ->all(),
        ]]);
    }

    public function store(SaveCashierSaleReturnRequest $request, Sale $sale): JsonResponse
    {
        $shift = $this->shiftService->openShiftOf($request->user());

        if ($shift === null) {
            return $this->errorResponse(ApiErrorCode::ShiftNotOpen, SaleReturnException::shiftNotOpen()->getMessage(), 'Retur dicatat di shift yang sedang terbuka. Buka shift di kasir.', Response::HTTP_CONFLICT);
        }

        try {
            $saleReturn = $this->isAdmin($request)
                ? $this->returnService->createApproved($sale, $shift, $request->user(), $request->validated())
                : $this->returnService->create($sale, $shift, $request->user(), $request->validated());
        } catch (SaleReturnException $exception) {
            return $this->refusedResponse($exception);
        }

        return response()->json(['data' => $this->returnSummary($saleReturn->load(['sale', 'cashShift', 'items.saleItem']))], Response::HTTP_CREATED);
    }

    /**
     * The return slip, opened in its own window and printed there. A cashier prints the returns they asked for.
     */
    public function sheet(Request $request, SaleReturn $saleReturn): InertiaResponse
    {
        abort_unless($this->isAdmin($request) || $saleReturn->requested_by === $request->user()->id, 403);

        return Inertia::render('Tenant/Print/SaleReturn', [
            'saleReturn' => PrintableSaleReturnResource::make($saleReturn->load(['sale', 'cashShift', 'items.saleItem']))->resolve(),
        ]);
    }

    private function isAdmin(Request $request): bool
    {
        /** @var Store $store */
        $store = tenant();

        return $request->user()->isAdminIn($store);
    }

    /**
     * Without the refund account: the till shows who asked for what, and the customer's bank details are only for
     * the slip of the cashier's own return and for admins.
     *
     * @return array<string, mixed>
     */
    private function returnSummary(SaleReturn $saleReturn): array
    {
        return [
            ...Arr::except(SaleReturnResource::make($saleReturn)->resolve(), ['refund_account']),
            'sheet_url' => route('tenant.cashier.returns.sheet', ['tenant' => tenant('code'), 'sale_return' => $saleReturn->id]),
        ];
    }

    private function refusedResponse(SaleReturnException $exception): JsonResponse
    {
        return $this->errorResponse(ApiErrorCode::ReturnRefused, $exception->getMessage(), 'Perbaiki isian retur sesuai pesan ini, lalu coba lagi.', Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
