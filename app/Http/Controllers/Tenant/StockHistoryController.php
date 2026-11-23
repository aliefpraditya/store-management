<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockHistoryService;
use Inertia\Inertia;
use Inertia\Response;

class StockHistoryController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private readonly StockHistoryService $historyService) {}

    public function index(DateRangeRequest $request): Response
    {
        $filters = [
            'product_id' => $request->integer('product') ?: null,
            'warehouse_id' => $request->integer('warehouse') ?: null,
            'from' => $request->fromDate()?->toDateString(),
            'to' => $request->toDate()?->toDateString(),
        ];

        $movements = $this->historyService->paginate($filters, self::PER_PAGE)
            ->through(fn (StockMovement $movement): array => StockMovementResource::make($movement)->resolve());

        $product = $filters['product_id'] === null ? null : Product::withTrashed()->with('baseUnit')->find($filters['product_id']);
        $day = $request->toDate();

        return Inertia::render('Tenant/StockHistory/Index', [
            'movements' => $movements,
            'filters' => $filters,
            'product' => $product === null ? null : ['id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'base_unit' => $product->baseUnit->code],
            'stock_on' => $product === null || $day === null ? null : ['date' => $day->toDateString(), ...$this->historyService->stockOn($product->id, $day)],
            'warehouses' => Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']),
            'product_search_url' => route('tenant.products.search', ['tenant' => tenant('code')]),
        ]);
    }
}
