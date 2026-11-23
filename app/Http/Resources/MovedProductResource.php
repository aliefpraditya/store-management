<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\StockMovementReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the stock movement table. Expects `category` and `brand` to be loaded and the ledger figures of
 * {@see StockMovementReportService::page()}.
 *
 * @mixin Product
 */
class MovedProductResource extends JsonResource
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
            'base_unit' => $this->base_unit_code,
            'opening_quantity' => $this->getAttribute('opening_quantity'),
            'sold_quantity' => $this->getAttribute('sold_quantity'),
            'voided_quantity' => $this->getAttribute('voided_quantity'),
            'returned_quantity' => $this->getAttribute('returned_quantity'),
            'counted_quantity' => $this->getAttribute('counted_quantity'),
            'closing_quantity' => $this->getAttribute('closing_quantity'),
        ];
    }
}
