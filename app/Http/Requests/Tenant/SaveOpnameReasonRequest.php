<?php

namespace App\Http\Requests\Tenant;

use App\Models\OpnameReason;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A reason of an opname variance, new or renamed or switched off. The reasons every store starts with stay on.
 */
class SaveOpnameReasonRequest extends FormRequest
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
        /** @var OpnameReason|null $reason */
        $reason = $this->route('opname_reason');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('opname_reasons', 'name')->ignore($reason?->id)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var OpnameReason|null $reason */
                $reason = $this->route('opname_reason');

                if ($reason?->is_system && $this->has('is_active') && ! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', "Alasan {$reason->name} bawaan toko dan tidak bisa dinonaktifkan.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Isi nama alasan.',
            'name.unique' => 'Alasan dengan nama itu sudah ada.',
            'name.max' => 'Nama alasan maksimal 100 huruf.',
        ];
    }
}
