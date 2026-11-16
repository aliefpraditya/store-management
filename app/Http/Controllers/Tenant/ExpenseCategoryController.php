<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Services\ExpenseCategoryService;
use Illuminate\Http\RedirectResponse;

/**
 * The expense categories are managed from a window on the expense list, so saving goes back to where it came from.
 */
class ExpenseCategoryController extends Controller
{
    public function __construct(private readonly ExpenseCategoryService $categoryService) {}

    public function store(SaveExpenseCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create($request->validated('name'));

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function update(SaveExpenseCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $this->categoryService->update($category, $request->validated());

        return back()->with('success', 'Kategori disimpan.');
    }
}
