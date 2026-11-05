<?php

namespace App\Exceptions;

use Exception;

/**
 * A unit that a bulk price change cannot be applied to without a wrong result. The message says why, in words an
 * admin reads in the preview.
 */
class PriceChangeSkippedException extends Exception
{
    public static function negativeCost(): self
    {
        return new self('Modal akan jadi negatif.');
    }

    public static function negativePrice(): self
    {
        return new self('Harga jual akan jadi negatif.');
    }

    public static function marginNeedsCost(): self
    {
        return new self('Modal 0, harga dari margin tidak bisa dihitung.');
    }

    public static function currentMarginUnknown(): self
    {
        return new self('Modal atau harga jual sekarang 0, margin lama tidak bisa dihitung.');
    }
}
