<?php

namespace App\Http\Requests\Tenant;

use App\Models\Sale;

/**
 * A return made from the till: the receipt is the one in the address, so it is not sent in the body.
 */
class SaveCashierSaleReturnRequest extends SaveSaleReturnRequest
{
    protected function prepareForValidation(): void
    {
        /** @var Sale $sale */
        $sale = $this->route('sale');

        $this->merge(['sale_id' => $sale->id]);
    }
}
