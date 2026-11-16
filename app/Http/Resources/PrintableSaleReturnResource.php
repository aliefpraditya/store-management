<?php

namespace App\Http\Resources;

use App\Models\SaleReturn;
use App\Services\StoreSettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the browser prints for a return: the return itself plus the store's letterhead and paper width. Expects `sale`,
 * `cashShift` and `items.saleItem` to be loaded.
 *
 * @phpstan-import-type Letterhead from StoreSettingService
 *
 * @mixin SaleReturn
 */
class PrintableSaleReturnResource extends JsonResource
{
    /**
     * @param  Letterhead  $letterhead
     */
    public function __construct(SaleReturn $resource, private readonly array $letterhead, private readonly int $paperWidth)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...SaleReturnResource::make($this->resource)->resolve($request),
            'store' => $this->letterhead,
            'paper_width' => $this->paperWidth,
        ];
    }
}
