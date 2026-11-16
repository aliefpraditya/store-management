<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\BarcodeLabelSheetRequest;
use App\Http\Requests\Tenant\PickBarcodeLabelProductsRequest;
use App\Services\BarcodeLabelService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Printing barcode stickers (the SKU as a barcode) for the items picked on the item list, for admins. It has no page of its
 * own: the item list opens a window to set how many stickers each item gets, and the sheet prints from a window of its own.
 */
class BarcodeLabelController extends Controller
{
    public function __construct(private readonly BarcodeLabelService $barcodeLabels) {}

    public function products(PickBarcodeLabelProductsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->barcodeLabels->pickableProducts($request->productIds())]);
    }

    public function sheet(BarcodeLabelSheetRequest $request): Response
    {
        return Inertia::render('Tenant/Print/BarcodeLabels', [
            'sheet' => [
                'labels' => $this->barcodeLabels->labels($request->copiesByProductId()),
                'width_mm' => $request->widthMm(),
                'height_mm' => $request->heightMm(),
                'columns' => $request->columns(),
            ],
        ]);
    }
}
