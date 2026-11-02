<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\PaymentMethod;
use App\Enums\ReturnDisposition;
use App\Enums\ReturnResolution;
use App\Enums\SaleReturnStatus;
use App\Exceptions\SaleReturnException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Http\Requests\Tenant\SaveSaleReturnRequest;
use App\Http\Resources\PrintableSaleReturnResource;
use App\Http\Resources\SaleReturnListResource;
use App\Http\Resources\SaleReturnResource;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\SaleReturnService;
use App\Services\ShiftService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Returns (retur) for admins: the list with the ones waiting for a decision, making one from any receipt, and
 * approving or rejecting. A return an admin makes is approved at once. Cashiers ask for returns from the till.
 */
class SaleReturnController extends Controller
{
    private const LIST_PER_PAGE = 25;

    private const SALE_LOOKUP_LIMIT = 10;

    public function __construct(
        private readonly SaleReturnService $returnService,
        private readonly ShiftService $shiftService,
    ) {}

    public function index(DateRangeRequest $request): Response
    {
        $statusFilter = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();

        $returns = $this->returnService
            ->paginateReturns($statusFilter, $search, $request->fromDate(), $request->toDate(), self::LIST_PER_PAGE)
            ->through(fn (SaleReturn $saleReturn): array => SaleReturnListResource::make($saleReturn)->resolve());

        return Inertia::render('Tenant/Returns/Index', [
            'returns' => $returns,
            'filters' => [
                'status' => $statusFilter,
                'search' => $search,
                'from' => $request->fromDate()?->toDateString(),
                'to' => $request->toDate()?->toDateString(),
            ],
            'statuses' => collect(SaleReturnStatus::cases())->map(fn (SaleReturnStatus $case): array => ['value' => $case->value, 'label' => $case->label()])->all(),
            'pending_count' => SaleReturn::query()->where('status', SaleReturnStatus::Pending)->count(),
            'create_url' => route('tenant.returns.create', ['tenant' => tenant('code')]),
        ]);
    }

    /**
     * A new return, optionally for a receipt already chosen (`?sale=`, from the receipt page).
     */
    public function create(Request $request): Response
    {
        $sale = $request->integer('sale') ? Sale::query()->with(['items', 'payments'])->find($request->integer('sale')) : null;

        return Inertia::render('Tenant/Returns/Create', [
            'sale' => $sale === null ? null : $this->saleOption($sale),
            'refund_methods' => PaymentMethod::cashierOptions(),
            'dispositions' => ReturnDisposition::options(),
            'resolutions' => ReturnResolution::options(),
            'urls' => [
                'index' => route('tenant.returns.index', ['tenant' => tenant('code')]),
                'store' => route('tenant.returns.store', ['tenant' => tenant('code')]),
                'sales' => route('tenant.returns.sales', ['tenant' => tenant('code')]),
                'products' => route('tenant.cashier.products', ['tenant' => tenant('code')]),
            ],
        ]);
    }

    /**
     * Completed receipts to pick from, found by receipt number or customer name; only those with something left
     * to return.
     */
    public function sales(Request $request): JsonResponse
    {
        $sales = $this->returnService
            ->returnableSales($request->string('q')->trim()->toString(), self::SALE_LOOKUP_LIMIT)
            ->map(fn (Sale $sale): array => $this->saleOption($sale))
            ->values();

        return response()->json(['data' => $sales]);
    }

    /**
     * An admin's return is approved straight away. It comes out of the drawer of the admin's own running shift.
     */
    public function store(SaveSaleReturnRequest $request): RedirectResponse
    {
        $shift = $this->shiftService->openShiftOf($request->user());

        if ($shift === null) {
            return back()->withInput()->with('error', SaleReturnException::shiftNotOpen()->getMessage());
        }

        try {
            $saleReturn = $this->returnService->createApproved(Sale::query()->findOrFail($request->validated('sale_id')), $shift, $request->user(), $request->validated());
        } catch (SaleReturnException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('tenant.returns.show', ['tenant' => tenant('code'), 'sale_return' => $saleReturn->id])->with('success', 'Retur selesai. Stok sudah kembali.');
    }

    public function show(SaleReturn $saleReturn): Response
    {
        return Inertia::render('Tenant/Returns/Show', [
            'saleReturn' => [
                ...SaleReturnResource::make($saleReturn->load(['sale', 'cashShift', 'items.saleItem']))->resolve(),
                'urls' => [
                    'index' => route('tenant.returns.index', ['tenant' => tenant('code')]),
                    'sale' => route('tenant.sales.show', ['tenant' => tenant('code'), 'sale' => $saleReturn->sale_id]),
                    'approve' => route('tenant.returns.approve', ['tenant' => tenant('code'), 'sale_return' => $saleReturn->id]),
                    'reject' => route('tenant.returns.reject', ['tenant' => tenant('code'), 'sale_return' => $saleReturn->id]),
                    'sheet' => route('tenant.returns.sheet', ['tenant' => tenant('code'), 'sale_return' => $saleReturn->id]),
                ],
            ],
        ]);
    }

    public function approve(Request $request, SaleReturn $saleReturn): RedirectResponse
    {
        return $this->redirectWithOutcome(function () use ($request, $saleReturn): string {
            $this->returnService->approve($saleReturn, $request->user());

            return 'Retur disetujui. Stok sudah kembali.';
        });
    }

    public function reject(Request $request, SaleReturn $saleReturn): RedirectResponse
    {
        return $this->redirectWithOutcome(function () use ($request, $saleReturn): string {
            $this->returnService->reject($saleReturn, $request->user());

            return 'Retur ditolak.';
        });
    }

    /**
     * The return slip, opened in its own window and printed there.
     */
    public function sheet(SaleReturn $saleReturn): Response
    {
        return Inertia::render('Tenant/Print/SaleReturn', [
            'saleReturn' => PrintableSaleReturnResource::make($saleReturn->load(['sale', 'cashShift', 'items.saleItem']))->resolve(),
        ]);
    }

    /**
     * @return array{id: int, number: string, sold_at: string, customer_name: string|null, default_refund_method: string, lines: list<array<string, mixed>>}
     */
    private function saleOption(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'sold_at' => $sale->sold_at->toIso8601String(),
            'customer_name' => $sale->customer_name,
            'default_refund_method' => $this->returnService->defaultRefundMethod($sale)->value,
            'lines' => $this->returnService->returnableLines($sale),
        ];
    }

    /**
     * @param  Closure(): string  $action  returns the success message
     */
    private function redirectWithOutcome(Closure $action): RedirectResponse
    {
        try {
            return back()->with('success', $action());
        } catch (SaleReturnException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
