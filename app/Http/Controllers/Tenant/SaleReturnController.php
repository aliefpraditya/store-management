<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\PaymentMethod;
use App\Enums\ReturnDisposition;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Exceptions\SaleReturnException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveSaleReturnRequest;
use App\Http\Resources\SaleReturnListResource;
use App\Http\Resources\SaleReturnResource;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Store;
use App\Services\SaleReturnService;
use App\Services\ShiftService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
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
        private readonly SaleReturnService $returns,
        private readonly ShiftService $shifts,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();

        $returns = SaleReturn::query()
            ->with(['sale', 'cashShift'])
            ->when(SaleReturnStatus::tryFrom($status), fn (Builder $query, SaleReturnStatus $status) => $query->where('status', $status))
            ->when($request->date('from'), fn (Builder $query, $from) => $query->whereDate('returned_at', '>=', $from))
            ->when($request->date('to'), fn (Builder $query, $to) => $query->whereDate('returned_at', '<=', $to))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('sale', fn (Builder $sale) => $sale->where('number', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%"))))
            ->orderByDesc('id')
            ->paginate(self::LIST_PER_PAGE)
            ->withQueryString()
            ->through(fn (SaleReturn $saleReturn): array => SaleReturnListResource::make($saleReturn)->resolve());

        return Inertia::render('Tenant/Returns/Index', [
            'returns' => $returns,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'from' => $request->date('from')?->toDateString(),
                'to' => $request->date('to')?->toDateString(),
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
            'refund_methods' => collect(PaymentMethod::forCashier())->map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()])->all(),
            'dispositions' => collect(ReturnDisposition::cases())->map(fn (ReturnDisposition $case): array => ['value' => $case->value, 'label' => $case->label()])->all(),
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
        $term = $request->string('q')->trim()->toString();

        $sales = Sale::query()
            ->with(['items', 'payments'])
            ->where('status', SaleStatus::Completed)
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")))
            ->orderByDesc('sold_at')
            ->limit(self::SALE_LOOKUP_LIMIT)
            ->get()
            ->map(fn (Sale $sale): array => $this->saleOption($sale))
            ->filter(fn (array $option): bool => collect($option['lines'])->contains(fn (array $line): bool => (float) $line['remaining'] > 0))
            ->values();

        return response()->json(['data' => $sales]);
    }

    /**
     * An admin's return is approved straight away. It comes out of the drawer of the admin's own running shift.
     */
    public function store(SaveSaleReturnRequest $request): RedirectResponse
    {
        $shift = $this->shifts->openShiftOf($request->user());

        if ($shift === null) {
            return back()->withInput()->with('error', SaleReturnException::shiftNotOpen()->getMessage());
        }

        try {
            $saleReturn = $this->returns->createApproved(Sale::query()->findOrFail($request->validated('sale_id')), $shift, $request->user(), $request->validated());
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
        return $this->perform(function () use ($request, $saleReturn): string {
            $this->returns->approve($saleReturn, $request->user());

            return 'Retur disetujui. Stok sudah kembali.';
        });
    }

    public function reject(Request $request, SaleReturn $saleReturn): RedirectResponse
    {
        return $this->perform(function () use ($request, $saleReturn): string {
            $this->returns->reject($saleReturn, $request->user());

            return 'Retur ditolak.';
        });
    }

    /**
     * The return slip, opened in its own window and printed there.
     */
    public function sheet(SaleReturn $saleReturn): Response
    {
        /** @var Store $store */
        $store = tenant();

        return Inertia::render('Tenant/Print/SaleReturn', [
            'saleReturn' => [
                ...SaleReturnResource::make($saleReturn->load(['sale', 'cashShift', 'items.saleItem']))->resolve(),
                'store' => ['name' => $store->name, 'address' => $store->address, 'phone' => $store->phone],
            ],
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
            'default_refund_method' => $this->returns->defaultRefundMethod($sale)->value,
            'lines' => $this->returns->returnableLines($sale),
        ];
    }

    /**
     * @param  Closure(): string  $step  returns the success message
     */
    private function perform(Closure $step): RedirectResponse
    {
        try {
            return back()->with('success', $step());
        } catch (SaleReturnException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
