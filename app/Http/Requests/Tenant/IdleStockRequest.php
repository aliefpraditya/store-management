<?php

namespace App\Http\Requests\Tenant;

use App\Services\IdleStockService;
use App\Services\StockReportService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The idle stock screen: how many days without a sale make stock idle, the category the table is narrowed to, and its
 * page and size. Like the other report screens, a value that is not one of the options is left out as if it was not
 * sent: the address is typed by hand now and then, and it must not end in an error page.
 */
class IdleStockRequest extends FormRequest
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

    public function days(): int
    {
        $days = filter_var($this->query('days'), FILTER_VALIDATE_INT);

        return in_array($days, IdleStockService::DAYS_OPTIONS, true) ? $days : IdleStockService::DEFAULT_DAYS;
    }

    public function categoryId(): ?int
    {
        return $this->positiveIntegerOrNull('category');
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
