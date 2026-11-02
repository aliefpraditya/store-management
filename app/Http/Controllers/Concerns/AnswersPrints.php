<?php

namespace App\Http\Controllers\Concerns;

use App\Models\DeliveryNote;
use App\Models\Sale;
use App\Services\PrintCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * The two halves of printing a document, shared by receipts and delivery notes. Counting a print and showing the sheet
 * are separate requests, so the sheet's address is signed and carries the number of the print that was counted: the
 * sheet says SALINAN for exactly that print, and it cannot be opened (and printed) without being counted first.
 */
trait AnswersPrints
{
    /**
     * Notes one more print and answers the signed address of the sheet for it.
     *
     * @param  string  $sheetRouteName  the route of the sheet in the caller's area
     * @param  string  $routeParameterName  the sheet route's parameter for the document, e.g. `sale`
     */
    protected function recordPrintAnswer(PrintCounter $printCounter, Sale|DeliveryNote $document, string $sheetRouteName, string $routeParameterName): JsonResponse
    {
        $printNumber = $printCounter->recordPrint($document);

        return response()->json(['data' => [
            'is_copy' => $printNumber > 1,
            'print_count' => $printNumber,
            'sheet_url' => URL::temporarySignedRoute($sheetRouteName, now()->addMinutes(30), [
                'tenant' => tenant('code'),
                $routeParameterName => $document->id,
                'print' => $printNumber,
            ]),
        ]]);
    }

    /**
     * Which print a sheet was opened for (1 is the original); the signature keeps the number from being changed.
     */
    protected function printNumberOf(Request $request): int
    {
        return max(1, $request->integer('print', 1));
    }
}
