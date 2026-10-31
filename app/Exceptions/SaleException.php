<?php

namespace App\Exceptions;

use Exception;

/**
 * A sale that cannot be completed. The message is safe to show to a cashier.
 */
class SaleException extends Exception
{
    public static function shiftNotOpen(): self
    {
        return new self('Buka shift dulu sebelum berjualan.');
    }

    public static function notSellable(string $productName): self
    {
        return new self("{$productName} tidak bisa dijual dengan satuan itu. Pilih satuan lain.");
    }

    public static function productUnavailable(string $productName): self
    {
        return new self("{$productName} sudah tidak aktif atau dihapus. Hapus dari keranjang.");
    }

    public static function receiptIdInUse(): self
    {
        return new self('Kode transaksi ini sudah dipakai transaksi lain. Muat ulang halaman lalu coba lagi.');
    }

    public static function lineNotFound(): self
    {
        return new self('Ada barang di keranjang yang tidak ditemukan lagi. Muat ulang halaman lalu masukkan barangnya lagi.');
    }

    public static function insufficientStock(string $productName, string $availableText): self
    {
        return new self("Stok {$productName} tidak cukup, tersisa {$availableText}.");
    }

    public static function paymentTooLow(): self
    {
        return new self('Pembayaran kurang dari total belanja.');
    }

    public static function changeExceedsCash(): self
    {
        return new self('Kembalian hanya bisa diberikan dari pembayaran tunai.');
    }
}
