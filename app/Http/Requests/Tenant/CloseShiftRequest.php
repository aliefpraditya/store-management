<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
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
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
            'drawer_left_cash' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
            'closing_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'counted_cash.required' => 'Isi jumlah tunai yang dihitung.',
            'counted_cash.numeric' => 'Jumlah tunai harus berupa angka.',
            'counted_cash.min' => 'Jumlah tunai tidak boleh negatif.',
            'counted_cash.decimal' => 'Jumlah tunai maksimal 2 angka desimal.',
            'drawer_left_cash.required' => 'Isi uang yang ditinggal di laci.',
            'drawer_left_cash.numeric' => 'Uang yang ditinggal di laci harus berupa angka.',
            'drawer_left_cash.min' => 'Uang yang ditinggal di laci tidak boleh negatif.',
            'drawer_left_cash.decimal' => 'Uang yang ditinggal di laci maksimal 2 angka desimal.',
        ];
    }
}
