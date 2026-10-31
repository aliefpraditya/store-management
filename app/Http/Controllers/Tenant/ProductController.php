<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\ProductHasTransactionsException;
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

    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $categoryId = $request->integer('category') ?: null;
        $status = $request->string('status')->toString();

        $products = Product::query()
            ->withStockTotals()
            ->withTransactionFlag()
            ->with(['category', 'brand', 'baseUnit', 'productUnits.unit'])
            ->search($search)
            ->when($categoryId, fn ($query) => $query->where('products.category_id', $categoryId))
            ->when($status === 'active', fn ($query) => $query->where('products.is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('products.is_active', false))
            ->orderBy('products.name')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Product $product): array => ProductListResource::make($product)->resolve());

        return Inertia::render('Tenant/Products/Index', [
            'products' => $products,
            'filters' => ['search' => $search, 'category' => $categoryId, 'status' => $status],
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'create_url' => route('tenant.products.create', ['tenant' => tenant('code')]),
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
        $this->products->create($request->validated());

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
        $this->products->update($product, $request->validated());

        return redirect()->route('tenant.products.index', ['tenant' => tenant('code')])->with('success', 'Barang disimpan.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        try {
            $this->products->delete($product);
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
