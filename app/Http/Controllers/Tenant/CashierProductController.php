<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ApiErrorCode;
use App\Exceptions\ShiftException;
use App\Http\Controllers\Controller;
use App\Services\CashierCatalogService;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Item lookup for the cashier screen, scoped to the warehouse of the open shift of whoever is asking: a search by name,\n * SKU or barcode, or the current state of the units already in the cart (`?units[]=`).
 */
class CashierProductController extends Controller
{
    public function __invoke(Request $request, ShiftService $shiftService, CashierCatalogService $catalog): JsonResponse
    {
        $shift = $shiftService->openShiftOf($request->user());

        if ($shift === null) {
            return response()->json([
                'code' => ApiErrorCode::ShiftNotOpen->value,
                'message' => 'Buka shift dulu sebelum mencari barang. '.ShiftException::AWAITING_REVIEW_HINT,
                'more_info' => 'Pencarian barang kasir memakai gudang dari shift yang sedang terbuka.',
            ], Response::HTTP_CONFLICT);
        }

        $warehouseId = $shift->register->warehouse_id;

        // Units asked for by id are the cart being brought up to date; otherwise it is a search.
        if ($request->has('units')) {
            return response()->json(['data' => $catalog->forUnits($warehouseId, array_map('intval', (array) $request->input('units')))]);
        }

        return response()->json(['data' => $catalog->search($warehouseId, $request->string('q')->toString())]);
    }
}
