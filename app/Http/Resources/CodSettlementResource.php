<?php

namespace App\Http\Resources;

use App\Models\CodSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The money received for a COD sale, as every screen that shows it reads it.
 *
 * @mixin CodSettlement
 */
class CodSettlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'amount' => $this->amount,
            'expected_amount' => $this->expected_amount,
            'difference_reason' => $this->difference_reason,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'received_by_name' => $this->received_by_name,
            'received_at' => $this->received_at->toIso8601String(),
            'approved_by_name' => $this->approved_by_name,
            'approved_at' => $this->approved_at?->toIso8601String(),
        ];
    }
}
