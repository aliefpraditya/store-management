<?php

namespace App\Exceptions;

use Exception;

/**
 * A step on a customer order that is not allowed right now. The message is safe to show to a cashier or admin.
 */
class CustomerOrderException extends Exception
{
    public static function shiftNotOpen(): self
    {
        return new self('Buka shift dulu sebelum mencatat pesanan. '.ShiftException::AWAITING_REVIEW_HINT);
    }

    public static function orderIdInUse(): self
    {
        return new self('Kode pesanan ini sudah dipakai transaksi lain. Muat ulang halaman lalu coba lagi.');
    }

    public static function depositMoreThanTotal(): self
    {
        return new self('Uang muka tidak boleh lebih besar dari total pesanan.');
    }

    public static function alreadyFinished(string $statusLabel): self
    {
        return new self("Pesanan ini berstatus {$statusLabel}, jadi tidak bisa diproses lagi. Muat ulang daftar pesanan.");
    }

    public static function notOrderedYet(string $statusLabel): self
    {
        return new self("Pesanan ini berstatus {$statusLabel}. Hanya pesanan yang masih dipesan yang bisa ditandai barang datang.");
    }

    public static function notReadyToCollect(string $statusLabel): self
    {
        return new self("Pesanan ini berstatus {$statusLabel}. Hanya pesanan yang siap diambil yang bisa dibayar dan diambil.");
    }

    /**
     * The goods are meant to come in through a stock opname first, so the order can be set aside from real stock.
     */
    public static function goodsNotInStock(string $productName, string $availableText): self
    {
        return new self("Stok {$productName} belum cukup untuk pesanan ini, tersedia {$availableText}. Masukkan barang yang datang lewat stok opname dulu.");
    }

    /**
     * The metres are there, but not as many whole packs (rolls) as the order asks for, after the ones other orders hold.
     */
    public static function packsNotInStock(string $productName, string $packUnitCode, int $freePacks): self
    {
        return new self("{$productName}: {$packUnitCode} utuh belum cukup untuk pesanan ini, tersedia {$freePacks} {$packUnitCode} utuh. Masukkan barang yang datang lewat stok opname dulu.");
    }

    public static function shiftNeededForRefund(): self
    {
        return new self('Pesanan ini sudah menerima uang muka. Buka shift dulu untuk mengembalikannya. '.ShiftException::AWAITING_REVIEW_HINT);
    }

    public static function refundAccountNeeded(): self
    {
        return new self('Uang muka dibayar non tunai. Isi rekening penerima pengembaliannya.');
    }

    public static function dayClosed(string $dateText): self
    {
        return new self("Hari {$dateText} sudah ditutup, jadi uang muka belum bisa dikembalikan hari ini. Minta Super Admin membuka kembali hari itu.");
    }
}
