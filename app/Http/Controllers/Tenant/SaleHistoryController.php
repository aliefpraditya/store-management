<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Http\Resources\SaleDetailResource;
use App\Http\Resources\SaleHistoryRowResource;
use App\Models\Sale;
use App\Models\Store;
use App\Services\SaleHistoryService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The receipt history of the whole store, for admins only. A cashier looks up receipts of their running shift in
 * the dialog on the cashier screen instead.
 */
class SaleHistoryController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(private readonly SaleHistoryService $saleHistoryService) {}

    public function index(DateRangeRequest $request): Response
    {
        $filters = [
            'from' => $request->has('from') ? $request->fromDate()?->toDateString() : today()->toDateString(),
            'to' => $request->has('to') ? $request->toDate()?->toDateString() : today()->toDateString(),
            'search' => $request->string('search')->trim()->toString() ?: null,
            'cashier_id' => $request->integer('cashier') ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        return Inertia::render('Tenant/Sales/Index', [
            'sales' => $this->saleHistoryService->paginate($filters, self::PER_PAGE)
                ->through(fn (Sale $sale): array => SaleHistoryRowResource::make($sale)->resolve()),
            'totals' => $this->saleHistoryService->totals($filters),
            'filters' => $filters,
            'cashiers' => $this->saleHistoryService->cashiers(),
            'statuses' => collect(SaleStatus::cases())->map(fn (SaleStatus $status): array => ['value' => $status->value, 'label' => $status->label()])->all(),
        ]);
    }

    public function show(Sale $sale): Response
    {
        /** @var Store $store */
        $store = tenant();

        return Inertia::render('Tenant/Sales/Show', [
            'sale' => SaleDetailResource::make($sale->load(['items', 'payments', 'register']))->resolve(),
            'urls' => [
                'index' => route('tenant.sales.index', ['tenant' => $store->code]),
                'new_delivery_note' => route('tenant.delivery-notes.create', ['tenant' => $store->code, 'sale' => $sale->id]),
                'new_return' => route('tenant.returns.create', ['tenant' => $store->code, 'sale' => $sale->id]),
            ],
        ]);
    }
}
