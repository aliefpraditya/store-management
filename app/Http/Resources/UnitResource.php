<?php

namespace App\Http\Resources;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the unit list. Expects the `product_units_count` count to be loaded.
 *
 * @mixin Unit
 */
class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $productsCount = (int) $this->getAttribute('product_units_count');

        return [
            'code' => $this->code,
            'name' => $this->name,
            'products_count' => $productsCount,
            'can_delete' => $productsCount === 0,
            'update_url' => route('tenant.units.update', ['tenant' => tenant('code'), 'unit' => $this->code]),
            'delete_url' => route('tenant.units.destroy', ['tenant' => tenant('code'), 'unit' => $this->code]),
        ];
    }
}
