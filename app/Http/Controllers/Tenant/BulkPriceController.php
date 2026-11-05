<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CostChangeMethod;
use App\Enums\ProductStatusFilter;
use App\Enums\SellingChangeMethod;
use App\Exceptions\BulkPriceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\BulkPriceRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use App\Models\Unit;
use App\Services\BulkPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Changing the modal and the selling price of many items at once, for admins: pick the items, preview, apply.
 */
class BulkPriceController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private readonly BulkPriceService $bulkPrices) {}

    public function index(Request $request): Response
    {
        /** @var Store $store */
        $store = tenant();
        $search = $request->string('search')->toString();
        $categoryId = $request->integer('category') ?: null;
        $brandId = $request->integer('brand') ?: null;
        $status = ProductStatusFilter::tryFrom($request->string('status')->toString());

        $products = $this->bulkPrices->paginateCandidates($search, $categoryId, $brandId, $status, self::PER_PAGE);

        return Inertia::render('Tenant/Products/BulkPrice', [
            'products' => $products->through(fn (Product $product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->category->name,
                'brand' => $product->brand?->name,
                'is_active' => $product->is_active,
                'units' => $product->productUnits->map(fn (ProductUnit $unit): array => [
                    'code' => $unit->unit_code,
                    'cost_price' => $unit->cost_price,
                    'selling_price' => $unit->selling_price,
                    'is_sellable' => $unit->is_sellable,
                ])->values()->all(),
            ]),
            'matching_ids' => $this->bulkPrices->matchingIds($search, $categoryId, $brandId, $status),
            'filters' => ['search' => $search, 'category' => $categoryId, 'brand' => $brandId, 'status' => $status?->value ?? ''],
            'statuses' => ProductStatusFilter::options(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->orderBy('code')->pluck('code'),
            'cost_methods' => collect(CostChangeMethod::cases())->map(fn (CostChangeMethod $method): array => ['value' => $method->value, 'label' => $method->label()])->all(),
            'selling_methods' => collect(SellingChangeMethod::cases())->map(fn (SellingChangeMethod $method): array => ['value' => $method->value, 'label' => $method->label(), 'takes_value' => $method->takesValue()])->all(),
            'max_products' => BulkPriceService::MAX_PRODUCTS,
            'urls' => [
                'preview' => route('tenant.bulk-prices.preview', ['tenant' => $store->code]),
                'apply' => route('tenant.bulk-prices.apply', ['tenant' => $store->code]),
                'products' => route('tenant.products.index', ['tenant' => $store->code]),
            ],
        ]);
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

    public function apply(BulkPriceRequest $request): RedirectResponse
    {
        try {
            $totals = $this->bulkPrices->apply($request->productIds(), $request->change());
        } catch (BulkPriceException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $skippedNote = $totals['units_skipped'] > 0 ? " {$totals['units_skipped']} satuan dilewati." : '';

        return back()->with('success', "Harga {$totals['products_changed']} barang diubah ({$totals['units_changed']} satuan).".$skippedNote);
    }
}
