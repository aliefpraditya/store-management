<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegisterRequest extends FormRequest
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
        return [
            'fixed_opening_cash' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fixed_opening_cash.required' => 'Isi modal kas tetap.',
            'fixed_opening_cash.numeric' => 'Modal kas tetap harus berupa angka.',
            'fixed_opening_cash.min' => 'Modal kas tetap tidak boleh negatif.',
            'fixed_opening_cash.decimal' => 'Modal kas tetap maksimal 2 angka desimal.',
        ];
    }
}
