<?php

namespace App\Exceptions;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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

    /**
     * A sealed pack (a roll) that was cut cannot be sold whole again, so the total in the base unit can be enough while the
     * whole packs are not.
     */
    public static function insufficientSealedPacks(string $productName, string $packUnitCode, int $intactPacks): self
    {
        return new self("{$productName}: {$packUnitCode} utuh tidak cukup, tersisa {$intactPacks} {$packUnitCode} utuh. Sisanya potongan eceran, jual per satuan yang lebih kecil.");
    }

    public static function sealedPackNotWhole(string $productName, string $packUnitCode): self
    {
        return new self("{$productName} dijual per {$packUnitCode} utuh, jumlahnya harus bilangan bulat. Untuk sebagian, jual per satuan yang lebih kecil.");
    }

    public static function quantityText(BigDecimal $quantity, string $unitCode): string
    {
        $text = rtrim(rtrim((string) $quantity->toScale(3), '0'), '.');

        return "{$text} {$unitCode}";
    }

    /**
     * The total is the one the server worked out, which can differ from the screen's when a price changed after the
     * item went into the cart, so it is named: the cashier can see why an exact payment was refused.
     */
    public static function paymentTooLow(BigDecimal $total): self
    {
        $shownTotal = number_format((float) (string) $total, $total->isEqualTo($total->toScale(0, RoundingMode::Down)) ? 0 : 2, ',', '.');

        return new self("Pembayaran kurang dari total belanja Rp {$shownTotal}. Harga di layar mungkin sudah berubah: cari ulang barangnya, lalu coba lagi.");
    }

    public static function productIsBeingCounted(string $productName): self
    {
        return new self("{$productName} sedang dihitung di stok opname dan belum bisa dijual. Hapus dari keranjang, atau tunggu opname selesai.");
    }

    public static function deliveryNoteRequired(): self
    {
        return new self('Nota COD harus punya surat jalan. Isi penerima dan alamat pengantaran.');
    }

    public static function codMoreThanOnce(): self
    {
        return new self('COD hanya boleh satu baris pembayaran.');
    }

    /**
     * COD is what is left to pay on delivery, so the other payments and the COD add up to the total exactly: nothing
     * to give change on.
     */
    public static function codMustBeTheRest(): self
    {
        return new self('Jumlah COD harus sisa tagihan setelah pembayaran lain, tidak lebih dan tidak kurang.');
    }

    public static function codWithOrderDeposit(): self
    {
        return new self('COD tidak bisa digabung dengan uang muka pesanan. Bayar uang muka dengan tunai, transfer, QRIS, atau EDC.');
    }

    public static function orderIsInAnotherWarehouse(): self
    {
        return new self('Barang pesanan ini ada di gudang lain. Ambil di kasir gudang tempat pesanan dibuat.');
    }

    public static function changeExceedsCash(): self
    {
        return new self('Kembalian hanya bisa diberikan dari pembayaran tunai.');
    }
}
