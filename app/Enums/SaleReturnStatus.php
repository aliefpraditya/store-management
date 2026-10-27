<?php

namespace App\Enums;

enum SaleReturnStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Rejected = 'rejected';
}
