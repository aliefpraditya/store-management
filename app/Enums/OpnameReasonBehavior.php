<?php

namespace App\Enums;

/**
 * What choosing a reason asks of the admin who reviews the variance. A plain reason only needs to be chosen; damaged
 * goods also need a decision about where the damaged units go.
 */
enum OpnameReasonBehavior: string
{
    case Plain = 'plain';
    case Damaged = 'damaged';

    public function needsDamageDecision(): bool
    {
        return $this === self::Damaged;
    }
}
