<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ShipmentStatus;
use App\Exceptions\ShipmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CancelShipmentRequest;
use App\Http\Requests\Tenant\SaveShipmentRequest;
use App\Http\Resources\ShipmentListResource;
use App\Http\Resources\ShipmentPurchaseResource;
use App\Http\Resources\ShipmentResource;
use App\Models\Carrier;
use App\Models\Purchase;
use App\Models\Shipment;
use App\Services\CarrierService;
use App\Services\ShipmentAttachmentService;
use App\Services\ShipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shipments of purchases with a carrier: the list, making and changing a draft with its packing list, and cancelling one. Giving
 * a departure date ships it.
 */
class ShipmentController extends Controller
{
    private const LIST_PER_PAGE = 25;

    public function __construct(
        private readonly ShipmentService $shipmentService,
        private readonly CarrierService $carrierService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString() ?: null,
            'carrier' => $request->integer('carrier') ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        return Inertia::render('Tenant/Shipments/Index', [
            'shipments' => $this->shipmentService->paginate($filters, self::LIST_PER_PAGE)
                ->through(fn (Shipment $shipment): array => ShipmentListResource::make($shipment)->resolve()),
            'filters' => $filters,
            'carriers' => Carrier::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => ShipmentStatus::options(),
            'create_url' => route('tenant.shipments.create', ['tenant' => tenant('code')]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tenant/Shipments/Form', [
            'shipment' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(SaveShipmentRequest $request): RedirectResponse
    {
        try {
            $shipment = $this->shipmentService->create($request->user(), $request->safe()->except('attachments'), $request->file('attachments', []));
        } catch (ShipmentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('tenant.shipments.show', ['tenant' => tenant('code'), 'shipment' => $shipment->id])
            ->with('success', $shipment->status === ShipmentStatus::Shipped ? 'Pengiriman berangkat. Nota di dalamnya sekarang berstatus Dikirim.' : 'Pengiriman disimpan sebagai draft. Isi tanggal berangkat bila barangnya sudah berangkat.');
    }

    public function show(Shipment $shipment): Response
    {
        return Inertia::render('Tenant/Shipments/Show', ['shipment' => $this->resourceOf($shipment)]);
    }

    public function edit(Shipment $shipment): Response|RedirectResponse
    {
        if (! $shipment->status->isEditable()) {
            return redirect()
                ->route('tenant.shipments.show', ['tenant' => tenant('code'), 'shipment' => $shipment->id])
                ->with('error', ShipmentException::notEditable()->getMessage());
        }

        return Inertia::render('Tenant/Shipments/Form', [
            'shipment' => $this->resourceOf($shipment),
            ...$this->formOptions($shipment),
        ]);
    }

    public function update(SaveShipmentRequest $request, Shipment $shipment): RedirectResponse
    {
        try {
            $updatedShipment = $this->shipmentService->update($shipment, $request->user(), $request->safe()->except('attachments'), $request->file('attachments', []));
        } catch (ShipmentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('tenant.shipments.show', ['tenant' => tenant('code'), 'shipment' => $shipment->id])
            ->with('success', $updatedShipment->status === ShipmentStatus::Shipped ? 'Pengiriman berangkat. Nota di dalamnya sekarang berstatus Dikirim.' : 'Pengiriman disimpan.');
    }

    public function cancel(CancelShipmentRequest $request, Shipment $shipment): RedirectResponse
    {
        try {
            $this->shipmentService->cancel($shipment, $request->validated('reason'));
        } catch (ShipmentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pengiriman dibatalkan. Nota di dalamnya bisa dimuat di pengiriman lain.');
    }

    /**
     * @return array<string, mixed>
     */
    private function resourceOf(Shipment $shipment): array
    {
        return ShipmentResource::make($shipment->load([
            'carrier',
            'purchases.supplier',
            'purchases.items.product',
            'attachments',
            'containers.lines.purchaseItem.purchase.supplier',
            'containers.lines.purchaseItem.product',
        ]))->resolve();
    }

    /**
     * What the form offers: the carriers a shipment can use (plus the one a draft already has) and the purchases that can be
     * loaded, which is every ordered purchase not in another shipment.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?Shipment $editedShipment = null): array
    {
        return [
            'carriers' => $this->carrierService->selectable($editedShipment?->carrier_id)->map(fn (Carrier $carrier): array => ['id' => $carrier->id, 'name' => $carrier->name, 'transit_days' => $carrier->transit_days])->values(),
            'available_purchases' => $this->shipmentService->availablePurchases($editedShipment)
                ->map(fn (Purchase $purchase): array => ShipmentPurchaseResource::make($purchase)->resolve())
                ->values(),
            'store_url' => route('tenant.shipments.store', ['tenant' => tenant('code')]),
            'index_url' => route('tenant.shipments.index', ['tenant' => tenant('code')]),
            'max_attachments' => ShipmentAttachmentService::MAX_PER_SHIPMENT,
        ];
    }
}
