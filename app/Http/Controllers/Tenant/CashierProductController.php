<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Services\CashierCatalogService;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Item lookup for the cashier screen, scoped to the warehouse of the open shift of whoever is asking.
 */
class CashierProductController extends Controller
{
    public function __invoke(Request $request, ShiftService $shifts, CashierCatalogService $catalog): JsonResponse
    {
        $shift = $shifts->openShiftOf($request->user());

        if ($shift === null) {
            return response()->json([
                'code' => ApiErrorCode::ShiftNotOpen->value,
                'message' => 'Buka shift dulu sebelum mencari barang.',
                'more_info' => 'Pencarian barang kasir memakai gudang dari shift yang sedang terbuka.',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json(['data' => $catalog->search($shift->register->warehouse_id, $request->string('q')->toString())]);
    }
}
