<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\DeliveryNoteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveCashierDeliveryNoteRequest;
use App\Http\Resources\PrintableDeliveryNoteResource;
use App\Models\DeliveryNote;
use App\Models\Sale;
use App\Models\Store;
use App\Services\DeliveryNoteService;
use App\Services\PrintCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delivery notes from the till: a cashier makes and prints them for their own receipts, without leaving the
 * cashier screen. Following a note through shipping and delivery stays with the admin pages.
 */
class CashierDeliveryNoteController extends Controller
{
    public function __construct(
        private readonly DeliveryNoteService $notes,
        private readonly PrintCounter $prints,
    ) {}

    /**
     * What the dialog needs for one receipt: the lines with what is still left to deliver, and the notes already made.
     */
    public function show(Request $request, Sale $sale): JsonResponse
    {
        $this->authorizeFor($request, $sale);

        $sale->load('items');

        return response()->json(['data' => [
            'number' => $sale->number,
            'customer_name' => $sale->customer_name,
            'lines' => $this->notes->deliverableLines($sale),
            'notes' => DeliveryNote::query()
                ->with('items')
                ->where('sale_id', $sale->id)
                ->orderBy('id')
                ->get()
                ->map(fn (DeliveryNote $note): array => $this->noteSummary($note))
                ->all(),
        ]]);
    }

    public function store(SaveCashierDeliveryNoteRequest $request, Sale $sale): JsonResponse
    {
        $this->authorizeFor($request, $sale);

        try {
            $note = $this->notes->create($sale, $request->user(), $request->validated());
        } catch (DeliveryNoteException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $this->noteSummary($note)], Response::HTTP_CREATED);
    }

    /**
     * Notes one more print. A cashier may print the notes of their own receipts. The browser then opens the sheet
     * in a new window, which shows the note as printed.
     */
    public function print(Request $request, DeliveryNote $deliveryNote): JsonResponse
    {
        $this->authorizeFor($request, $deliveryNote->sale);

        $isCopy = $this->prints->record($deliveryNote);

        return response()->json(['data' => ['is_copy' => $isCopy, 'print_count' => $deliveryNote->refresh()->print_count]]);
    }

    /**
     * The printable sheet, opened in its own window and printed there. From the second print on it says SALINAN.
     */
    public function sheet(Request $request, DeliveryNote $deliveryNote): InertiaResponse
    {
        $this->authorizeFor($request, $deliveryNote->sale);

        return Inertia::render('Tenant/Print/DeliveryNote', [
            'note' => PrintableDeliveryNoteResource::make($deliveryNote->load(['items', 'sale']), $deliveryNote->print_count > 1)->resolve(),
        ]);
    }

    /**
     * A cashier works with their own receipts; an admin with any.
     */
    private function authorizeFor(Request $request, Sale $sale): void
    {
        /** @var Store $store */
        $store = tenant();

        abort_unless($request->user()->isAdminIn($store) || $sale->cashier_id === $request->user()->id, 403);
    }

    /**
     * The items carry how much was sent and, once the delivery has ended, how much arrived and how much came back.
     *
     * @return array{id: int, number: string, status: string, status_label: string, print_count: int, print_url: string, sheet_url: string, items: list<array<string, mixed>>}
     */
    private function noteSummary(DeliveryNote $note): array
    {
        $note->loadMissing('items');

        return [
            'id' => $note->id,
            'number' => $note->number,
            'status' => $note->status->value,
            'status_label' => $note->status->label(),
            'print_count' => (int) $note->print_count,
            'print_url' => route('tenant.cashier.delivery-notes.print', ['tenant' => tenant('code'), 'delivery_note' => $note->id]),
            'sheet_url' => route('tenant.cashier.delivery-notes.sheet', ['tenant' => tenant('code'), 'delivery_note' => $note->id]),
            'items' => $note->items->map(fn ($item): array => [
                'sale_item_id' => $item->sale_item_id,
                'name' => $item->product_name,
                'unit' => $item->unit_name,
                'quantity' => $item->quantity,
                'quantity_delivered' => $item->quantity_delivered,
                'quantity_returned' => $item->quantity_returned,
            ])->values()->all(),
        ];
    }
}
