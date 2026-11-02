<?php

namespace App\Exceptions;

use Exception;

/**
 * A store database could not be prepared. The message is safe to show to a super admin.
 */
class StoreProvisioningException extends Exception
{
    public static function alreadyReady(): self
    {
        return new self('Database toko ini sudah siap.');
    }

    public static function alreadyRunning(): self
    {
        return new self('Persiapan database toko ini sedang berjalan. Tunggu sampai selesai.');
    }
}
