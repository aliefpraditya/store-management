<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the supplier list. Expects `purchases_count`.
 *
 * @mixin Supplier
 */
class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'purchases_count' => $this->purchases_count,
            'update_url' => route('tenant.suppliers.update', ['tenant' => tenant('code'), 'supplier' => $this->id]),
        ];
    }
}
