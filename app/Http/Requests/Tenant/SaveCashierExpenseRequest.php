<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Support\Arr;

/**
 * An expense from the till: it always comes out of the cashier's own drawer, so the source, the shift and the day are
 * not sent.
 */
class SaveCashierExpenseRequest extends SaveExpenseRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['source', 'cash_shift_id', 'business_date']);
    }
}
