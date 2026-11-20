<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The one reason to give to every variance of an opname that has none yet. Only a reason that is still switched on can be
 * given to many items at once.
 */
class ApplyStockOpnameReasonRequest extends FormRequest
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
            'reason_id' => ['required', 'integer', Rule::exists('opname_reasons', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason_id.required' => 'Pilih alasannya.',
            'reason_id.exists' => 'Alasan tidak dikenal atau sudah dinonaktifkan.',
        ];
    }
}
