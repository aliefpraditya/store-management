<?php

namespace App\Http\Resources;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the edit form needs. The database password is never sent back, only whether one is stored
 * (read raw, so a changed APP_KEY cannot stop the form from opening).
 *
 * @mixin Store
 */
class StoreFormResource extends JsonResource
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
            'address' => $this->address,
            'phone' => $this->phone,
            'receipt_prefix' => $this->receipt_prefix,
            'db_name' => $this->db_name,
            'db_username' => $this->db_username,
            'db_host' => $this->db_host,
            'has_db_password' => $this->getRawOriginal('db_password') !== null,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'is_database_name_editable' => $this->status->isAwaitingProvisioning(),
        ];
    }
}
