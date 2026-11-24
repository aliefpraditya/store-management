<?php

namespace App\Http\Requests\Tenant;

use App\Enums\SalesGrouping;
use App\Enums\StockGroupMetric;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The summary tab of the stock report: whether the stock is shared out per category or per brand, by what it is worth or by
 * how much there is of it, and the base unit a quantity is counted in (here and for the best sellers). Like the other
 * report screens, a value that is not one of the options is left out as if it was not sent, so a hand-typed address never
 * ends in an error page.
 */
class StockSummaryRequest extends FormRequest
{
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

    public function groupsGrouping(): SalesGrouping
    {
        $grouping = $this->query('groups_by');

        return (is_string($grouping) ? SalesGrouping::tryFrom($grouping) : null) ?? SalesGrouping::Category;
    }

    public function groupsMetric(): StockGroupMetric
    {
        $metric = $this->query('groups_metric');

        return (is_string($metric) ? StockGroupMetric::tryFrom($metric) : null) ?? StockGroupMetric::Value;
    }

    /**
     * The base unit the stock quantities are asked for; the report falls back to the unit most products are held in when this is
     * not one of the units that hold stock.
     */
    public function groupsUnit(): ?string
    {
        return $this->unitOf('groups_unit');
    }

    /** The base unit the best sellers are asked for, with the same fallback. */
    public function bestSellersUnit(): ?string
    {
        return $this->unitOf('sellers_unit');
    }

    private function unitOf(string $key): ?string
    {
        $unit = $this->query($key);

        return is_string($unit) ? $unit : null;
    }
}
