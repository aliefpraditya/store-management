<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\BulkPriceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ApplyBulkPriceRequest;
use App\Http\Requests\Tenant\BulkPriceRequest;
use App\Http\Requests\Tenant\PickBulkPriceProductsRequest;
use App\Services\BulkPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Changing the modal and the selling price of the items picked on the item list, for admins: preview, then apply. It has no page of its own; the item list opens a window for it.
 */
class BulkPriceController extends Controller
{
    public function __construct(private readonly BulkPriceService $bulkPrices) {}

    public function units(PickBulkPriceProductsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->bulkPrices->unitsOf($request->productIds())]);
    }

    public function preview(BulkPriceRequest $request): JsonResponse
    {
        try {
            $preview = $this->bulkPrices->preview($request->productIds(), $request->change());
        } catch (BulkPriceException $exception) {
            return response()->json(['message' => $exception->getMessage()], HttpResponse::HTTP_CONFLICT);
        }

        return response()->json(['data' => $preview]);
    }

    public function apply(ApplyBulkPriceRequest $request): RedirectResponse
    {
        try {
            $totals = $this->bulkPrices->apply($request->productIds(), $request->change(), $request->previewFingerprint());
        } catch (BulkPriceException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $skippedNote = $totals['units_skipped'] > 0 ? " {$totals['units_skipped']} satuan dilewati." : '';

        return back()->with('success', "Harga {$totals['products_changed']} barang diubah ({$totals['units_changed']} satuan).".$skippedNote);
    }
}
