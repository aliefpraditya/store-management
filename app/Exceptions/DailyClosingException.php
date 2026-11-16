<?php

namespace App\Exceptions;

use Carbon\CarbonInterface;
use Exception;

/**
 * A day-closing step that is not allowed right now. The message is safe to show to an admin.
 */
class DailyClosingException extends Exception
{
    public static function alreadyClosed(CarbonInterface $date): self
    {
        return new self("Hari {$date->format('d/m/Y')} sudah ditutup.");
    }

    public static function notReady(CarbonInterface $date): self
    {
        return new self("Hari {$date->format('d/m/Y')} belum bisa ditutup. Selesaikan dulu shift yang masih terbuka, selisih kas yang belum diputuskan, atau retur yang menunggu persetujuan, lalu coba lagi.");
    }

    public static function futureDate(): self
    {
        return new self('Hari yang belum tiba tidak bisa ditutup.');
    }

    public static function notClosed(CarbonInterface $date): self
    {
        return new self("Hari {$date->format('d/m/Y')} tidak sedang berstatus tutup, jadi tidak bisa dibuka kembali.");
    }
}
