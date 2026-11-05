<?php

namespace App\Exceptions;

use Exception;

/**
 * A shift step that is not allowed right now. The message is safe to show to a cashier.
 */
class ShiftException extends Exception
{
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

    public static function differenceNotPending(string $statusLabel): self
    {
        return new self("Selisih kas shift ini berstatus {$statusLabel}, jadi tidak bisa diputuskan lagi.");
    }

    public static function cannotDecideOwnShift(): self
    {
        return new self('Selisih kas shift Anda sendiri harus diputuskan Admin lain atau Super Admin.');
    }

    public static function notAwaitingFollowUp(): self
    {
        return new self('Hanya selisih kas yang ditolak dan belum ditindaklanjuti yang bisa ditandai.');
    }

    public static function dayClosed(string $dateText): self
    {
        return new self("Hari {$dateText} sudah ditutup. Minta Super Admin membuka kembali hari itu sebelum membuka shift.");
    }

    public static function differenceNeedsNote(): self
    {
        return new self('Ada selisih kas, isi catatan penutupan.');
    }

    public static function openingCashNeedsNote(string $carriedOverCash): self
    {
        return new self('Modal awal berbeda dari sisa laci shift sebelumnya (Rp '.number_format((float) $carriedOverCash, 0, ',', '.').'). Isi alasannya.');
    }

    public static function drawerLeftExceedsCounted(): self
    {
        return new self('Uang yang ditinggal di laci tidak boleh lebih dari tunai yang dihitung.');
    }
}
