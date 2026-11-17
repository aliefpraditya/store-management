<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\PickPriceLabelProductsRequest;
use App\Http\Requests\Tenant\PriceLabelSheetRequest;
use App\Services\PriceLabelService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Printing price labels (item name, price and the day printed) for the items picked on the item list, for admins. It has no
 * page of its own: the item list opens a window to set how many labels each unit gets, and the sheet prints from a frame.
 */
class PriceLabelController extends Controller
{
    public function __construct(private readonly PriceLabelService $priceLabels) {}

    public function units(PickPriceLabelProductsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->priceLabels->pickableUnits($request->productIds())]);
    }

    public function sheet(PriceLabelSheetRequest $request): Response
    {
        return Inertia::render('Tenant/Print/PriceLabels', [
            'sheet' => [
                'labels' => $this->priceLabels->labels($request->copiesByProductUnitId()),
                'printed_on' => $this->priceLabels->printedOn(),
                'width_mm' => $request->widthMm(),
                'height_mm' => $request->heightMm(),
                'columns' => $request->columns(),
            ],
        ]);
    }
}
