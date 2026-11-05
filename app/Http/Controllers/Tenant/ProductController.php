<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ProductStatusFilter;
use App\Exceptions\ProductHasTransactionsException;
use App\Exceptions\ProductSaveException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveProductRequest;
use App\Http\Resources\ProductFormResource;
use App\Http\Resources\ProductListResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(private readonly ProductService $productService) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $categoryId = $request->integer('category') ?: null;
        $statusFilter = $request->string('status')->toString();

        $products = $this->productService
            ->paginateProducts($search, $categoryId, ProductStatusFilter::tryFrom($statusFilter), self::PER_PAGE)
            ->through(fn (Product $product): array => ProductListResource::make($product)->resolve());

        return Inertia::render('Tenant/Products/Index', [
            'products' => $products,
            'filters' => ['search' => $search, 'category' => $categoryId, 'status' => $statusFilter],
            'statuses' => ProductStatusFilter::options(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'create_url' => route('tenant.products.create', ['tenant' => tenant('code')]),
            'bulk_prices_url' => route('tenant.bulk-prices.index', ['tenant' => tenant('code')]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tenant/Products/Form', [
            'product' => null,
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
        ];
    }
}
