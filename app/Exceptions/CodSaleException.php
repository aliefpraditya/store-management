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

    public static function notForDelivery(): self
    {
        return new self('Nota ini diambil di toko, tidak ada yang diantar.');
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
        return new self('Barang nota ini ikut di surat jalan gabungan milik nota pengambilan pesanan. Batalkan surat jalan gabungannya dulu.');
    }

    public static function hasReturns(): self
    {
        return new self('Nota ini sudah punya retur, jadi tidak bisa di-void.');
    }

    public static function paidByReturnCredit(): self
    {
        return new self('Nota pengganti retur tidak bisa di-void. Pakai Retur.');
    }

    public static function collectsCustomerOrder(): self
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

    public static function notDeliveredYet(): self
    {
        return new self('Barang nota ini belum diantar, jadi uang pelunasannya belum bisa diterima. Tandai sudah diantar dulu, atau catat sebagai pembayaran di muka bila pelanggan baru membayar sebagian.');
    }

    public static function advanceReceived(): self
    {
        return new self('Nota ini sudah menerima pembayaran di muka, jadi tidak bisa di-void. Pakai Retur untuk barang yang tidak jadi diambil.');
    }

    public static function advanceAfterDelivery(): self
    {
        return new self('Barang nota ini sudah diantar. Terima uangnya sebagai pelunasan COD, bukan pembayaran di muka.');
    }

    public static function advanceMustBePartial(string $remainingText): self
    {
        return new self("Pembayaran di muka harus kurang dari sisa tagihan COD ({$remainingText}). Kalau pelanggan melunasi, nota ini bukan COD lagi.");
    }

    public static function alreadySettled(): self
    {
        return new self('Uang COD nota ini sudah diterima. Muat ulang daftar COD untuk melihat keadaan terbaru.');
    }

    public static function nothingToReceive(): self
    {
        return new self('Seluruh tagihan COD nota ini sudah terpotong retur, jadi tidak ada uang yang perlu diterima.');
    }

    public static function returnPending(): self
    {
        return new self('Nota ini punya retur yang menunggu persetujuan. Setujui atau tolak retur itu dulu, supaya jumlah yang harus diterima sudah pasti.');
    }

    public static function methodNotAccepted(): self
    {
        return new self('Uang COD hanya bisa diterima lewat tunai, transfer, QRIS, atau EDC.');
    }

    public static function differenceNeedsReason(): self
    {
        return new self('Jumlah yang diterima berbeda dari yang seharusnya. Isi alasan selisihnya.');
    }

    public static function shiftNeededForCash(): self
    {
        return new self('Uang tunai masuk ke laci, jadi buka shift dulu. '.ShiftException::AWAITING_REVIEW_HINT);
    }

    public static function settlementDayClosed(string $dateText): self
    {
        return new self("Hari {$dateText} sudah ditutup, jadi penerimaan uang hari ini belum bisa dicatat. Minta Super Admin membuka kembali hari itu.");
    }

    public static function settlementIdInUse(): self
    {
        return new self('Kode kirim ini sudah dipakai untuk penerimaan lain.');
    }

    public static function noSettlementToApprove(): self
    {
        return new self('Nota ini belum punya penerimaan uang COD yang perlu disetujui.');
    }

    public static function alreadyApproved(): self
    {
        return new self('Penerimaan uang COD nota ini sudah disetujui.');
    }
}
