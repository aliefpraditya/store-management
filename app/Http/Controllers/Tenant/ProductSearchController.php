<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Type-ahead lookup for product pickers (opname, stock history).
 */
class ProductSearchController extends Controller
{
    private const RESULT_LIMIT = 15;

    public function __invoke(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with('baseUnit')
            ->search($request->string('q')->toString())
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get();

        return response()->json([
            'data' => $products->map(fn (Product $product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'base_unit' => $product->baseUnit->code,
            ])->values(),
        ]);
    }
}
