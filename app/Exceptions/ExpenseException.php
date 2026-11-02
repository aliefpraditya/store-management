<?php

namespace App\Exceptions;

use Exception;

/**
 * An expense step that is not allowed right now. The message is safe to show to a cashier or admin.
 */
class ExpenseException extends Exception
{
    public static function shiftNotOpen(): self
    {
        return new self('Buka shift dulu untuk mencatat pengeluaran dari laci kasir.');
    }

    public static function categoryInactive(): self
    {
        return new self('Kategori ini sudah dinonaktifkan. Pilih kategori lain.');
    }

    public static function expenseIdInUse(): self
    {
        return new self('Pengeluaran ini sudah tercatat untuk orang atau sumber lain. Muat ulang layar lalu coba lagi.');
    }

    public static function alreadyVoided(): self
    {
        return new self('Pengeluaran ini sudah dibatalkan.');
    }

    public static function drawerShiftClosed(): self
    {
        return new self('Shift pengeluaran ini sudah ditutup, jadi tidak bisa dibatalkan. Selisih kasnya diputuskan di Rekap Shift.');
    }
}
