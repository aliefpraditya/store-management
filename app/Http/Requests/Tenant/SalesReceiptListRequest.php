<?php

namespace App\Http\Requests\Tenant;

use App\Services\SaleHistoryService;

/**
 * The receipt list of the sales report: a period and a payment method as for the detailed sheet, the words typed in the
 * search box, and the page and size of the table. A value that is not usable is left out as if it was not sent, since the
 * address is typed by hand now and then and must not end in an error page.
 */
class SalesReceiptListRequest extends SalesDetailReportRequest
{
    use ReadsTableQuery;

    public function page(): int
    {
        return $this->positiveInteger('page');
    }

    public function perPage(): int
    {
        return $this->optionOrFirst('per_page', SaleHistoryService::RECEIPT_LIST_PER_PAGE_OPTIONS);
    }
}
