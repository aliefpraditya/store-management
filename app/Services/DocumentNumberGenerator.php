<?php

namespace App\Services;

use App\Models\Register;
use App\Models\Store;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Hands out document numbers such as OPN/MU/2610/0002 or, for a register, INV/MU/K1/2610/0125: type, the store's
 * receipt prefix, the register code (receipts only), year-month, and a running number that restarts every month
 * (per register for receipts). Numbers are never reused, even if the document is cancelled.
 */
class DocumentNumberGenerator
{
    private const SEQUENCES_TABLE = 'document_sequences';

    private const NO_REGISTER_ID = 0;

    /**
     * Must run inside the caller's transaction so a rolled-back document does not skip a number.
     *
     * @param  string  $type  short document code, e.g. OPN
     * @param  Register|null  $register  for documents numbered per register, e.g. receipts
     */
    public function next(string $type, CarbonInterface $date, ?Register $register = null): string
    {
        $period = $date->format('ym');
        $key = ['type' => $type, 'register_id' => $register?->id ?? self::NO_REGISTER_ID, 'period' => $period];

        DB::table(self::SEQUENCES_TABLE)->insertOrIgnore([...$key, 'last_number' => 0]);

        $sequence = DB::table(self::SEQUENCES_TABLE)->where($key);
        $lastNumber = (int) (clone $sequence)->lockForUpdate()->value('last_number') + 1;
        $sequence->update(['last_number' => $lastNumber]);

        /** @var Store $store */
        $store = tenant();

        return implode('/', array_filter([$type, $store->receipt_prefix, $register?->code, $period, sprintf('%04d', $lastNumber)]));
    }
}
