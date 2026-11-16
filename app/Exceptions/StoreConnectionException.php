<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * The new database credentials of a store do not work. The message is safe to show to a super admin.
 */
class StoreConnectionException extends Exception
{
    public static function withMessage(string $description, Throwable $previous): self
    {
        return new self("Toko tidak disimpan karena koneksi ke database gagal. {$description}", previous: $previous);
    }
}
