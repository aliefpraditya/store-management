<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Resources\PrintableDeliveryNoteResource;
use App\Models\DeliveryNote;
use App\Services\PrintCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Printing a delivery note, shared by the admin pages and the till. See {@see AnswersPrints} for how a print is counted.
 */
trait PrintsDeliveryNotes
{
    use AnswersPrints;

    /**
     * Notes one more print and answers the signed address of the sheet for it.
     *
     * @param  string  $sheetRouteName  the route of the sheet in the caller's area (admin or till)
     */
    protected function recordPrintOf(PrintCounter $printCounter, DeliveryNote $deliveryNote, string $sheetRouteName): JsonResponse
    {
        return $this->recordPrintAnswer($printCounter, $deliveryNote, $sheetRouteName, 'delivery_note');
    }

    /**
     * The printable sheet, printed from a hidden frame. From the second print on it says SALINAN.
     *
     * @param  array{name: string, address: string|null, phone: string|null}  $letterhead  the store's letterhead, as the paper prints it
     */
    protected function sheetOf(Request $request, DeliveryNote $deliveryNote, array $letterhead): InertiaResponse
    {
        return Inertia::render('Tenant/Print/DeliveryNote', [
            'note' => PrintableDeliveryNoteResource::make($deliveryNote->load(['items', 'sale']), $this->printNumberOf($request) > 1, $letterhead)->resolve(),
        ]);
    }
}
