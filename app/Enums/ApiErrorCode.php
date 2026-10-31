<?php

namespace App\Enums;

/**
 * The internal `code` of the JSON error body (`code`, `message`, `more_info`). The first digits are the HTTP status,
 * so a code is never reused for a different meaning on another endpoint.
 */
enum ApiErrorCode: int
{
    case ShiftNotOpen = 4091;
    case ShiftIsOpen = 4231;
}
