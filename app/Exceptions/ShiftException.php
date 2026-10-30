<?php

namespace App\Exceptions;

use Exception;

/**
 * A shift step that is not allowed right now. The message is safe to show to a cashier.
 */
class ShiftException extends Exception
{
    public static function registerInUse(string $registerName, string $cashierName): self
    {
        return new self("{$registerName} sedang dipakai {$cashierName}. Tutup shift itu dulu.");
    }

    public static function alreadyOpen(): self
    {
        return new self('Anda masih punya shift yang terbuka.');
    }

    public static function notOpen(): self
    {
        return new self('Shift ini sudah ditutup.');
    }

    public static function noRegister(): self
    {
        return new self('Belum ada register yang aktif untuk toko ini.');
    }

    public static function notYours(string $cashierName): self
    {
        return new self("Shift ini dibuka oleh {$cashierName}.");
    }

    public static function differenceNeedsNote(): self
    {
        return new self('Ada selisih kas, isi catatan penutupan.');
    }
}
