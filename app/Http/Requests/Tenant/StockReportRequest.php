<?php

namespace App\Http\Requests\Tenant;

use App\Enums\SalesGrouping;
use App\Enums\StockStatusFilter;
use App\Services\StockReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * The stock report screen: what the table of products is narrowed to, and the page, size and grouping of its tables.
 * Like the other report screens, a value that is not one of the options is left out as if it was not sent: the address
 * is typed by hand now and then, and it must not end in an error page.
 */
class StockReportRequest extends FormRequest
{
    use ReadsTableQuery;

    private const SEARCH_MAX_LENGTH = 100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    public function search(): string
    {
        $term = $this->query('search');

        return is_string($term) ? Str::limit(trim($term), self::SEARCH_MAX_LENGTH, '') : '';
    }

    public function categoryId(): ?int
    {
        return $this->positiveIntegerOrNull('category');
    }

    public function brandId(): ?int
    {
        return $this->positiveIntegerOrNull('brand');
    }

    public function status(): ?StockStatusFilter
    {
        $status = $this->query('status');

        return is_string($status) ? StockStatusFilter::tryFrom($status) : null;
    }

    public function positionsPage(): int
    {
        return $this->positiveInteger('positions_page');
    }

    public function positionsPerPage(): int
    {
        return $this->optionOrFirst('positions_per_page', StockReportService::PER_PAGE_OPTIONS);
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
        return $this->optionOrFirst('groups_per_page', StockReportService::GROUP_PER_PAGE_OPTIONS);
    }

    private function positiveIntegerOrNull(string $key): ?int
    {
        return filter_var($this->query($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    }
}
