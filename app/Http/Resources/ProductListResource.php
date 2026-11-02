<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the item list. Expects `category`, `brand` and `productUnits` to be loaded, the `sellable_stock` /
 * `dead_stock` totals and the `is_below_minimum` flag from {@see Product::scopeWithStockTotals()} and the
 * `has_transactions` flag from {@see Product::scopeWithTransactionFlag()}.
 *
 * @mixin Product
 */
class ProductListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $defaultSaleUnit = $this->productUnits->firstWhere('is_default_sale', true);

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category->name,
            'brand' => $this->brand?->name,
            'is_active' => $this->is_active,
            'min_stock' => $this->min_stock,
            'sellable_stock' => $this->getAttribute('sellable_stock'),
            'dead_stock' => $this->getAttribute('dead_stock'),
            'is_below_minimum' => (bool) $this->getAttribute('is_below_minimum'),
            'cost_price' => $this->cost_price,
            'base_unit' => $this->base_unit_code,
            'units' => $this->productUnits->map(fn ($productUnit): array => [
                'code' => $productUnit->unit_code,
                'conversion_factor' => $productUnit->conversion_factor,
            ])->values()->all(),
            'default_sale' => $defaultSaleUnit === null ? null : [
                'unit' => $defaultSaleUnit->unit_code,
                'price' => $defaultSaleUnit->selling_price,
            ],
            'can_delete' => ! (bool) $this->getAttribute('has_transactions'),
            'edit_url' => route('tenant.products.edit', ['tenant' => tenant('code'), 'product' => $this->id]),
            'delete_url' => route('tenant.products.destroy', ['tenant' => tenant('code'), 'product' => $this->id]),
        ];
    }
}
