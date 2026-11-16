<?php

namespace App\Exceptions;

use Exception;

/**
 * A product with stock or history cannot be deleted. The message is safe to show to an admin.
 */
class ProductHasTransactionsException extends Exception
{
    public static function forProduct(string $name): self
    {
        return new self("{$name} sudah punya stok atau transaksi, jadi tidak bisa dihapus. Nonaktifkan saja.");
    }
}
