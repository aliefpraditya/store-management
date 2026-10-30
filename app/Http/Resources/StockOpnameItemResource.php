<?php

namespace App\Http\Resources;

use App\Models\StockOpnameItem;
use App\Services\StockOpnameService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One counted product. Expects `product.baseUnit`, `product.productUnits` and the `system_quantity`,
 * `variance_quantity` and `product_cost_price` columns from {@see StockOpnameService::paginateItems()}.
 *
 * @mixin StockOpnameItem
 */
class StockOpnameItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variance = $this->isCounted() ? BigDecimal::of($this->getAttribute('variance_quantity')) : null;

        return [
            'id' => $this->id,
            'product' => ['id' => $this->product->id, 'sku' => $this->product->sku, 'name' => $this->product->name],
            'base_unit' => $this->product->baseUnit->code,
            'units' => $this->product->productUnits->map(fn ($productUnit): array => [
                'code' => $productUnit->unit_code,
                'conversion_factor' => $productUnit->conversion_factor,
            ])->values()->all(),
            'system_quantity' => $this->getAttribute('system_quantity'),
            'counted_quantity' => $this->counted_quantity,
            'variance' => $variance === null ? null : (string) $variance,
            'variance_value' => $variance === null
                ? null
                : (string) $variance->multipliedBy($this->getAttribute('product_cost_price'))->toScale(2, RoundingMode::HalfUp),
            'reason' => $this->reason,
            'remove_url' => route('tenant.stock-opnames.items.destroy', [
                'tenant' => tenant('code'),
                'stock_opname' => $this->stock_opname_id,
                'item' => $this->id,
            ]),
        ];
    }
}
