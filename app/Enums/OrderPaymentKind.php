<?php

namespace App\Enums;

enum OrderPaymentKind: string
{
    case Deposit = 'deposit';
    case Refund = 'refund';
}
