<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\PaymentMethod;
use App\Enums\ReturnDisposition;
use App\Enums\SaleStatus;
use App\Exceptions\SaleReturnException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveCashierSaleReturnRequest;
use App\Http\Resources\SaleReturnResource;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Store;
use App\Services\SaleReturnService;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns from the till: a cashier asks for a return on a receipt the customer brings, found by its number, without
 * leaving the cashier screen, and it waits for an admin's approval. An admin using the till is approved at once. Deciding and listing returns
 * stays with the admin pages.
 */
class CashierSaleReturnController extends Controller
{
    public function __construct(
        private readonly SaleReturnService $returns,
        private readonly ShiftService $shifts,
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
            return response()->json(['message' => 'Nota tidak ditemukan. Ketik nomor nota selengkapnya.'], Response::HTTP_NOT_FOUND);
        }

        if ($sale->status !== SaleStatus::Completed) {
            return response()->json(['message' => SaleReturnException::saleNotCompleted()->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => [
            'number' => $sale->number,
            'return_url' => route('tenant.cashier.receipts.return', ['tenant' => tenant('code'), 'sale' => $sale->id]),
            'return_store_url' => route('tenant.cashier.receipts.returns.store', ['tenant' => tenant('code'), 'sale' => $sale->id]),
        ]]);
    }

    /**
     * What the dialog needs for one receipt: the lines with what can still be returned, the ways to pay back and
     * the goods destinations, and the returns already asked for. Any receipt of the store may be returned from the
     * till; an admin decides every return a cashier asks for.
     */
    public function show(Request $request, Sale $sale): JsonResponse
    {
        $sale->load(['items', 'payments']);

        return response()->json(['data' => [
            'number' => $sale->number,
            'customer_name' => $sale->customer_name,
            'default_refund_method' => $this->returns->defaultRefundMethod($sale)->value,
            'is_approved_at_once' => $this->isAdmin($request),
            'refund_methods' => collect(PaymentMethod::forCashier())->map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()])->all(),
            'dispositions' => collect(ReturnDisposition::cases())->map(fn (ReturnDisposition $case): array => ['value' => $case->value, 'label' => $case->label()])->all(),
            'lines' => $this->returns->returnableLines($sale),
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
        $shift = $this->shifts->openShiftOf($request->user());

        if ($shift === null) {
            return response()->json(['message' => SaleReturnException::shiftNotOpen()->getMessage()], Response::HTTP_CONFLICT);
        }

        try {
            $saleReturn = $this->isAdmin($request)
                ? $this->returns->createApproved($sale, $shift, $request->user(), $request->validated())
                : $this->returns->create($sale, $shift, $request->user(), $request->validated());
        } catch (SaleReturnException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $this->returnSummary($saleReturn->load(['sale', 'cashShift', 'items.saleItem']))], Response::HTTP_CREATED);
    }

    /**
     * The return slip, opened in its own window and printed there. A cashier prints the returns they asked for.
     */
    public function sheet(Request $request, SaleReturn $saleReturn): InertiaResponse
    {
        abort_unless($this->isAdmin($request) || $saleReturn->requested_by === $request->user()->id, 403);

        /** @var Store $store */
        $store = tenant();

        return Inertia::render('Tenant/Print/SaleReturn', [
            'saleReturn' => [
                ...SaleReturnResource::make($saleReturn->load(['sale', 'cashShift', 'items.saleItem']))->resolve(),
                'store' => ['name' => $store->name, 'address' => $store->address, 'phone' => $store->phone],
            ],
        ]);
    }

    private function isAdmin(Request $request): bool
    {
        /** @var Store $store */
        $store = tenant();

        return $request->user()->isAdminIn($store);
    }

    /**
     * @return array<string, mixed>
     */
    private function returnSummary(SaleReturn $saleReturn): array
    {
        return [
            ...SaleReturnResource::make($saleReturn)->resolve(),
            'sheet_url' => route('tenant.cashier.returns.sheet', ['tenant' => tenant('code'), 'sale_return' => $saleReturn->id]),
        ];
    }
}
