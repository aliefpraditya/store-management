<?php

namespace App\Http\Resources;

use App\Enums\StoreStatus;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the central dashboard may know about a store. Database credentials are never exposed.
 *
 * @mixin Store
 */
class StoreCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'receipt_prefix' => $this->receipt_prefix,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'is_enterable' => $this->status === StoreStatus::Active,
            'url' => route('tenant.dashboard', ['tenant' => $this->code]),
        ];
    }
}
