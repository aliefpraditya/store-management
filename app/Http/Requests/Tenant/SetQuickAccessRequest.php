<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Puts one item in quick access or takes it out. The route's `products.manage` permission is the authorization.
 */
class SetQuickAccessRequest extends FormRequest
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
            'is_in_quick_access' => ['required', 'boolean'],
        ];
    }
}
