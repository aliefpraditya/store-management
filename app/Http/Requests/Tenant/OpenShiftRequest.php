<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenShiftRequest extends FormRequest
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
            'register_id' => ['required', 'integer', Rule::exists('registers', 'id')->where('is_active', true)],
            'opening_cash' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
            'opening_cash_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'register_id.required' => 'Pilih register.',
            'register_id.exists' => 'Register tidak valid.',
            'opening_cash.required' => 'Isi modal kas awal.',
            'opening_cash.numeric' => 'Modal kas awal harus berupa angka.',
            'opening_cash.min' => 'Modal kas awal tidak boleh negatif.',
            'opening_cash.decimal' => 'Modal kas awal maksimal 2 angka desimal.',
        ];
    }
}
