<?php

namespace App\Http\Resources;

use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Models\StockOpname;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The header of one opname with what the admin may do next. Expects `warehouse`, `category`, `brand` and `creator`.
 *
 * @mixin StockOpname
 */
class StockOpnameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isIn = fn (StockOpnameStatus ...$statuses): bool => in_array($this->status, $statuses, true);
        $isSelectedScope = $this->scope === StockOpnameScope::Selected;
        $actionUrl = fn (string $action, array $parameters = []): string => route("tenant.stock-opnames.{$action}", ['tenant' => tenant('code'), 'stock_opname' => $this->id, ...$parameters]);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'opname_date' => $this->opname_date->toDateString(),
            'warehouse' => $this->warehouse->name,
            'scope' => $this->scope->value,
            'scope_label' => $this->scope->label(),
            'category' => $this->category?->name,
            'brand' => $this->brand?->name,
            'reason_label' => $this->reason_code?->label(),
            'is_counting_arrivals' => (bool) $this->reason_code?->isArrivalCount(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'snapshot_at' => $this->snapshot_at?->toIso8601String(),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'created_by' => $this->creator?->name,
            'can_start' => $isIn(StockOpnameStatus::Draft),
            'can_count' => $isIn(StockOpnameStatus::Counting),
            'can_manage_products' => $isSelectedScope && $isIn(StockOpnameStatus::Draft, StockOpnameStatus::Counting),
            'can_finish' => $isIn(StockOpnameStatus::Counting),
            'requires_review' => (bool) $this->reason_code?->requiresVarianceReview(),
            'can_review' => $isIn(StockOpnameStatus::Review) && $this->reason_code?->requiresVarianceReview() === true,
            'can_reopen' => $isIn(StockOpnameStatus::Review),
            'can_post' => $isIn(StockOpnameStatus::Review),
            'is_erased_on_cancel' => $this->status->isErasedWhenCancelled(),
            'can_cancel' => $isIn(StockOpnameStatus::Draft, StockOpnameStatus::Counting, StockOpnameStatus::Review),
            'urls' => [
                'index' => route('tenant.stock-opnames.index', ['tenant' => tenant('code')]),
                'show' => $actionUrl('show'),
                'start' => $actionUrl('start'),
                'counts' => $actionUrl('counts'),
                'review' => $actionUrl('review'),
                'add_item' => $actionUrl('items.store'),
                'zero_uncounted' => $actionUrl('zero-uncounted'),
                'finish' => $actionUrl('finish'),
                'reopen' => $actionUrl('reopen'),
                'post' => $actionUrl('post'),
                'cancel' => $actionUrl('cancel'),
                'product_search' => route('tenant.products.search', ['tenant' => tenant('code')]),
            ],
        ];
    }
}
