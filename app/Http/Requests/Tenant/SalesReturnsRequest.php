<?php

namespace App\Http\Requests\Tenant;

use App\Services\SalesDetailReportService;

/**
 * The returns and voids tab of the sales report: a period, and the page, size and order of its two lists. Anything that is
 * not a positive whole number is the first page, a size or an order that is not one of the options is the first option,
 * like a date that is not a date, so a hand-typed address never ends in an error page.
 */
class SalesReturnsRequest extends DateRangeRequest
{
    use ReadsTableQuery;

    public function returnsPage(): int
    {
        return $this->positiveInteger('returns_page');
    }

    public function returnsPerPage(): int
    {
        return $this->optionOrFirst('returns_per_page', SalesDetailReportService::LIST_PER_PAGE_OPTIONS);
    }

    public function returnsSort(): string
    {
        return $this->optionOrFirst('returns_sort', SalesDetailReportService::RETURN_SORTS);
    }

    /**
     * @return 'asc'|'desc'
     */
    public function returnsDirection(): string
    {
        return $this->directionOf('returns_direction', $this->returnsSort(), SalesDetailReportService::TEXT_SORTS);
    }

    public function voidsPage(): int
    {
        return $this->positiveInteger('voids_page');
    }

    public function voidsPerPage(): int
    {
        return $this->optionOrFirst('voids_per_page', SalesDetailReportService::LIST_PER_PAGE_OPTIONS);
    }

    public function voidsSort(): string
    {
        return $this->optionOrFirst('voids_sort', SalesDetailReportService::VOID_SORTS);
    }

    /**
     * @return 'asc'|'desc'
     */
    public function voidsDirection(): string
    {
        return $this->directionOf('voids_direction', $this->voidsSort(), SalesDetailReportService::TEXT_SORTS);
    }
}
