<?php

namespace App\Http\Requests\Tenant;

use App\Services\StockMovementReportService;
use App\Services\StockReportService;

/**
 * The stock movement screen: a period, what its table is narrowed to, and the page and size of the table. Like the other
 * report screens, a value that is not one of the options is left out as if it was not sent: the address is typed by
 * hand now and then, and it must not end in an error page.
 */
class StockMovementRequest extends DateRangeRequest
{
    use ReadsTableQuery;

    public function categoryId(): ?int
    {
        return $this->positiveIntegerOrNull('category');
    }

    public function sort(): string
    {
        return $this->optionOrFirst('sort', StockMovementReportService::sorts());
    }

    /**
     * The order the table is in: names run A to Z and the figures biggest first, unless the address says so.
     *
     * @return 'asc'|'desc'
     */
    public function direction(): string
    {
        $direction = $this->query('direction');

        if (in_array($direction, ['asc', 'desc'], true)) {
            return $direction;
        }

        return $this->sort() === 'name' ? 'asc' : 'desc';
    }

    public function page(): int
    {
        return $this->positiveInteger('page');
    }

    public function perPage(): int
    {
        return $this->optionOrFirst('per_page', StockReportService::PER_PAGE_OPTIONS);
    }
}
