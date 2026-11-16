<?php

namespace App\Exceptions;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Exception;

/**
 * A shift step that is not allowed right now. The message is safe to show to a cashier.
 */
class ShiftException extends Exception
{
    /** Added to the messages that ask for an open shift: a shift that was counted but not yet decided is not open either. */
    public const AWAITING_REVIEW_HINT = 'Bila shift Anda sudah dihitung, tunggu keputusan Admin.';

    public static function registerInUse(string $registerName, string $cashierName): self
    {
        return new self("{$registerName} sedang dipakai {$cashierName}. Tutup shift itu dulu.");
    }

    public static function alreadyOpen(): self
    {
        return new self('Anda masih punya shift yang terbuka.');
    }

    public static function notOpen(): self
    {
        return new self('Shift ini sudah ditutup.');
    }

    public static function noRegister(): self
    {
        return new self('Belum ada register yang aktif untuk toko ini.');
    }

    public static function notYours(string $cashierName): self
    {
        return new self("Shift ini dibuka oleh {$cashierName}.");
    }

    public static function hasPendingReturns(int $count): self
    {
        return new self("Masih ada {$count} retur yang menunggu persetujuan Admin. Selesaikan dulu sebelum menutup shift.");
    }

    public static function alreadyAwaitingReview(): self
    {
        return new self('Hitungan shift ini sudah tercatat dan menunggu review Admin.');
    }

    public static function registerAwaitingReview(string $registerName): self
    {
        return new self("Shift sebelumnya di {$registerName} masih menunggu review Admin karena kasnya tidak cocok. Minta Admin memutuskannya dulu, atau pakai kasir lain.");
    }

    public static function differenceNotPending(string $statusLabel): self
    {
        return new self("Selisih kas shift ini berstatus {$statusLabel}, jadi tidak bisa diputuskan lagi.");
    }

    public static function cannotDecideOwnShift(): self
    {
        return new self('Selisih kas shift Anda sendiri harus diputuskan oleh yang berwenang: Admin Toko untuk shift kasir, Super Admin untuk shift Admin Toko.');
    }

    public static function cannotDecideShiftYouClosed(): self
    {
        return new self('Anda yang menutup shift ini, jadi selisih kasnya diputuskan Admin lain atau Super Admin.');
    }

    public static function onlySuperAdminDecidesAnAdminShift(): self
    {
        return new self('Selisih kas shift Admin Toko hanya bisa diputuskan Super Admin.');
    }

    public static function dayClosed(string $dateText): self
    {
        return new self("Hari {$dateText} sudah ditutup. Minta Super Admin membuka kembali hari itu sebelum membuka shift.");
    }

    public static function openingCashNeedsNote(string $carriedOverCash): self
    {
        return new self('Modal awal berbeda dari sisa laci shift sebelumnya (Rp '.number_format((int) (string) BigDecimal::of($carriedOverCash)->toScale(0, RoundingMode::HalfUp), 0, ',', '.').'). Isi alasannya.');
    }

    public static function drawerLeftExceedsCounted(): self
    {
        return new self('Uang yang ditinggal di laci tidak boleh lebih dari tunai yang dihitung.');
    }
}
