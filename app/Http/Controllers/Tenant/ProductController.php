<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CostChangeMethod;
use App\Enums\ProductConditionFilter;
use App\Enums\ProductSortColumn;
use App\Enums\ProductStatusFilter;
use App\Enums\SellingChangeMethod;
use App\Exceptions\ProductHasTransactionsException;
use App\Exceptions\ProductSaveException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\BarcodeLabelSheetRequest;
use App\Http\Requests\Tenant\SaveProductRequest;
use App\Http\Requests\Tenant\SetQuickAccessRequest;
use App\Http\Resources\ProductFormResource;
use App\Http\Resources\ProductListResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\BarcodeLabelService;
use App\Services\BulkPriceService;
use App\Services\PriceLabelService;
use App\Services\ProductService;
use App\Services\StoreSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly ProductService $productService,
        private readonly StoreSettingService $storeSettings,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $categoryId = $request->integer('category') ?: null;
        $brandId = $request->integer('brand') ?: null;
        $status = ProductStatusFilter::tryFrom($request->string('status')->toString());
        $condition = ProductConditionFilter::tryFrom($request->string('condition')->toString());
        $sort = ProductSortColumn::tryFrom($request->string('sort')->toString()) ?? ProductSortColumn::Name;
        $direction = $sort->directionOrDefault($request->string('direction')->toString());

        $products = $this->productService
            ->paginateProducts($search, $categoryId, $brandId, $status, $condition, $sort, $direction, self::PER_PAGE)
            ->through(fn (Product $product): array => ProductListResource::make($product)->resolve());

        return Inertia::render('Tenant/Products/Index', [
            'products' => $products,
            'matching_ids' => Inertia::optional(fn (): array => $this->productService->matchingIds($search, $categoryId, $brandId, $status, $condition)),
            'filters' => ['search' => $search, 'category' => $categoryId, 'brand' => $brandId, 'status' => $status?->value ?? '', 'condition' => $condition?->value ?? '', 'sort' => $sort->value, 'direction' => $direction],
            'statuses' => ProductStatusFilter::options(),
            'conditions' => ProductConditionFilter::options(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'create_url' => route('tenant.products.create', ['tenant' => tenant('code')]),
            'quick_access' => ['count' => Product::query()->whereNotNull('quick_access_position')->count(), 'limit' => Product::QUICK_ACCESS_LIMIT],
            'barcode_label' => [
                'limits' => [
                    'max_copies_per_product' => BarcodeLabelService::MAX_COPIES_PER_PRODUCT,
                    'max_labels' => BarcodeLabelService::MAX_LABELS,
                    ...$this->labelStockLimits(),
                ],
                'urls' => [
                    'products' => route('tenant.barcode-labels.products', ['tenant' => tenant('code')]),
                    'sheet' => route('tenant.barcode-labels.sheet', ['tenant' => tenant('code')]),
                ],
            ],
            'price_label' => [
                'limits' => [
                    'max_copies_per_unit' => PriceLabelService::MAX_COPIES_PER_UNIT,
                    'max_labels' => PriceLabelService::MAX_LABELS,
                    ...$this->labelStockLimits(),
                ],
                'urls' => [
                    'units' => route('tenant.price-labels.units', ['tenant' => tenant('code')]),
                    'sheet' => route('tenant.price-labels.sheet', ['tenant' => tenant('code')]),
                ],
            ],
            'bulk_price' => [
                'cost_methods' => CostChangeMethod::options(),
                'selling_methods' => SellingChangeMethod::options(),
                'max_products' => BulkPriceService::MAX_PRODUCTS,
                'selling_price_step' => $this->storeSettings->sellingPriceStep(),
                'urls' => [
                    'units' => route('tenant.bulk-prices.units', ['tenant' => tenant('code')]),
                    'preview' => route('tenant.bulk-prices.preview', ['tenant' => tenant('code')]),
                    'apply' => route('tenant.bulk-prices.apply', ['tenant' => tenant('code')]),
                ],
            ],
        ]);
    }

    /**
     * The sizes of sticker stock the printing windows accept, the same for barcode stickers and price labels.
     *
     * @return array{min_width: int, max_width: int, min_height: int, max_height: int, max_columns: int}
     */
    private function labelStockLimits(): array
    {
        return [
            'min_width' => BarcodeLabelSheetRequest::MIN_WIDTH_MM,
            'max_width' => BarcodeLabelSheetRequest::MAX_WIDTH_MM,
            'min_height' => BarcodeLabelSheetRequest::MIN_HEIGHT_MM,
            'max_height' => BarcodeLabelSheetRequest::MAX_HEIGHT_MM,
            'max_columns' => BarcodeLabelSheetRequest::MAX_COLUMNS,
        ];
    }

    public function create(): Response
    {
        return Inertia::render('Tenant/Products/Form', [
            'product' => null,
            'suggested_sku' => $this->productService->suggestSku(tenant('receipt_prefix')),
            'form_action' => ['url' => route('tenant.products.store', ['tenant' => tenant('code')]), 'method' => 'post'],
            ...$this->formOptions(),
        ]);
    }

    public function store(SaveProductRequest $request): RedirectResponse
    {
        $this->productService->create($request->validated());

        return redirect()->route('tenant.products.index', ['tenant' => tenant('code')])->with('success', 'Barang ditambahkan.');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Tenant/Products/Form', [
            'product' => ProductFormResource::make($product->load(['category', 'brand', 'productUnits.unit']))->resolve(),
            'form_action' => ['url' => route('tenant.products.update', ['tenant' => tenant('code'), 'product' => $product->id]), 'method' => 'put'],
            ...$this->formOptions(),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        try {
            $this->productService->update($product, $request->validated());
        } catch (ProductSaveException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('tenant.products.index', ['tenant' => tenant('code')])->with('success', 'Barang disimpan.');
    }

    public function setQuickAccess(SetQuickAccessRequest $request, Product $product): RedirectResponse
    {
        try {
            $this->productService->setQuickAccess($product, $request->boolean('is_in_quick_access'));
        } catch (ProductSaveException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        try {
            $this->productService->delete($product);
        } catch (ProductHasTransactionsException $exception) {
            return redirect()->route('tenant.products.index', ['tenant' => tenant('code')])->with('error', $exception->getMessage());
        }

        return redirect()->route('tenant.products.index', ['tenant' => tenant('code')])->with('success', 'Barang dihapus.');
    }

    /**
     * @return array{categories: mixed, brands: mixed, units: mixed}
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->orderBy('code')->get(['code', 'name']),
            'selling_price_step' => $this->storeSettings->sellingPriceStep(),
        ];
    }
}
