<?php

namespace App\Http\Requests\Tenant;

use App\Enums\StockStatusFilter;
use App\Services\StockReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The stock positions screen: the day it shows the stock of, what the table of products is narrowed to, and its page, size and order.
 * Like the other report screens, a value that is not one of the options is left out as if it was not sent: the address
 * is typed by hand now and then, and it must not end in an error page.
 */
class StockReportRequest extends FormRequest
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

    /**
     * The day the stock is wanted as it was on, or `null` for now: a value that is not a real day, and today or a day to
     * come (there is no ledger of those yet), mean now.
     */
    public function asOf(): ?CarbonImmutable
    {
        $value = $this->query('as_of');

        if (! is_string($value) || ! CarbonImmutable::hasFormat($value, 'Y-m-d')) {
            return null;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        // hasFormat lets a day that does not exist roll over (2026-02-30 becomes 2 March), so the day must read back the same.
        return $day->format('Y-m-d') === $value && $day->isBefore(CarbonImmutable::today()) ? $day : null;
    }

    public function positionsPage(): int
    {
        return $this->positiveInteger('positions_page');
    }

    public function positionsPerPage(): int
    {
        return $this->optionOrFirst('positions_per_page', StockReportService::PER_PAGE_OPTIONS);
    }

    public function positionsSort(): string
    {
        return $this->optionOrFirst('positions_sort', StockReportService::positionSorts());
    }

    /**
     * The order the table of products is in: names run A to Z and the figures biggest first, unless the address says so.
     *
     * @return 'asc'|'desc'
     */
    public function positionsDirection(): string
    {
        return $this->directionOf('positions_direction', $this->positionsSort());
    }
}
