<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ApiErrorCode;
use Illuminate\Http\JsonResponse;

/**
 * The error body of the till's JSON endpoints: an internal `code`, the `message` the cashier reads, and `more_info`
 * on what to do next.
 */
trait AnswersJsonErrors
{
    private function errorResponse(ApiErrorCode $code, string $message, string $moreInfo, int $status): JsonResponse
    {
        return response()->json(['code' => $code->value, 'message' => $message, 'more_info' => $moreInfo], $status);
    }
}
