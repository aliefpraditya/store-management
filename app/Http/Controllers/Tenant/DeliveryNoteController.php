<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\DeliveryNoteStatus;
use App\Exceptions\DeliveryNoteException;
use App\Http\Controllers\Concerns\PrintsDeliveryNotes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CompleteDeliveryNoteRequest;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Http\Requests\Tenant\SaveDeliveryNoteRequest;
use App\Http\Requests\Tenant\ShipDeliveryNoteRequest;
use App\Http\Resources\DeliveryNoteListResource;
use App\Http\Resources\DeliveryNoteResource;
use App\Models\DeliveryNote;
use App\Models\Driver;
use App\Models\Sale;
use App\Services\DeliveryNoteService;
use App\Services\DriverService;
use App\Services\PrintCounter;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Delivery notes (surat jalan): list, make one from a receipt, follow it through shipping and delivery, print it.
 */
class DeliveryNoteController extends Controller
{
    use PrintsDeliveryNotes;

    private const LIST_PER_PAGE = 25;

    private const SALE_LOOKUP_LIMIT = 10;

    public function __construct(
        private readonly DeliveryNoteService $noteService,
        private readonly PrintCounter $printCounter,
        private readonly DriverService $drivers,
    ) {}

    public function index(DateRangeRequest $request): Response
    {
        $statusFilter = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();

        $notes = $this->noteService
            ->paginateNotes($statusFilter, $search, $request->fromDate(), $request->toDate(), self::LIST_PER_PAGE)
            ->through(fn (DeliveryNote $note): array => DeliveryNoteListResource::make($note)->resolve());

        return Inertia::render('Tenant/DeliveryNotes/Index', [
            'notes' => $notes,
            'filters' => [
                'status' => $statusFilter,
                'search' => $search,
                'from' => $request->fromDate()?->toDateString(),
                'to' => $request->toDate()?->toDateString(),
            ],
            'statuses' => collect(DeliveryNoteStatus::cases())->map(fn (DeliveryNoteStatus $case): array => ['value' => $case->value, 'label' => $case->label()])->all(),
            'create_url' => route('tenant.delivery-notes.create', ['tenant' => tenant('code')]),
        ]);
    }

    /**
     * A new note, optionally for a receipt already chosen (`?sale=`, from the receipt page).
     */
    public function create(Request $request): Response
    {
        $sale = $request->integer('sale') ? Sale::query()->with('items')->find($request->integer('sale')) : null;

        return Inertia::render('Tenant/DeliveryNotes/Create', [
            'sale' => $sale === null ? null : $this->saleOption($sale),
            'urls' => [
                'index' => route('tenant.delivery-notes.index', ['tenant' => tenant('code')]),
                'store' => route('tenant.delivery-notes.store', ['tenant' => tenant('code')]),
                'sales' => route('tenant.delivery-notes.sales', ['tenant' => tenant('code')]),
            ],
        ]);
    }

    /**
     * Completed receipts to pick from, found by receipt number or customer name; only those with something left
     * to deliver.
     */
    public function sales(Request $request): JsonResponse
    {
        $sales = $this->noteService
            ->deliverableSales($request->string('q')->trim()->toString(), self::SALE_LOOKUP_LIMIT)
            ->map(fn (Sale $sale): array => $this->saleOption($sale))
            ->values();

        return response()->json(['data' => $sales]);
    }

    public function store(SaveDeliveryNoteRequest $request): RedirectResponse
    {
        try {
            $note = $this->noteService->create(Sale::query()->findOrFail($request->validated('sale_id')), $request->user(), $request->validated());
        } catch (DeliveryNoteException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('tenant.delivery-notes.show', ['tenant' => tenant('code'), 'delivery_note' => $note->id])->with('success', 'Surat jalan dibuat.');
    }

    public function show(DeliveryNote $deliveryNote): Response
    {
        return Inertia::render('Tenant/DeliveryNotes/Show', [
            'note' => DeliveryNoteResource::make($deliveryNote->load(['items', 'sale']))->resolve(),
            'drivers' => $this->drivers->active()->map(fn (Driver $driver): array => ['id' => $driver->id, 'name' => $driver->name])->all(),
            'settings_url' => route('tenant.settings.index', ['tenant' => tenant('code')]),
        ]);
    }

    public function ship(ShipDeliveryNoteRequest $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        return $this->redirectWithOutcome(function () use ($request, $deliveryNote): string {
            $driverId = $request->validated('driver_id');

            $this->noteService->ship($deliveryNote, $driverId === null ? null : Driver::query()->findOrFail($driverId));

            return 'Surat jalan ditandai dikirim.';
        });
    }

    public function complete(CompleteDeliveryNoteRequest $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        return $this->redirectWithOutcome(function () use ($request, $deliveryNote): string {
            $this->noteService->complete($deliveryNote, $request->validated('results'));

            return 'Pengiriman diselesaikan.';
        });
    }

    public function cancel(DeliveryNote $deliveryNote): RedirectResponse
    {
        return $this->redirectWithOutcome(function () use ($deliveryNote): string {
            $this->noteService->cancel($deliveryNote);

            return 'Surat jalan dibatalkan.';
        });
    }

    /**
     * Notes one more print and answers the sheet to open in a new window.
     */
    public function print(DeliveryNote $deliveryNote): JsonResponse
    {
        return $this->recordPrintOf($this->printCounter, $deliveryNote, 'tenant.delivery-notes.sheet');
    }

    public function sheet(Request $request, DeliveryNote $deliveryNote): Response
    {
        return $this->sheetOf($request, $deliveryNote);
    }

    /**
     * @return array{id: int, number: string, sold_at: string, customer_name: string|null, lines: list<array<string, mixed>>}
     */
    private function saleOption(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'sold_at' => $sale->sold_at->toIso8601String(),
            'customer_name' => $sale->customer_name,
            'lines' => $this->noteService->deliverableLines($sale),
        ];
    }

    /**
     * @param  Closure(): string  $action  returns the success message
     */
    private function redirectWithOutcome(Closure $action): RedirectResponse
    {
        try {
            return back()->with('success', $action());
        } catch (DeliveryNoteException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
