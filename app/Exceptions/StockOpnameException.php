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

    public static function quantityTooLarge(string $productName): self
    {
        return new self("{$productName}: jumlah yang dihitung terlalu besar setelah diubah ke satuan terkecil. Periksa angkanya.");
    }

    public static function noProducts(): self
    {
        return new self('Pilih minimal satu barang sebelum mulai menghitung.');
    }

    public static function noMatchingProducts(): self
    {
        return new self('Tidak ada barang untuk kategori dan merek itu.');
    }

    /**
     * @param  list<string>  $productNames
     */
    public static function largeVarianceNeedsReason(array $productNames, string $thresholdText): self
    {
        $names = implode(', ', $productNames);

        return new self("Selisih {$names} bernilai di atas Rp {$thresholdText}, jadi wajib diberi alasan. Isi alasannya di baris barang itu, lalu posting lagi.");
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
