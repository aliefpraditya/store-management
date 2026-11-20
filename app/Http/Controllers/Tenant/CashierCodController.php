<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ApiErrorCode;
use App\Exceptions\CodSaleException;
use App\Http\Controllers\Concerns\AnswersJsonErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ReceiveCodSettlementRequest;
use App\Http\Requests\Tenant\VoidSaleRequest;
use App\Http\Resources\CodSaleResource;
use App\Http\Resources\CodSettlementQueueResource;
use App\Models\Sale;
use App\Services\CodSaleService;
use App\Services\CodSettlementService;
use App\Services\SaleVoidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COD sales from the till: a cashier sees the COD sales still waiting for their delivery, from any day, marks one as
 * delivered, or voids one when the customer did not take it. A receipt is found here rather than by its shift, since
 * a COD sale is often settled the day after it was made. Any cashier of the store may void one, by the owner's choice
 * (see 7.5 of the analysis); the voided receipt keeps who did it and why.
 *
 * Once the goods are delivered, the money is received here too, by whoever holds the permission for it, and a
 * difference in it is approved by whoever holds the permission for that.
 */
class CashierCodController extends Controller
{
    use AnswersJsonErrors;

    private const LIST_LIMIT = 50;

    public function __construct(
        private readonly CodSaleService $codSales,
        private readonly SaleVoidService $voids,
        private readonly CodSettlementService $settlements,
    ) {}

    /**
     * The newest COD sales waiting for delivery, and how many wait in all, so the dialog can say when it shows only some.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CodSaleResource::collection($this->codSales->undeliveredSales(self::LIST_LIMIT))->resolve(),
            'meta' => ['total' => $this->codSales->undeliveredCount()],
        ]);
    }

    /**
     * The delivered COD sales whose money is yet to be received or approved, and how many wait in all.
     */
    public function settlements(): JsonResponse
    {
        return response()->json([
            'data' => CodSettlementQueueResource::collection($this->settlements->settlementQueue(self::LIST_LIMIT))->resolve(),
            'meta' => [
                'total' => $this->settlements->settlementQueueCount(),
                'awaiting_approval_total' => $this->settlements->awaitingApprovalCount(),
            ],
        ]);
    }

    public function settle(ReceiveCodSettlementRequest $request, Sale $sale): JsonResponse
    {
        try {
            $settlement = $this->settlements->receive($sale, $request->user(), $request->validated());
        } catch (CodSaleException $exception) {
            return $this->refused($exception);
        }

        return response()->json(['data' => ['number' => $sale->number, 'state' => $settlement->status->value]]);
    }

    public function approve(Request $request, Sale $sale): JsonResponse
    {
        try {
            $this->settlements->approve($sale, $request->user());
        } catch (CodSaleException $exception) {
            return $this->refused($exception);
        }

        return response()->json(['data' => ['number' => $sale->number]]);
    }

    public function delivered(Sale $sale): JsonResponse
    {
        try {
            $this->codSales->markDelivered($sale);
        } catch (CodSaleException $exception) {
            return $this->refused($exception);
        }

        return response()->json(['data' => ['number' => $sale->number]]);
    }

    public function void(VoidSaleRequest $request, Sale $sale): JsonResponse
    {
        try {
            $this->voids->void($sale, $request->user(), $request->validated('reason'), $request->validated('refund_account'));
        } catch (CodSaleException $exception) {
            return $this->refused($exception);
        }

        return response()->json(['data' => ['number' => $sale->number]]);
    }

    private function refused(CodSaleException $exception): JsonResponse
    {
        return $this->errorResponse(ApiErrorCode::CodRefused, $exception->getMessage(), 'Muat ulang daftar COD untuk melihat keadaan nota yang terbaru, lalu coba lagi.', Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
