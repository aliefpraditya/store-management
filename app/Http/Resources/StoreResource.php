<?php

namespace App\Http\Resources;

use App\Enums\StoreStatus;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the Master Toko list. No database credentials, only the database name.
 * The optional `failure_message` attribute is the latest failed setup step, selected by the list query.
 *
 * @mixin Store
 */
class StoreResource extends JsonResource
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
            'db_name' => $this->db_name,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'is_active' => $this->status === StoreStatus::Active,
            'can_toggle_active' => $this->status->canBeToggled(),
            'can_retry' => $this->status->needsProvisioning(),
            'failure_message' => $this->status === StoreStatus::Failed ? $this->getAttribute('failure_message') : null,
            'edit_url' => route('central.stores.edit', $this->resource),
            'status_url' => route('central.stores.status', $this->resource),
            'provision_url' => route('central.stores.provision', $this->resource),
        ];
    }
}
