<?php

namespace App\Services;

use App\Models\DeliveryNote;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Counts how often a printable document (a receipt, a delivery note) was printed. The first print is the original;
 * every later one is a copy and is marked SALINAN on paper.
 */
class PrintCounter
{
    /**
     * Notes one more print of the document, atomically so two registers printing at once both count, and answers
     * which print this was (1 for the original). A sheet opened for that exact print can then say SALINAN or not by
     * itself, instead of looking at a count that later prints have moved on.
     */
    public function recordPrint(Sale|DeliveryNote $document): int
    {
        return DB::transaction(function () use ($document): int {
            $lockedDocument = $document::query()->lockForUpdate()->findOrFail($document->id);

            $lockedDocument->increment('print_count');

            return $lockedDocument->print_count;
        });
    }
}
