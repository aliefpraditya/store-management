<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reopening a closed day is for the Super Admin only, and always with a reason.
 */
class ReopenDailyClosingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()->is_super_admin;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Isi alasan membuka kembali hari ini.',
        ];
    }
}
