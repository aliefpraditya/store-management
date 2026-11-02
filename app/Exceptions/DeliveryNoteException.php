<?php

namespace App\Exceptions;

use Exception;

/**
 * A delivery note step that is not allowed right now. The message is safe to show to an admin.
 */
class DeliveryNoteException extends Exception
{
    public static function saleNotCompleted(): self
    {
        return new self('Surat jalan hanya bisa dibuat dari nota yang selesai, bukan nota void.');
    }

    public static function noItems(): self
    {
        return new self('Pilih minimal satu barang yang diantar.');
    }

    public static function lineNotOnSale(): self
    {
        return new self('Ada barang yang tidak ada di nota ini.');
    }

    public static function exceedsRemaining(string $productName, string $remainingText): self
    {
        return new self("{$productName} melebihi sisa nota yang belum diantar, sisa {$remainingText}.");
    }

    public static function wrongStatus(string $action, string $statusLabel): self
    {
        return new self("Surat jalan tidak bisa {$action} karena berstatus {$statusLabel}. Muat ulang halaman untuk melihat status terbarunya.");
    }

    public static function noteIdInUse(): self
    {
        return new self('Pengajuan surat jalan ini sudah tercatat untuk orang atau nota lain. Muat ulang layar lalu coba lagi.');
    }

    public static function resultNotOnNote(): self
    {
        return new self('Ada hasil untuk barang yang tidak ada di surat jalan ini, atau barang yang sama diisi dua kali.');
    }

    public static function resultDoesNotAddUp(string $productName): self
    {
        return new self("{$productName}: jumlah terkirim ditambah kembali harus sama dengan jumlah yang dikirim.");
    }

    public static function resultMissing(): self
    {
        return new self('Isi hasil pengiriman untuk setiap barang.');
    }
}
