<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Concerns\ValidatesContacts;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adds (POST) or edits (PUT) a supplier; the one being edited is the `supplier` route parameter.
 */
class SaveSupplierRequest extends FormRequest
{
    use ValidatesContacts;

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
            'address' => ['nullable', 'string', 'max:500'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->contactRules(),
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
            'address.max' => 'Alamat terlalu panjang.',
            'province.max' => 'Nama provinsi terlalu panjang.',
            'city.max' => 'Nama kota terlalu panjang.',
            'email.email' => 'Format email tidak valid.',
            'tax_number.max' => 'NPWP terlalu panjang.',
            'notes.max' => 'Catatan terlalu panjang.',
            ...$this->contactMessages(),
        ];
    }
}
