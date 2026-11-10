<?php

namespace App\Exceptions;

use Exception;

/**
 * A step on a COD sale (marking it delivered, voiding it) that is not allowed right now. The message is safe to show
 * to a cashier or admin.
 */
class CodSaleException extends Exception
{
    public static function notCod(): self
    {
        return new self('Hanya nota COD yang bisa diproses di sini. Nota lain memakai Retur.');
    }

    public static function alreadyVoided(): self
    {
        return new self('Nota ini sudah di-void. Muat ulang daftar COD untuk melihat keadaan terbaru.');
    }

    public static function notCompleted(string $statusLabel): self
    {
        return new self("Nota ini berstatus {$statusLabel}, jadi tidak bisa diproses lagi.");
    }

    public static function alreadyDelivered(): self
    {
        return new self('Barang nota ini sudah diantar dan diterima pelanggan, jadi tidak bisa di-void. Pakai Retur untuk barang yang dikembalikan.');
    }

    public static function onCombinedDeliveryNote(): self
    {
        return new self('Barang nota ini ikut di surat jalan gabungan milik nota pengambilan pesanan. Void nota pengambilan itu dulu (surat jalannya ikut dibatalkan), atau batalkan surat jalan gabungannya.');
    }

    public static function hasReturns(): self
    {
        return new self('Nota ini sudah punya retur, jadi tidak bisa di-void.');
    }

    public static function paidByReturnCredit(): self
    {
        return new self('Nota pengganti retur tidak bisa di-void. Pakai Retur.');
    }

    public static function paidByOrderDeposit(): self
    {
        return new self('Nota pengambilan pesanan tidak bisa di-void. Pakai Retur.');
    }

    public static function shiftNeededForRefund(): self
    {
        return new self('Nota ini sudah menerima DP tunai. Buka shift dulu untuk mengembalikannya dari laci. '.ShiftException::AWAITING_REVIEW_HINT);
    }

    public static function refundAccountNeeded(): self
    {
        return new self('Nota ini sudah menerima DP non tunai. Isi rekening penerima pengembaliannya.');
    }

    public static function dayClosed(string $dateText): self
    {
        return new self("Hari {$dateText} sudah ditutup, jadi void hari ini belum bisa dicatat. Minta Super Admin membuka kembali hari itu.");
    }

    public static function nothingToDeliver(): self
    {
        return new self('Nota ini tidak punya surat jalan yang masih berjalan.');
    }
}
