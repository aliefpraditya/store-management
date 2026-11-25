<?php

namespace App\Exceptions;

use Exception;

/**
 * A purchase step that is not allowed right now. The message is safe to show to an admin.
 */
class PurchaseException extends Exception
{
    public static function notEditable(): self
    {
        return new self('Nota ini sudah dikonfirmasi, jadi tidak bisa diubah lagi. Batalkan lalu buat nota baru bila ada yang salah.');
    }

    public static function notConfirmable(): self
    {
        return new self('Hanya nota draft yang bisa dikonfirmasi.');
    }

    public static function paymentRequired(): self
    {
        return new self('Nota ini harus dibayar saat dikonfirmasi. Pakai Konfirmasi & Bayar.');
    }

    public static function nothingToPay(): self
    {
        return new self('Nota ini tidak punya tagihan, jadi tidak ada yang dibayar. Cukup konfirmasi.');
    }

    public static function withoutItems(): self
    {
        return new self('Nota belum punya barang. Tambahkan barang dulu.');
    }

    public static function notCancellable(): self
    {
        return new self('Nota ini tidak bisa dibatalkan lagi karena barangnya sudah dikirim atau notanya sudah selesai.');
    }

    public static function supplierInactive(): self
    {
        return new self('Supplier ini sudah dinonaktifkan. Pilih supplier lain atau aktifkan kembali.');
    }

    public static function productUnavailable(): self
    {
        return new self('Ada barang di nota yang tidak ditemukan. Muat ulang layar lalu coba lagi.');
    }

    public static function productInactive(string $productName): self
    {
        return new self("Barang {$productName} sudah dinonaktifkan, jadi tidak bisa dibeli. Aktifkan dulu di Daftar Barang.");
    }

    public static function damagedGoodsNotBought(string $productName): self
    {
        return new self("{$productName} adalah barang DG (rusak) yang dibuat dari stok sendiri, jadi tidak dibeli dari supplier.");
    }

    public static function unitNotOfProduct(string $productName, string $unitCode): self
    {
        return new self("Satuan {$unitCode} bukan satuan barang {$productName}.");
    }

    public static function duplicateItem(string $productName, string $unitCode): self
    {
        return new self("{$productName} dalam satuan {$unitCode} muncul dua kali di nota. Gabungkan jadi satu baris.");
    }

    public static function purchaseIdInUse(): self
    {
        return new self('Nota ini sudah dibuat oleh orang lain. Muat ulang layar lalu coba lagi.');
    }

    public static function tooManyAttachments(int $maximum): self
    {
        return new self("Satu nota paling banyak punya {$maximum} lampiran.");
    }

    public static function attachmentsLocked(): self
    {
        return new self('Nota yang dibatalkan tidak bisa diubah lampirannya.');
    }
}
