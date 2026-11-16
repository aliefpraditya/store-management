<?php

namespace App\Http\Requests\Tenant;

use App\Models\Sale;
use App\Models\Store;

/**
 * A delivery note made from the till: the receipt is the one in the address, so it is not sent in the body. A cashier
 * may only make notes for their own receipts, which is checked before anything in the body is looked at.
 */
class SaveCashierDeliveryNoteRequest extends SaveDeliveryNoteRequest
{
    public function authorize(): bool
    {
        /** @var Store $store */
        $store = tenant();

        return $this->user()->canHandleReceipt($this->route('sale'), $store);
    }

    protected function prepareForValidation(): void
    {
        /** @var Sale $sale */
        $sale = $this->route('sale');

        $this->merge(['sale_id' => $sale->id]);
    }
}
