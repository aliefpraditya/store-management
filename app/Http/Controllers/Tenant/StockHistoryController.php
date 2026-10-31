<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockHistoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockHistoryController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private readonly StockHistoryService $history) {}

    public function index(Request $request): Response
    {
        $filters = [
            'product_id' => $request->integer('product') ?: null,
            'warehouse_id' => $request->integer('warehouse') ?: null,
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
        ];

        $movements = $this->history->paginate($filters, self::PER_PAGE)
            ->through(fn (StockMovement $movement): array => StockMovementResource::make($movement)->resolve());

        $product = $filters['product_id'] === null ? null : Product::withTrashed()->with('baseUnit')->find($filters['product_id']);

        return Inertia::render('Tenant/StockHistory/Index', [
            'movements' => $movements,
            'filters' => $filters,
            'product' => $product === null ? null : ['id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'base_unit' => $product->baseUnit->code],
            'warehouses' => Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']),
            'product_search_url' => route('tenant.products.search', ['tenant' => tenant('code')]),
        ]);
    }
}
