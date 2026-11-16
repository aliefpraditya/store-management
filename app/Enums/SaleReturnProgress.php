<?php

namespace App\Enums;

/**
 * How much of a receipt has been taken back, counting only returns an admin approved (`sales.return_status`).
 */
enum SaleReturnProgress: string
{
    case None = 'none';
    case Partial = 'partial';
    case Full = 'full';
}
