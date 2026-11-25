<?php

namespace App\Http\Resources;

use App\Models\Carrier;
use App\Models\CarrierContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the carrier list. Expects `purchases_count` and the loaded `contacts`.
 *
 * @mixin Carrier
 */
class CarrierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $routeParameters = ['tenant' => tenant('code')];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'province' => $this->province,
            'city' => $this->city,
            'transit_days' => $this->transit_days,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'contacts' => $this->contacts->map(fn (CarrierContact $contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
            ])->all(),
            'purchases_count' => $this->purchases_count,
            'purchases_url' => route('tenant.purchases.index', [...$routeParameters, 'carrier' => $this->id]),
            'update_url' => route('tenant.carriers.update', [...$routeParameters, 'carrier' => $this->id]),
        ];
    }
}
