<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Concerns\ValidatesContacts;
use App\Models\Carrier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adds (POST) or edits (PUT) a carrier; the one being edited is the `carrier` route parameter.
 */
class SaveCarrierRequest extends FormRequest
{
    use ValidatesContacts;

    private const MAX_TRANSIT_DAYS = 365;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $editedCarrier = $this->route('carrier');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('carriers', 'name')->ignore($editedCarrier instanceof Carrier ? $editedCarrier->id : null),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'transit_days' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_TRANSIT_DAYS],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->contactRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama ekspedisi wajib diisi.',
            'name.unique' => 'Nama ekspedisi sudah dipakai.',
            'phone.max' => 'Nomor telepon terlalu panjang.',
            'address.max' => 'Alamat terlalu panjang.',
            'province.max' => 'Nama provinsi terlalu panjang.',
            'city.max' => 'Nama kota terlalu panjang.',
            'transit_days.integer' => 'Lama kirim harus berupa angka hari.',
            'transit_days.min' => 'Lama kirim minimal 1 hari.',
            'transit_days.max' => 'Lama kirim paling lama '.self::MAX_TRANSIT_DAYS.' hari.',
            'notes.max' => 'Catatan terlalu panjang.',
            ...$this->contactMessages(),
        ];
    }
}
