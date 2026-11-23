<?php

namespace App\Http\Requests\Tenant;

use App\Services\RestockReportService;
use App\Services\StockReportService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The restock screen: how many days of sales set the pace, how many days the stock should last, the category the table is
 * narrowed to, and the order, page and size of the table. Like the other report screens, a value that is not one of the
 * options is left out as if it was not sent: the address is typed by hand now and then, and it must not end in an error
 * page.
 */
class RestockRequest extends FormRequest
{
    use ReadsTableQuery;

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

    public function window(): int
    {
        return $this->optionOrFirst('window', RestockReportService::WINDOW_OPTIONS);
    }

    public function cover(): int
    {
        return $this->optionOrFirst('cover', RestockReportService::COVER_OPTIONS);
    }

    public function categoryId(): ?int
    {
        return $this->positiveIntegerOrNull('category');
    }

    public function sort(): string
    {
        return $this->optionOrFirst('sort', RestockReportService::sorts());
    }

    /**
     * The order the table is in: the most urgent first and names A to Z, the figures biggest first, unless the address
     * says so.
     *
     * @return 'asc'|'desc'
     */
    public function direction(): string
    {
        $direction = $this->query('direction');

        if (in_array($direction, ['asc', 'desc'], true)) {
            return $direction;
        }

        return RestockReportService::startsAscending($this->sort()) ? 'asc' : 'desc';
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
