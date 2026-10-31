<?php

namespace App\Http\Requests\Central;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for both creating (POST) and editing (PUT) a user; the user being edited is the `user` route parameter.
 */
class SaveUserRequest extends FormRequest
{
    private const MIN_PASSWORD_LENGTH = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $editedUser = $this->editedUser();

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($editedUser),
            ],
            'password' => [
                $editedUser === null ? 'required' : 'nullable',
                'string', 'min:'.self::MIN_PASSWORD_LENGTH,
            ],
            'role' => ['required', Rule::enum(UserRole::class), $this->ownRoleRule()],
            'store_id' => [
                Rule::requiredIf(fn (): bool => $this->enum('role', UserRole::class)?->isStoreRole() ?? false),
                'nullable', 'integer', Rule::exists('stores', 'id'),
            ],
            'is_active' => ['required', 'boolean', $this->ownActiveRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.min' => 'Username minimal :min karakter.',
            'username.regex' => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, atau strip.',
            'username.unique' => 'Username sudah dipakai.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal :min karakter.',
            'role.required' => 'Pilih peran.',
            'role.enum' => 'Peran tidak dikenal.',
            'store_id.required' => 'Pilih toko.',
            'store_id.exists' => 'Toko tidak ditemukan.',
        ];
    }

    private function editedUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }

    private function isEditingOwnAccount(): bool
    {
        return $this->editedUser()?->is($this->user()) ?? false;
    }

    /**
     * Stops a super admin from locking themself out by changing their own role.
     */
    private function ownRoleRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($this->isEditingOwnAccount() && $value !== $this->editedUser()->role?->value) {
                $fail('Anda tidak bisa mengubah peran akun sendiri.');
            }
        };
    }

    /**
     * Stops a user from deactivating their own account.
     */
    private function ownActiveRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($this->isEditingOwnAccount() && ! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                $fail('Anda tidak bisa menonaktifkan akun sendiri.');
            }
        };
    }
}
