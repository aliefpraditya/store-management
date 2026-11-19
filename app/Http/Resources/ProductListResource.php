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
            'is_damaged_goods' => $this->is_damaged_goods,
            'min_stock' => $this->min_stock,
            'sellable_stock' => $this->getAttribute('sellable_stock'),
            'dead_stock' => $this->getAttribute('dead_stock'),
            'returns_stock' => $this->getAttribute('returns_stock'),
            'is_below_minimum' => (bool) $this->getAttribute('is_below_minimum'),
            'cost_price' => $this->cost_price,
            'base_unit' => $this->base_unit_code,
            ...self::unitDisplay($this->resource),
            'default_sale' => $defaultSaleUnit === null ? null : [
                'unit' => $defaultSaleUnit->unit_code,
                'price' => $defaultSaleUnit->selling_price,
            ],
            'can_delete' => ! (bool) $this->getAttribute('has_transactions'),
            'edit_url' => route('tenant.products.edit', ['tenant' => tenant('code'), 'product' => $this->id]),
            'delete_url' => route('tenant.products.destroy', ['tenant' => tenant('code'), 'product' => $this->id]),
        ];
    }

    /**
     * What a screen needs to write a stock quantity in the larger units (see `formatStock` in the frontend): the units
     * and, for a product with a sealed pack unit, how many whole packs are in sellable and dead stock. Expects
     * `productUnits` loaded and, for the packs, the `*_intact_packs` totals of {@see Product::scopeWithStockTotals()}.
     *
     * @return array{units: list<array{code: string, conversion_factor: string, is_sealed_pack: bool}>, sealed_pack: array{unit: string, intact: int, dead_intact: int}|null}
     */
    public static function unitDisplay(Product $product): array
    {
        $sealedPackUnit = $product->sealedPackUnit();

        return [
            'units' => $product->productUnits->map(fn ($productUnit): array => [
                'code' => $productUnit->unit_code,
                'conversion_factor' => $productUnit->conversion_factor,
                'is_sealed_pack' => $productUnit->is_sealed_pack,
            ])->values()->all(),
            'sealed_pack' => $sealedPackUnit === null ? null : [
                'unit' => $sealedPackUnit->unit_code,
                'intact' => (int) $product->getAttribute('sellable_intact_packs'),
                'dead_intact' => (int) $product->getAttribute('dead_intact_packs'),
            ],
        ];
    }
}
