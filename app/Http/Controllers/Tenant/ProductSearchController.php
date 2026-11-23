<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Type-ahead lookup for product pickers (opname, stock history).
 */
class ProductSearchController extends Controller
{
    public function __invoke(Request $request, ProductService $productService): JsonResponse
    {
        $products = $productService->searchForPicker($request->string('q')->toString());

        return response()->json([
            'data' => $products->map(fn (Product $product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'brand' => $product->brand?->name,
                'base_unit' => $product->baseUnit->code,
                'units' => $product->productUnits->pluck('unit_code')->values(),
            ])->values(),
        ]);
    }
}
