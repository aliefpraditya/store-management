<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Stops a user from deactivating their own account, whichever screen sends the change.
 */
class NotDeactivatingOwnAccount implements ValidationRule
{
    public function __construct(private readonly ?User $editedUser, private readonly ?User $actingUser) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isEditingOwnAccount = $this->editedUser?->is($this->actingUser) ?? false;

        if ($isEditingOwnAccount && ! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $fail('Anda tidak bisa menonaktifkan akun sendiri.');
        }
    }
}
