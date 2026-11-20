<?php

namespace App\Exceptions;

use Exception;

/**
 * A stock opname step that is not allowed right now. The message is safe to show to an admin.
 */
class StockOpnameException extends Exception
{
    private const PROBLEMS_SHOWN = 5;

    public static function wrongStatus(string $action, string $statusLabel): self
    {
        return new self("Opname berstatus {$statusLabel}, jadi tidak bisa {$action}.");
    }

    public static function productIsBusy(string $productName): self
    {
        return new self("{$productName} sedang dihitung di opname lain untuk gudang ini.");
    }

    public static function damageNeedsSellableWarehouse(): self
    {
        return new self('Alasan Rusak hanya untuk opname gudang jual: barang DG dibuat di gudang yang sama dengan opname, dan dari gudang dead stock atau retur tidak bisa dijual.');
    }

    public static function quantityTooLarge(string $productName): self
    {
        return new self("{$productName}: jumlah yang dihitung terlalu besar setelah diubah ke satuan terkecil. Periksa angkanya.");
    }

    public static function sealedPacksNotWhole(string $productName, string $packUnitCode): self
    {
        return new self("{$productName}: jumlah {$packUnitCode} yang dihitung harus bilangan bulat, karena hanya yang utuh dihitung {$packUnitCode}. Tulis potongannya dengan satuan yang lebih kecil.");
    }

    public static function notAReturnsWarehouse(): self
    {
        return new self('Gudang ini bukan gudang retur, jadi barangnya tidak bisa dilepas ke gudang asal.');
    }

    public static function noMatchingProducts(): self
    {
        return new self('Tidak ada barang untuk kategori dan merek itu.');
    }

    /**
     * @param  list<string>  $productNames
     */
    public static function largeVarianceNeedsNote(array $productNames, string $thresholdText): self
    {
        $names = implode(', ', array_slice($productNames, 0, self::PROBLEMS_SHOWN));
        $others = count($productNames) - self::PROBLEMS_SHOWN;
        $namesText = $others > 0 ? "{$names} dan {$others} barang lain" : $names;

        return new self("Selisih {$namesText} bernilai di atas Rp {$thresholdText}, jadi wajib diberi catatan. Klik Hitung Ulang, isi catatannya di baris barang itu, lalu selesaikan hitung dan posting lagi.");
    }

    /**
     * @param  list<string>  $problems  each one names the product and says what is missing
     */
    public static function reviewIncomplete(array $problems): self
    {
        $shown = array_slice($problems, 0, self::PROBLEMS_SHOWN);
        $others = count($problems) - count($shown);

        return new self('Selisih belum selesai direview: '.implode('; ', $shown).($others > 0 ? "; dan {$others} barang lain" : '').'.');
    }

    public static function cannotReview(string $productName, string $problem): self
    {
        return new self("{$productName}: {$problem}");
    }

    public static function reasonNotAskedPerItem(): self
    {
        return new self('Opname ini tidak meminta alasan per barang.');
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
