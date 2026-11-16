<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use Throwable;

/**
 * Tests whether a store's own database credentials work, without touching the default connection
 * (which belongs to the central database or to whichever store is currently initialized).
 */
class StoreConnectionChecker
{
    private const CHECK_CONNECTION = 'store_connection_check';

    private const MAX_MESSAGE_LENGTH = 500;

    /** MySQL driver error numbers a super admin can act on, with the advice to show. */
    private const FAILURE_HINTS = [
        1049 => 'Database belum dibuat di hPanel.',
        1045 => 'Username atau password database salah.',
        1044 => 'Username database tidak punya akses ke database ini.',
        2002 => 'Server database tidak bisa dihubungi; periksa host database.',
    ];

    /**
     * @throws PDOException when the store's database cannot be reached with its current credentials
     */
    public function assertCanConnect(Store $store): void
    {
        config(['database.connections.'.self::CHECK_CONNECTION => $store->database()->connection()]);
        DB::purge(self::CHECK_CONNECTION);

        try {
            DB::connection(self::CHECK_CONNECTION)->getPdo();
        } finally {
            DB::purge(self::CHECK_CONNECTION);
        }
    }

    /**
     * Advice first when the database error is a known one, the raw error after it for whoever has to dig deeper.
     */
    public function describeFailure(Throwable $exception): string
    {
        $rawMessage = $exception->getMessage();
        $hint = preg_match('/\[(\d{4})\]/', $rawMessage, $matches) === 1 ? (self::FAILURE_HINTS[(int) $matches[1]] ?? null) : null;

        return Str::limit($hint === null ? $rawMessage : "{$hint} ({$rawMessage})", self::MAX_MESSAGE_LENGTH);
    }
}
