<?php

namespace App\Http\Requests\Tenant;

use App\Models\Driver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adds (POST) or edits (PUT) a driver; the driver being edited is the `driver` route parameter.
 */
class SaveDriverRequest extends FormRequest
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
        $editedDriver = $this->route('driver');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('drivers', 'name')->ignore($editedDriver instanceof Driver ? $editedDriver->id : null)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama sopir wajib diisi.',
            'name.max' => 'Nama sopir maksimal :max karakter.',
            'name.unique' => 'Nama sopir sudah ada.',
        ];
    }
}
