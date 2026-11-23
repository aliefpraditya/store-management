<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PaymentMethod;

/**
 * The detailed sales sheet or spreadsheet: a period, as for the sales report, and optionally one payment method to narrow
 * the receipts to. A method that is not one a sale can be paid by is left out as if it was not sent, like a bad date.
 */
class SalesDetailReportRequest extends DateRangeRequest
{
    public function paymentMethod(): ?PaymentMethod
    {
        $value = $this->query('method');
        $method = is_string($value) ? PaymentMethod::tryFrom($value) : null;

        return in_array($method, PaymentMethod::forSale(), true) ? $method : null;
    }
}
