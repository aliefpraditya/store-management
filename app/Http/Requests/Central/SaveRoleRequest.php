<?php

namespace App\Http\Requests\Central;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for both creating (POST) and editing (PUT) a role; the role being edited is the `role` route parameter. The label
 * of a system role is not editable, so it is not validated for one.
 */
class SaveRoleRequest extends FormRequest
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
        $editedRole = $this->editedRole();

        return [
            'label' => $editedRole?->is_system
                ? ['nullable']
                : ['required', 'string', 'max:50', Rule::unique('roles', 'label')->ignore($editedRole), 'not_in:Super Admin'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Nama peran wajib diisi.',
            'label.max' => 'Nama peran maksimal :max karakter.',
            'label.unique' => 'Nama peran sudah dipakai.',
            'label.not_in' => 'Nama peran ini dicadangkan.',
            'permissions.*.exists' => 'Izin tidak dikenal.',
        ];
    }

    private function editedRole(): ?Role
    {
        $role = $this->route('role');

        return $role instanceof Role ? $role : null;
    }
}
