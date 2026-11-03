<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\CodSaleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\VoidSaleRequest;
use App\Http\Resources\CodSaleResource;
use App\Models\Sale;
use App\Services\CodSaleService;
use App\Services\SaleVoidService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * COD sales from the till: a cashier sees the COD sales still waiting for their delivery, from any day, marks one as
 * delivered, or voids one when the customer did not take it. A receipt is found here rather than by its shift, since
 * a COD sale is often settled the day after it was made.
 */
class CashierCodController extends Controller
{
    private const LIST_LIMIT = 50;

    public function __construct(
        private readonly CodSaleService $codSales,
        private readonly SaleVoidService $voids,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => CodSaleResource::collection($this->codSales->undeliveredSales(self::LIST_LIMIT))->resolve()]);
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
        return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
