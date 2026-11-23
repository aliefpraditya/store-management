<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\RestockReportService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the restock table. Expects `category`, `brand` and `productUnits` to be loaded and the stock totals,
 * `sold_quantity`, `days_left` and `suggested_quantity` of {@see RestockReportService::page()}.
 *
 * @mixin Product
 */
class RestockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $suggestedQuantity = (string) BigDecimal::of((string) $this->getAttribute('suggested_quantity'))->toScale(0);

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category->name,
            'brand' => $this->brand?->name,
            'sellable_stock' => $this->getAttribute('sellable_stock'),
            'min_stock' => $this->min_stock,
            'is_below_minimum' => (bool) $this->getAttribute('is_below_minimum'),
            'sold_quantity' => $this->getAttribute('sold_quantity'),
            'days_left' => $this->getAttribute('days_left'),
            'suggested_quantity' => $suggestedQuantity,
            'cost_price' => $this->cost_price,
            'suggested_cost' => (string) BigDecimal::of($this->cost_price)->multipliedBy($suggestedQuantity)->toScale(2, RoundingMode::HalfUp),
            'base_unit' => $this->base_unit_code,
            ...ProductListResource::unitDisplay($this->resource),
        ];
    }
}
