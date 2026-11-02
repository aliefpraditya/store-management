<?php

namespace App\Http\Resources;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An expense category. The admin's list also loads `expenses_count`, and then carries the address to change it; the
 * till only needs the name.
 *
 * @mixin ExpenseCategory
 */
class ExpenseCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'expenses_count' => $this->whenCounted('expenses'),
            'update_url' => $this->when(
                array_key_exists('expenses_count', $this->resource->getAttributes()),
                fn (): string => route('tenant.expenses.categories.update', ['tenant' => tenant('code'), 'category' => $this->id]),
            ),
        ];
    }
}
