<?php

namespace App\Http\Resources;

use App\Models\CodAdvancePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Money paid on a COD receipt before its goods were delivered, as the screens that list it read it.
 *
 * @mixin CodAdvancePayment
 */
class CodAdvancePaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'method_label' => $this->method->label(),
            'amount' => $this->amount,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'received_by_name' => $this->received_by_name,
            'received_at' => $this->received_at->toIso8601String(),
        ];
    }
}
