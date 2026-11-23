<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CodSettlementStatus;
use App\Enums\DeliveryNoteStatus;
use App\Enums\Permission;
use App\Exceptions\CodSaleException;
use App\Exceptions\DeliveryNoteException;
use App\Http\Controllers\Concerns\PrintsDeliveryNotes;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureUserCanAccessStore;
use App\Http\Requests\Tenant\CompleteDeliveryNoteRequest;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Http\Requests\Tenant\ReceiveCodSettlementRequest;
use App\Http\Requests\Tenant\SaveDeliveryNoteRequest;
use App\Http\Requests\Tenant\ShipDeliveryNoteRequest;
use App\Http\Resources\DeliveryNoteCodResource;
use App\Http\Resources\DeliveryNoteListResource;
use App\Http\Resources\DeliveryNoteResource;
use App\Models\DeliveryNote;
use App\Models\Driver;
use App\Models\Sale;
use App\Services\CodSettlementService;
use App\Services\DeliveryNoteService;
use App\Services\DriverService;
use App\Services\PrintCounter;
use App\Services\StoreSettingService;
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
        private readonly CodSettlementService $codSettlements,
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
            'can_create' => EnsureUserCanAccessStore::grants($request, Permission::DeliveryNotesManage),
        ]);
    }

    /**
     * A new note, optionally for a receipt already chosen (`?sale=`, from the receipt page).
     */
    public function create(Request $request): Response
    {
        $sale = $request->integer('sale') ? Sale::query()->with('items')->find($request->integer('sale')) : null;

        return Inertia::render('Tenant/DeliveryNotes/Create', [
            'sale' => $sale === null ? null : $this->saleOption($sale, $this->noteService->deliverableLines($sale)),
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
        $sales = $this->noteService->deliverableSales($request->string('q')->trim()->toString(), self::SALE_LOOKUP_LIMIT);
        $linesBySaleId = $this->noteService->deliverableLinesOf($sales);

        return response()->json(['data' => $sales->map(fn (Sale $sale): array => $this->saleOption($sale, $linesBySaleId[$sale->id]))->values()]);
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

    public function show(Request $request, DeliveryNote $deliveryNote): Response
    {
        return Inertia::render('Tenant/DeliveryNotes/Show', [
            'note' => DeliveryNoteResource::make($deliveryNote->load(['items', 'sale']))->resolve(),
            'cod' => $this->codMoneyOf($request, $deliveryNote),
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

    /**
     * Receives the money of the COD receipt this note belongs to, once the goods were delivered.
     */
    public function receiveCod(ReceiveCodSettlementRequest $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        return $this->redirectWithCodOutcome(function () use ($request, $deliveryNote): string {
            $settlement = $this->codSettlements->receive($deliveryNote->sale, $request->user(), $request->validated());

            return $settlement->status === CodSettlementStatus::AwaitingApproval
                ? 'Uang COD tercatat. Selisihnya menunggu persetujuan.'
                : 'Uang COD diterima.';
        });
    }

    public function approveCod(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        return $this->redirectWithCodOutcome(function () use ($request, $deliveryNote): string {
            $this->codSettlements->approve($deliveryNote->sale, $request->user());

            return 'Penerimaan uang COD disetujui.';
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

    public function sheet(Request $request, DeliveryNote $deliveryNote, StoreSettingService $settings): Response
    {
        return $this->sheetOf($request, $deliveryNote, $settings->letterhead());
    }

    /**
     * @param  list<array<string, mixed>>  $deliverableLines
     * @return array{id: int, number: string, sold_at: string, customer_name: string|null, lines: list<array<string, mixed>>}
     */
    private function saleOption(Sale $sale, array $deliverableLines): array
    {
        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'sold_at' => $sale->sold_at->toIso8601String(),
            'customer_name' => $sale->customer_name,
            'lines' => $deliverableLines,
        ];
    }

    /**
     * The money of the note's COD receipt, for those who may receive or approve it; null when the receipt is not COD, the
     * note was cancelled, or its goods all came back, so there is nothing to receive on it.
     *
     * @return array<string, mixed>|null
     */
    private function codMoneyOf(Request $request, DeliveryNote $note): ?array
    {
        $mayHandleMoney = EnsureUserCanAccessStore::grants($request, Permission::CodReceive) || EnsureUserCanAccessStore::grants($request, Permission::CodApprove);

        if (! $mayHandleMoney || in_array($note->status, [DeliveryNoteStatus::Cancelled, DeliveryNoteStatus::Returned], true)) {
            return null;
        }

        $note->sale->loadMissing('payments');

        if (! $note->sale->isCod()) {
            return null;
        }

        return DeliveryNoteCodResource::make(['row' => $this->codSettlements->rowOf($note->sale), 'note' => $note])->resolve($request);
    }

    /**
     * @param  Closure(): string  $action  returns the success message
     */
    private function redirectWithCodOutcome(Closure $action): RedirectResponse
    {
        try {
            return back()->with('success', $action());
        } catch (CodSaleException $exception) {
            return back()->with('error', $exception->getMessage());
        }
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
