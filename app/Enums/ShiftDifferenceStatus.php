<?php

namespace App\Enums;

enum ShiftDifferenceStatus: string
{
    case None = 'none';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
