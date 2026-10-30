<?php

namespace App\Exceptions;

use Exception;

/**
 * A stock opname step that is not allowed right now. The message is safe to show to an admin.
 */
class StockOpnameException extends Exception
{
    public static function wrongStatus(string $action, string $statusLabel): self
    {
        return new self("Opname berstatus {$statusLabel}, jadi tidak bisa {$action}.");
    }

    public static function productIsBusy(string $productName): self
    {
        return new self("{$productName} sedang dihitung di opname lain untuk gudang ini.");
    }

    public static function noProducts(): self
    {
        return new self('Pilih minimal satu barang sebelum mulai menghitung.');
    }

    public static function noMatchingProducts(): self
    {
        return new self('Tidak ada barang untuk kategori dan merek itu.');
    }

    public static function nothingCounted(): self
    {
        return new self('Belum ada barang yang dihitung.');
    }

    public static function notSelectedScope(): self
    {
        return new self('Barang hanya bisa ditambah atau dihapus pada opname dengan cakupan barang terpilih.');
    }
}
