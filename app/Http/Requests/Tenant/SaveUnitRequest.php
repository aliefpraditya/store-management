<?php

namespace App\Http\Requests\Tenant;

use App\Models\Unit;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adds (POST) or edits (PUT) a unit; the unit being edited is the `unit` route parameter.
 */
class SaveUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Unit::normalizeCode($this->input('code'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $editedUnit = $this->editedUnit();

        return [
            'code' => [
                'required', 'string', 'max:'.Unit::CODE_MAX_LENGTH, 'regex:/^[A-Za-z0-9][A-Za-z0-9 ._-]*$/',
                Rule::unique('units', 'code')->ignore($editedUnit?->code, 'code'),
            ],
            'name' => ['nullable', 'string', 'max:255', Rule::unique('units', 'name')->ignore($editedUnit?->code, 'code')],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || filled($this->input('name'))) {
                    return;
                }

                $isNameTakenByAnotherUnit = Unit::query()
                    ->where('name', $this->input('code'))
                    ->when($this->editedUnit(), fn ($query, Unit $unit) => $query->where('code', '!=', $unit->code))
                    ->exists();

                if ($isNameTakenByAnotherUnit) {
                    $validator->errors()->add('name', 'Tanpa nama, nama mengikuti kode, tetapi nama itu sudah dipakai satuan lain.');
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
            'code.required' => 'Kode satuan wajib diisi.',
            'code.max' => 'Kode satuan maksimal :max karakter.',
            'code.regex' => 'Kode satuan hanya huruf, angka, spasi, titik, garis bawah, atau strip.',
            'code.unique' => 'Kode satuan sudah dipakai.',
            'name.unique' => 'Nama satuan sudah dipakai.',
        ];
    }

    private function editedUnit(): ?Unit
    {
        $unit = $this->route('unit');

        return $unit instanceof Unit ? $unit : null;
    }
}
