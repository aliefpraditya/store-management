<?php

namespace App\Exceptions;

use Exception;

/**
 * A bulk price change that cannot be run at all. The message is safe to show to an admin.
 */
class BulkPriceException extends Exception
{
    public static function productsMissing(): self
    {
        return new self('Sebagian barang yang dipilih sudah tidak ada. Muat ulang halaman, lalu pilih lagi.');
    }
}
