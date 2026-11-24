<?php

namespace App\Http\Requests\Tenant;

use App\Services\StockVarianceReportService;

/**
 * The opname variance tab of the stock report: a period, and the page and size of its list of counts. Anything that is not a
 * positive whole number is the first page and a size that is not one of the options is the first option, like a date that is
 * not a date, so a hand-typed address never ends in an error page.
 */
class StockVarianceRequest extends DateRangeRequest
{
    use ReadsTableQuery;

    public function opnamesPage(): int
    {
        return $this->positiveInteger('opnames_page');
    }

    public function opnamesPerPage(): int
    {
        return $this->optionOrFirst('opnames_per_page', StockVarianceReportService::OPNAME_PER_PAGE_OPTIONS);
    }
}
