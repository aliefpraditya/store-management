<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the item form needs to edit a product. Expects `category`, `brand` and `productUnits.unit` to be loaded.
 *
 * @mixin Product
 */
class ProductFormResource extends JsonResource
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
            'category' => ['id' => $this->category->id, 'name' => $this->category->name],
            'brand' => $this->brand === null ? null : ['id' => $this->brand->id, 'name' => $this->brand->name],
            'min_stock' => $this->min_stock,
            'is_active' => $this->is_active,
            'version' => $this->version,
            'is_locked' => $this->hasTransactions(),
            'delete_url' => route('tenant.products.destroy', ['tenant' => tenant('code'), 'product' => $this->id]),
            'units' => $this->productUnits->map(fn ($productUnit): array => [
                'id' => $productUnit->id,
                'unit' => [
                    'code' => $productUnit->unit->code,
                    'name' => $productUnit->unit->name,
                ],
                'conversion_factor' => $productUnit->conversion_factor,
                'is_sellable' => $productUnit->is_sellable,
                'cost_price' => $productUnit->cost_price,
                'selling_price' => $productUnit->selling_price,
                'is_default_sale' => $productUnit->is_default_sale,
                'is_sealed_pack' => $productUnit->is_sealed_pack,
            ])->values()->all(),
        ];
    }
}
