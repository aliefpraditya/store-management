<?php

namespace App\Services;

use App\Models\Register;
use Brick\Math\BigDecimal;

/**
 * The cash registers of a store. A register's fixed opening cash is the cash every shift on it starts with; a shift
 * copies it when it opens, so changing it only reaches the shifts opened afterwards.
 */
class RegisterService
{
    /**
     * @param  numeric-string|int|float  $fixedOpeningCash
     */
    public function setFixedOpeningCash(Register $register, string|int|float $fixedOpeningCash): Register
    {
        $register->forceFill(['fixed_opening_cash' => (string) BigDecimal::of((string) $fixedOpeningCash)->toScale(2)])->save();

        return $register;
    }
}
