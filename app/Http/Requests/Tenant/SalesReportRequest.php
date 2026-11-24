<?php

namespace App\Http\Requests\Tenant;

use App\Enums\SalesGrouping;
use App\Services\SalesReportService;

/**
 * The sales report screen: a period, and the page, size, grouping and order of its tables. Anything that is not a
 * positive whole number is the first page, a size or an order that is not one of the options is the first option, like
 * a date that is not a date.
 */
class SalesReportRequest extends DateRangeRequest
{
    use ReadsTableQuery;

    public function productsPage(): int
    {
        return $this->positiveInteger('products_page');
    }

    public function productsPerPage(): int
    {
        return $this->optionOrFirst('products_per_page', SalesReportService::PER_PAGE_OPTIONS);
    }

    public function productsSort(): string
    {
        return $this->optionOrFirst('products_sort', SalesReportService::PRODUCT_SORTS);
    }

    /**
     * The order the products are listed in: names run A to Z and the figures biggest first, unless the address says so.
     *
     * @return 'asc'|'desc'
     */
    public function productsDirection(): string
    {
        return $this->directionOf('products_direction', $this->productsSort());
    }

    public function groupsGrouping(): SalesGrouping
    {
        $grouping = $this->query('groups_by');

        return (is_string($grouping) ? SalesGrouping::tryFrom($grouping) : null) ?? SalesGrouping::Category;
    }

    public function groupsPage(): int
    {
        return $this->positiveInteger('groups_page');
    }

    public function groupsPerPage(): int
    {
        return $this->optionOrFirst('groups_per_page', SalesReportService::GROUP_PER_PAGE_OPTIONS);
    }

    public function groupsSort(): string
    {
        return $this->optionOrFirst('groups_sort', SalesReportService::GROUP_SORTS);
    }

    /**
     * The order the groups are listed in: names run A to Z and the figures biggest first, unless the address says so.
     *
     * @return 'asc'|'desc'
     */
    public function groupsDirection(): string
    {
        return $this->directionOf('groups_direction', $this->groupsSort());
    }

    /**
     * @return 'asc'|'desc'
     */
    private function directionOf(string $key, string $sort): string
    {
        $direction = $this->query($key);

        if (in_array($direction, ['asc', 'desc'], true)) {
            return $direction;
        }

        return $sort === 'name' ? 'asc' : 'desc';
    }
}
