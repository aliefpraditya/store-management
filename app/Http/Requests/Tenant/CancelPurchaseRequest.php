<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The reason a purchase is cancelled, which stays with it on the list.
 */
class CancelPurchaseRequest extends FormRequest
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
        return ['reason' => ['required', 'string', 'max:255']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reason.required' => 'Isi alasan pembatalan.'];
    }
}
