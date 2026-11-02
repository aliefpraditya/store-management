<?php

namespace App\Enums;

/**
 * The internal `code` of the JSON error body (`code`, `message`, `more_info`). The first digits are the HTTP status,
 * so a code is never reused for a different meaning on another endpoint.
 */
enum ApiErrorCode: int
{
    case ReceiptNotYours = 4031;
    case ReceiptNotFound = 4041;
    case ShiftNotOpen = 4091;
    case ReturnRefused = 4221;
    case ExpenseRefused = 4222;
    case ShiftIsOpen = 4231;
}
