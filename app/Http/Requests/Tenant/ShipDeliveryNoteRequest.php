<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marking a delivery note as shipped: the driver is chosen now from the store's active drivers, and may be left out.
 */
class ShipDeliveryNoteRequest extends FormRequest
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
            'driver_id' => ['nullable', 'integer', Rule::exists('drivers', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['driver_id.exists' => 'Sopir tidak ditemukan atau sudah dinonaktifkan. Pilih sopir lain.'];
    }
}
