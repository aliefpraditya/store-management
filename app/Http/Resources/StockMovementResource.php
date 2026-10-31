<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use App\Services\StockHistoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the stock history. Expects `product.baseUnit` and `warehouse` to be loaded and the
 * `document_number` attribute from {@see StockHistoryService}.
 *
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'moved_at' => $this->moved_at->toIso8601String(),
            'document_number' => $this->getAttribute('document_number'),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'quantity' => $this->quantity,
            'balance_after' => $this->balance_after,
            'note' => $this->note,
            'product' => ['id' => $this->product->id, 'sku' => $this->product->sku, 'name' => $this->product->name],
            'base_unit' => $this->product->baseUnit->code,
            'warehouse' => $this->warehouse->name,
        ];
    }
}
