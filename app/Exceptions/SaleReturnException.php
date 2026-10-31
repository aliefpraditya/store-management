<?php

namespace App\Exceptions;

use Exception;

/**
 * A return step that is not allowed right now. The message is safe to show to a cashier or admin.
 */
class SaleReturnException extends Exception
{
    public static function saleNotCompleted(): self
    {
        return new self('Retur hanya bisa dibuat dari nota yang selesai, bukan nota void.');
    }

    public static function noItems(): self
    {
        return new self('Pilih minimal satu barang yang diretur.');
    }

    public static function lineNotOnSale(): self
    {
        return new self('Ada barang yang tidak ada di nota ini.');
    }

    public static function exceedsReturnable(string $productName, string $remainingText): self
    {
        return new self("{$productName} melebihi yang masih bisa diretur, sisa {$remainingText}.");
    }

    public static function refundAccountMissing(): self
    {
        return new self('Isi rekening penerima untuk pengembalian uang non tunai.');
    }

    public static function shiftNotOpen(): self
    {
        return new self('Buka shift dulu untuk membuat retur.');
    }

    public static function noWarehouseFor(string $dispositionLabel): self
    {
        return new self("Gudang untuk \"{$dispositionLabel}\" belum ada di toko ini.");
    }

    /**
     * A problem with the replacement goods or their payment, in the words of the sale.
     */
    public static function fromSale(SaleException $exception): self
    {
        return new self($exception->getMessage(), previous: $exception);
    }

    public static function wrongStatus(string $action, string $statusLabel): self
    {
        return new self("Retur berstatus {$statusLabel}, jadi tidak bisa {$action}.");
    }
}
