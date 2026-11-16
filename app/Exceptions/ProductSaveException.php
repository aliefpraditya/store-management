<?php

namespace App\Exceptions;

use Exception;

/**
 * A product could not be saved as it was sent. The message is safe to show to an admin.
 */
class ProductSaveException extends Exception
{
    public static function changedByAnother(): self
    {
        return new self('Barang ini diubah orang lain sejak halaman dibuka. Muat ulang halaman, lalu isi lagi perubahan Anda.');
    }

    /**
     * The rules about units and conversions that hold once a product has stock or history, checked again under the
     * product's lock because a sale can land between the form's validation and the save.
     */
    public static function unitsLocked(string $problem): self
    {
        return new self($problem);
    }
}
