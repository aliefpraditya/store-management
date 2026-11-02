<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use Illuminate\Support\Collection;

/**
 * The kinds of expense an admin sorts them by. Categories are switched off, never deleted, so old expenses keep
 * their category.
 *
 * @phpstan-type CategoryInput array{name: string, is_active?: bool}
 */
class ExpenseCategoryService
{
    /**
     * @return Collection<int, ExpenseCategory>
     */
    public function all(): Collection
    {
        return ExpenseCategory::query()->withCount('expenses')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    public function active(): Collection
    {
        return ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function create(string $name): ExpenseCategory
    {
        return ExpenseCategory::query()->create(['name' => trim($name), 'is_active' => true]);
    }

    /**
     * @param  CategoryInput  $categoryInput
     */
    public function update(ExpenseCategory $category, array $categoryInput): ExpenseCategory
    {
        $category->forceFill([
            'name' => trim($categoryInput['name']),
            'is_active' => $categoryInput['is_active'] ?? $category->is_active,
        ])->save();

        return $category;
    }
}
