<?php

namespace App\Http\Requests\Tenant;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adds (POST) or edits (PUT) an expense category; the one being edited is the `category` route parameter.
 */
class SaveExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $editedCategory = $this->route('category');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('expense_categories', 'name')->ignore($editedCategory instanceof ExpenseCategory ? $editedCategory->id : null),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.unique' => 'Nama kategori sudah dipakai.',
        ];
    }
}
