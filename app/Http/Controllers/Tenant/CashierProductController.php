<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\CashierCatalogService;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Item lookup for the cashier screen, scoped to the warehouse of the open shift of whoever is asking.
 */
class CashierProductController extends Controller
{
    public function __invoke(Request $request, ShiftService $shifts, CashierCatalogService $catalog): JsonResponse
    {
        $shift = $shifts->openShiftOf($request->user());

        abort_if($shift === null, 409, 'Buka shift dulu.');

        return response()->json(['data' => $catalog->search($shift->register->warehouse_id, $request->string('q')->toString())]);
    }
}
