<?php

namespace App\Http\Requests\Central;

use App\Models\User;
use App\Rules\NotDeactivatingOwnAccount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The active toggle on the user list; the user being changed is the `user` route parameter.
 */
class SetUserActiveRequest extends FormRequest
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
        $changedUser = $this->route('user');

        return [
            'is_active' => [
                'required', 'boolean',
                new NotDeactivatingOwnAccount($changedUser instanceof User ? $changedUser : null, $this->user()),
            ],
        ];
    }
}
