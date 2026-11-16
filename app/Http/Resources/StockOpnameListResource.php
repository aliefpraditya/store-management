<?php

namespace App\Http\Resources;

use App\Models\StockOpname;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the opname list. Expects `warehouse`, and the `items_count` and `counted_items_count` counts.
 *
 * @mixin StockOpname
 */
class StockOpnameListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'opname_date' => $this->opname_date->toDateString(),
            'warehouse' => $this->warehouse->name,
            'scope_label' => $this->scope->label(),
            'reason_label' => $this->reason_code?->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'items_count' => $this->getAttribute('items_count'),
            'counted_items_count' => $this->getAttribute('counted_items_count'),
            'show_url' => route('tenant.stock-opnames.show', ['tenant' => tenant('code'), 'stock_opname' => $this->id]),
        ];
    }
}
