<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\StockReportService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the stock report's table. Expects `category`, `brand` and `productUnits` to be loaded and the stock totals
 * and flags of {@see StockReportService::positionsPage()}.
 *
 * @mixin Product
 */
class StockPositionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category->name,
            'brand' => $this->brand?->name,
            'is_active' => $this->is_active,
            'is_damaged_goods' => $this->is_damaged_goods,
            'min_stock' => $this->min_stock,
            'sellable_stock' => $this->getAttribute('sellable_stock'),
            'dead_stock' => $this->getAttribute('dead_stock'),
            'returns_stock' => $this->getAttribute('returns_stock'),
            'is_below_minimum' => (bool) $this->getAttribute('is_below_minimum'),
            'has_negative_stock' => (bool) $this->getAttribute('has_negative_stock'),
            'cost_price' => $this->cost_price,
            'sellable_value' => $this->sellableValue(),
            'base_unit' => $this->base_unit_code,
            ...ProductListResource::unitDisplay($this->resource),
        ];
    }

    /**
     * What the sellable stock is worth at the purchase price. A negative balance is worth nothing, as in the totals.
     */
    private function sellableValue(): string
    {
        $stock = BigDecimal::of((string) $this->getAttribute('sellable_stock'));

        return (string) BigDecimal::of($this->cost_price)->multipliedBy($stock->isPositive() ? $stock : BigDecimal::zero())->toScale(2, RoundingMode::HalfUp);
    }
}
