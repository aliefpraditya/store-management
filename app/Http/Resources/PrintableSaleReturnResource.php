<?php

namespace App\Http\Resources;

use App\Models\SaleReturn;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the browser prints for a return: the return itself plus the store's letterhead. Expects `sale`, `cashShift`
 * and `items.saleItem` to be loaded.
 *
 * @mixin SaleReturn
 */
class PrintableSaleReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Store $store */
        $store = tenant();

        return [
            ...SaleReturnResource::make($this->resource)->resolve($request),
            'store' => ['name' => $store->name, 'address' => $store->address, 'phone' => $store->phone],
        ];
    }
}
