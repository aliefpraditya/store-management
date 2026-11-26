<?php

namespace App\Exceptions;

use Exception;

/**
 * A shipment step that is not allowed right now. The message is safe to show to an admin.
 */
class ShipmentException extends Exception
{
    public static function notEditable(): self
    {
        return new self('Pengiriman ini sudah berangkat atau dibatalkan, jadi tidak bisa diubah lagi.');
    }

    public static function carrierInactive(): self
    {
        return new self('Ekspedisi ini sudah dinonaktifkan. Pilih ekspedisi lain.');
    }

    public static function purchaseNotAvailable(string $purchaseNumber): self
    {
        return new self("Nota {$purchaseNumber} tidak bisa dimuat: notanya harus sudah dipesan (dibayar) dan belum masuk pengiriman lain.");
    }

    public static function itemNotInShipment(): self
    {
        return new self('Ada baris packing list yang barangnya bukan dari nota di pengiriman ini. Muat ulang layar lalu coba lagi.');
    }

    public static function overAllocated(string $productName, string $ordered): self
    {
        return new self("Jumlah {$productName} di container melebihi jumlah di nota ({$ordered}).");
    }

    public static function notFullyAllocated(string $purchaseNumber, string $productName): self
    {
        return new self("Nota {$purchaseNumber}: jumlah {$productName} di container belum sama dengan jumlah di nota. Semua barang nota harus masuk container sebelum diberangkatkan.");
    }

    public static function withoutContainers(): self
    {
        return new self('Isi packing list dulu: belum ada barang di container.');
    }

    public static function shipmentIdInUse(): self
    {
        return new self('Pengiriman ini sudah dibuat oleh orang lain. Muat ulang layar lalu coba lagi.');
    }

    public static function tooManyAttachments(int $maximum): self
    {
        return new self("Satu pengiriman paling banyak punya {$maximum} file.");
    }

    public static function attachmentsLocked(): self
    {
        return new self('Pengiriman yang dibatalkan tidak bisa diubah file-nya.');
    }

    public static function notCancellable(): self
    {
        return new self('Hanya pengiriman yang belum berangkat yang bisa dibatalkan.');
    }
}
