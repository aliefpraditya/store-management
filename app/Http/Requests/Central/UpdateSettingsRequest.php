<?php

namespace App\Http\Requests\Central;

use App\Services\AppSettingService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The application-wide settings form of the Super Admin.
 */
class UpdateSettingsRequest extends FormRequest
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
            'password_min_length' => ['required', 'integer', 'between:'.AppSettingService::LOWEST_PASSWORD_MIN_LENGTH.','.AppSettingService::HIGHEST_PASSWORD_MIN_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password_min_length.required' => 'Isi panjang minimal password.',
            'password_min_length.integer' => 'Panjang minimal password harus berupa angka bulat.',
            'password_min_length.between' => 'Panjang minimal password antara :min dan :max karakter.',
        ];
    }
}
