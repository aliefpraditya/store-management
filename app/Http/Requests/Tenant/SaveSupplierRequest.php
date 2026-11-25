<?php

namespace App\Http\Requests\Tenant;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adds (POST) or edits (PUT) a supplier; the one being edited is the `supplier` route parameter.
 */
class SaveSupplierRequest extends FormRequest
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
        $editedSupplier = $this->route('supplier');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('suppliers', 'name')->ignore($editedSupplier instanceof Supplier ? $editedSupplier->id : null),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama supplier wajib diisi.',
            'name.unique' => 'Nama supplier sudah dipakai.',
            'phone.max' => 'Nomor telepon terlalu panjang.',
            'notes.max' => 'Catatan terlalu panjang.',
        ];
    }
}
