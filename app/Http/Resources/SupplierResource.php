<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use App\Models\SupplierContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the supplier list. Expects `purchases_count` and the loaded `contacts`.
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
            'address' => $this->address,
            'province' => $this->province,
            'city' => $this->city,
            'email' => $this->email,
            'tax_number' => $this->tax_number,
            'contacts' => $this->contacts->map(fn (SupplierContact $contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
            ])->all(),
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'purchases_count' => $this->purchases_count,
            'purchases_url' => route('tenant.purchases.index', ['tenant' => tenant('code'), 'supplier' => $this->id]),
            'update_url' => route('tenant.suppliers.update', ['tenant' => tenant('code'), 'supplier' => $this->id]),
        ];
    }
}
