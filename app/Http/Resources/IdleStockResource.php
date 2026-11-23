<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\IdleStockService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the idle stock table. Expects `category`, `brand` and `productUnits` to be loaded and the stock totals and
 * `last_sold_on` of {@see IdleStockService::page()}.
 *
 * @mixin Product
 */
class IdleStockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lastSoldOn = $this->getAttribute('last_sold_on');

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category->name,
            'brand' => $this->brand?->name,
            'is_damaged_goods' => $this->is_damaged_goods,
            'sellable_stock' => $this->getAttribute('sellable_stock'),
            'cost_price' => $this->cost_price,
            'stock_value' => (string) BigDecimal::of($this->cost_price)->multipliedBy((string) $this->getAttribute('sellable_stock'))->toScale(2, RoundingMode::HalfUp),
            'last_sold_on' => $lastSoldOn,
            'days_since_sold' => $lastSoldOn === null ? null : (int) CarbonImmutable::parse($lastSoldOn)->diffInDays(CarbonImmutable::today()),
            'base_unit' => $this->base_unit_code,
            ...ProductListResource::unitDisplay($this->resource),
        ];
    }
}
