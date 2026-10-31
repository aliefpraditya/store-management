<?php

namespace App\Http\Requests\Tenant;

use App\Models\Sale;

/**
 * A delivery note made from the till: the receipt is the one in the address, so it is not sent in the body.
 */
class SaveCashierDeliveryNoteRequest extends SaveDeliveryNoteRequest
{
    protected function prepareForValidation(): void
    {
        /** @var Sale $sale */
        $sale = $this->route('sale');

        $this->merge(['sale_id' => $sale->id]);
    }
}
