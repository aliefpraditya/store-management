<?php

namespace App\Enums;

/**
 * What kind of item waits for an admin's decision on the store dashboard. The values are the keys the
 * dashboard page uses to pick a label and a link.
 */
enum ApprovalKind: string
{
    case SaleReturn = 'return';
    case CashDifference = 'cash_difference';
}
