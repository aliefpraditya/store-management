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
    public function productsPage(): int
    {
        return $this->positiveInteger('products_page');
    }

    public function productsPerPage(): int
    {
        return $this->optionOrFirst('products_per_page', SalesReportService::PER_PAGE_OPTIONS);
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
        $direction = $this->query('groups_direction');

        if (in_array($direction, ['asc', 'desc'], true)) {
            return $direction;
        }

        return $this->groupsSort() === 'name' ? 'asc' : 'desc';
    }

    private function positiveInteger(string $key): int
    {
        return max(filter_var($this->query($key), FILTER_VALIDATE_INT) ?: 1, 1);
    }

    /**
     * @param  list<int|string>  $options
     */
    private function optionOrFirst(string $key, array $options): int|string
    {
        $value = $this->query($key);

        foreach ($options as $option) {
            if ((string) $option === $value) {
                return $option;
            }
        }

        return $options[0];
    }
}
