<?php

namespace App\Exceptions;

use Exception;

/**
 * A unit that products are sold in cannot be deleted. The message is safe to show to an admin.
 */
class UnitInUseException extends Exception
{
    public static function forUnit(string $code): self
    {
        return new self("Satuan {$code} masih dipakai barang, jadi tidak bisa dihapus.");
    }
}
