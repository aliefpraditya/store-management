<?php

namespace App\Http\Resources;

use App\Models\DailyClosing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One closed day with the figures saved when it was closed. Expects `closedBy` and `reopenedBy` loaded.
 *
 * @mixin DailyClosing
 */
class DailyClosingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $date = $this->business_date->toDateString();

        return [
            'business_date' => $date,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'notes' => $this->notes,
            'closed_by_name' => $this->closedBy?->name,
            'closed_at' => $this->closed_at->toIso8601String(),
            'reopened_by_name' => $this->reopenedBy?->name,
            'reopened_at' => $this->reopened_at?->toIso8601String(),
            'reopen_reason' => $this->reopen_reason,
            'figures' => [
                'shifts_count' => $this->shifts_count,
                'sales_count' => $this->sales_count,
                'voids_count' => $this->voids_count,
                ...collect(DailyClosing::MONEY_FIELDS)->mapWithKeys(fn (string $field): array => [$field => $this->{$field}])->all(),
            ],
            'show_url' => route('tenant.daily-closings.show', ['tenant' => tenant('code'), 'date' => $date]),
            'sheet_url' => route('tenant.daily-closings.sheet', ['tenant' => tenant('code'), 'date' => $date]),
        ];
    }
}
