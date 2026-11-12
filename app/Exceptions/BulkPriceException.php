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

    public static function previewOutdated(): self
    {
        return new self('Harga barang berubah sejak pratinjau dibuat, jadi belum ada yang disimpan. Pratinjau dihitung ulang: periksa lagi, lalu terapkan.');
    }
}
