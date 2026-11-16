<?php

namespace App\Exceptions;

use Exception;

/**
 * A store was asked to change status in a way its current status does not allow. The message is safe to show to a super admin.
 */
class StoreStatusException extends Exception
{
    public static function notReadyToToggle(): self
    {
        return new self('Toko ini belum selesai disiapkan.');
    }
}
