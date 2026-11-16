<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\DeliveryNoteException;
use App\Http\Controllers\Concerns\PrintsDeliveryNotes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveCashierDeliveryNoteRequest;
use App\Http\Resources\CashierDeliveryNoteResource;
use App\Models\DeliveryNote;
use App\Models\Sale;
use App\Models\Store;
use App\Services\DeliveryNoteService;
use App\Services\PrintCounter;
use App\Services\StoreSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delivery notes from the till: a cashier makes and prints them for their own receipts, without leaving the
 * cashier screen. Following a note through shipping and delivery stays with the admin pages.
 */
class CashierDeliveryNoteController extends Controller
{
    use PrintsDeliveryNotes;

    public function __construct(
        private readonly DeliveryNoteService $noteService,
        private readonly PrintCounter $printCounter,
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
            'lines' => $this->noteService->deliverableLines($sale),
            'notes' => CashierDeliveryNoteResource::collection($this->noteService->notesOfSale($sale))->resolve(),
        ]]);
    }

    public function store(SaveCashierDeliveryNoteRequest $request, Sale $sale): JsonResponse
    {
        try {
            $note = $this->noteService->create($sale, $request->user(), $request->validated());
        } catch (DeliveryNoteException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => CashierDeliveryNoteResource::make($note)->resolve()], Response::HTTP_CREATED);
    }

    /**
     * Notes one more print and answers the sheet to open in a new window. A cashier may print the notes of their own receipts.
     */
    public function print(Request $request, DeliveryNote $deliveryNote): JsonResponse
    {
        $this->authorizeFor($request, $deliveryNote->sale);

        return $this->recordPrintOf($this->printCounter, $deliveryNote, 'tenant.cashier.delivery-notes.sheet');
    }

    public function sheet(Request $request, DeliveryNote $deliveryNote, StoreSettingService $settings): InertiaResponse
    {
        $this->authorizeFor($request, $deliveryNote->sale);

        return $this->sheetOf($request, $deliveryNote, $settings->letterhead());
    }

    private function authorizeFor(Request $request, Sale $sale): void
    {
        /** @var Store $store */
        $store = tenant();

        abort_unless($request->user()->canHandleReceipt($sale, $store), 403);
    }
}
