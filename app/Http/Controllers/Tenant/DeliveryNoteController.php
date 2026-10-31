<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\DeliveryNoteStatus;
use App\Enums\SaleStatus;
use App\Exceptions\DeliveryNoteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CompleteDeliveryNoteRequest;
use App\Http\Requests\Tenant\SaveDeliveryNoteRequest;
use App\Http\Resources\DeliveryNoteListResource;
use App\Http\Resources\DeliveryNoteResource;
use App\Http\Resources\PrintableDeliveryNoteResource;
use App\Models\DeliveryNote;
use App\Models\Sale;
use App\Services\DeliveryNoteService;
use App\Services\PrintCounter;
use Closure;
use Illuminate\Database\Eloquent\Builder;
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
    private const LIST_PER_PAGE = 25;

    private const SALE_LOOKUP_LIMIT = 10;

    public function __construct(
        private readonly DeliveryNoteService $notes,
        private readonly PrintCounter $prints,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();

        $notes = DeliveryNote::query()
            ->with('sale')
            ->withCount('items')
            ->when(DeliveryNoteStatus::tryFrom($status), fn (Builder $query, DeliveryNoteStatus $status) => $query->where('status', $status))
            ->when($request->date('from'), fn (Builder $query, $from) => $query->whereDate('business_date', '>=', $from))
            ->when($request->date('to'), fn (Builder $query, $to) => $query->whereDate('business_date', '<=', $to))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('recipient_name', 'like', "%{$search}%")
                ->orWhereHas('sale', fn (Builder $sale) => $sale->where('number', 'like', "%{$search}%"))))
            ->orderByDesc('id')
            ->paginate(self::LIST_PER_PAGE)
            ->withQueryString()
            ->through(fn (DeliveryNote $note): array => DeliveryNoteListResource::make($note)->resolve());

        return Inertia::render('Tenant/DeliveryNotes/Index', [
            'notes' => $notes,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'from' => $request->date('from')?->toDateString(),
                'to' => $request->date('to')?->toDateString(),
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
        $term = $request->string('q')->trim()->toString();

        $sales = Sale::query()
            ->with('items')
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

    public function store(SaveDeliveryNoteRequest $request): RedirectResponse
    {
        try {
            $note = $this->notes->create(Sale::query()->findOrFail($request->validated('sale_id')), $request->user(), $request->validated());
        } catch (DeliveryNoteException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('tenant.delivery-notes.show', ['tenant' => tenant('code'), 'delivery_note' => $note->id])->with('success', 'Surat jalan dibuat.');
    }

    public function show(DeliveryNote $deliveryNote): Response
    {
        return Inertia::render('Tenant/DeliveryNotes/Show', [
            'note' => DeliveryNoteResource::make($deliveryNote->load(['items', 'sale']))->resolve(),
        ]);
    }

    public function ship(DeliveryNote $deliveryNote): RedirectResponse
    {
        return $this->perform(function () use ($deliveryNote): string {
            $this->notes->ship($deliveryNote);

            return 'Surat jalan ditandai dikirim.';
        });
    }

    public function complete(CompleteDeliveryNoteRequest $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        return $this->perform(function () use ($request, $deliveryNote): string {
            $this->notes->complete($deliveryNote, $request->validated('results'));

            return 'Pengiriman diselesaikan.';
        });
    }

    public function cancel(DeliveryNote $deliveryNote): RedirectResponse
    {
        return $this->perform(function () use ($deliveryNote): string {
            $this->notes->cancel($deliveryNote);

            return 'Surat jalan dibatalkan.';
        });
    }

    /**
     * Notes one more print. The browser then opens the sheet in a new window, which shows the note as printed.
     */
    public function print(DeliveryNote $deliveryNote): JsonResponse
    {
        $isCopy = $this->prints->record($deliveryNote);

        return response()->json(['data' => ['is_copy' => $isCopy, 'print_count' => $deliveryNote->refresh()->print_count]]);
    }

    /**
     * The printable sheet, opened in its own window and printed there. From the second print on it says SALINAN.
     */
    public function sheet(DeliveryNote $deliveryNote): Response
    {
        return Inertia::render('Tenant/Print/DeliveryNote', [
            'note' => PrintableDeliveryNoteResource::make($deliveryNote->load(['items', 'sale']), $deliveryNote->print_count > 1)->resolve(),
        ]);
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
            'lines' => $this->notes->deliverableLines($sale),
        ];
    }

    /**
     * @param  Closure(): string  $step  returns the success message
     */
    private function perform(Closure $step): RedirectResponse
    {
        try {
            return back()->with('success', $step());
        } catch (DeliveryNoteException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
