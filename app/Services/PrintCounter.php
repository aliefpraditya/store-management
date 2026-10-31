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
     * Notes one more print of the document, atomically so two registers printing at once both count.
     *
     * @return bool true when this print is a copy, that is the document was printed before
     */
    public function record(Sale|DeliveryNote $document): bool
    {
        return DB::transaction(function () use ($document): bool {
            $locked = $document::query()->lockForUpdate()->findOrFail($document->id);
            $isCopy = $locked->print_count > 0;

            $locked->increment('print_count');

            return $isCopy;
        });
    }
}
