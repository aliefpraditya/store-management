<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\PurchasePaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\WarehouseType;
use App\Exceptions\PurchaseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CancelPurchaseRequest;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Http\Requests\Tenant\PayPurchaseRequest;
use App\Http\Requests\Tenant\SavePurchaseRequest;
use App\Http\Resources\PurchaseListResource;
use App\Http\Resources\PurchaseResource;
use App\Models\Carrier;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\CarrierService;
use App\Services\PurchaseAttachmentService;
use App\Services\PurchaseService;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Purchases (nota) from suppliers: the list, making and changing a draft with its lines and nota files, confirming it into
 * an order, and cancelling one.
 */
class PurchaseController extends Controller
{
    private const LIST_PER_PAGE = 25;

    public function __construct(
        private readonly PurchaseService $purchaseService,
        private readonly SupplierService $supplierService,
        private readonly CarrierService $carrierService,
    ) {}

    public function index(DateRangeRequest $request): Response
    {
        $filters = [
            'from' => $request->fromDate()?->toDateString(),
            'to' => $request->toDate()?->toDateString(),
            'supplier' => $request->integer('supplier') ?: null,
            'carrier' => $request->integer('carrier') ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'search' => $request->string('search')->trim()->toString() ?: null,
        ];

        return Inertia::render('Tenant/Purchases/Index', [
            'purchases' => $this->purchaseService->paginate($filters, self::LIST_PER_PAGE)
                ->through(fn (Purchase $purchase): array => PurchaseListResource::make($purchase)->resolve()),
            'filters' => $filters,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'carriers' => Carrier::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => PurchaseStatus::options(),
            'create_url' => route('tenant.purchases.create', ['tenant' => tenant('code')]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tenant/Purchases/Form', [
            'purchase' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(SavePurchaseRequest $request): RedirectResponse
    {
        try {
            $purchase = $this->purchaseService->create($request->user(), $request->safe()->except('attachments'), $request->file('attachments', []));
        } catch (PurchaseException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('tenant.purchases.show', ['tenant' => tenant('code'), 'purchase' => $purchase->id])
            ->with('success', 'Nota dibuat sebagai draft. Periksa lalu konfirmasi bila sudah benar.');
    }

    public function show(Purchase $purchase): Response
    {
        return Inertia::render('Tenant/Purchases/Show', [
            'purchase' => $this->resourceOf($purchase),
            'payment_methods' => PurchasePaymentMethod::options(),
        ]);
    }

    public function edit(Purchase $purchase): Response|RedirectResponse
    {
        if (! $purchase->status->isEditable()) {
            return redirect()
                ->route('tenant.purchases.show', ['tenant' => tenant('code'), 'purchase' => $purchase->id])
                ->with('error', PurchaseException::notEditable()->getMessage());
        }

        return Inertia::render('Tenant/Purchases/Form', [
            'purchase' => $this->resourceOf($purchase),
            ...$this->formOptions($purchase),
        ]);
    }

    public function update(SavePurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        try {
            $this->purchaseService->update($purchase, $request->user(), $request->safe()->except('attachments'), $request->file('attachments', []));
        } catch (PurchaseException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('tenant.purchases.show', ['tenant' => tenant('code'), 'purchase' => $purchase->id])
            ->with('success', 'Nota disimpan.');
    }

    public function confirm(Request $request, Purchase $purchase): RedirectResponse
    {
        try {
            $this->purchaseService->confirm($purchase, $request->user());
        } catch (PurchaseException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Nota dikonfirmasi. Barang dan harganya sekarang terkunci.');
    }

    public function pay(PayPurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        try {
            $this->purchaseService->confirmAndPay($purchase, $request->user(), $request->validated());
        } catch (PurchaseException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Nota dikonfirmasi dan dibayar. Barang dan harganya sekarang terkunci.');
    }

    public function cancel(CancelPurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        try {
            $this->purchaseService->cancel($purchase, $request->validated('reason'));
        } catch (PurchaseException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Nota dibatalkan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function resourceOf(Purchase $purchase): array
    {
        return PurchaseResource::make($purchase->load(['supplier', 'carrier', 'warehouse', 'items.product.productUnits', 'attachments']))->resolve();
    }

    /**
     * What the form offers to choose from: the suppliers that can still be bought from, the carriers the goods can be sent
     * with (plus the one a purchase being edited already has), and the sellable warehouses a nota can be meant for (the
     * default one first). Suppliers and carriers carry their city, so the form can suggest the carrier in the supplier's city.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?Purchase $editedPurchase = null): array
    {
        return [
            'suppliers' => $this->supplierService->active()->map(fn (Supplier $supplier): array => ['id' => $supplier->id, 'name' => $supplier->name, 'city' => $supplier->city])->values(),
            'carriers' => $this->carrierService->selectable($editedPurchase?->carrier_id)->map(fn (Carrier $carrier): array => ['id' => $carrier->id, 'name' => $carrier->name, 'city' => $carrier->city])->values(),
            'warehouses' => Warehouse::query()->where('type', WarehouseType::Sellable)->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']),
            'search_url' => route('tenant.products.search', ['tenant' => tenant('code')]),
            'store_url' => route('tenant.purchases.store', ['tenant' => tenant('code')]),
            'index_url' => route('tenant.purchases.index', ['tenant' => tenant('code')]),
            'max_attachments' => PurchaseAttachmentService::MAX_PER_PURCHASE,
        ];
    }
}
