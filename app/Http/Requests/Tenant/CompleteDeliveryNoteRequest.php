<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * How the delivery ended: for every item of the note, how much arrived and how much came back.
 */
class CompleteDeliveryNoteRequest extends FormRequest
{
    private const MAX_QUANTITY = 999999;

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
            'results' => ['required', 'array', 'min:1'],
            'results.*.item_id' => ['required', 'integer'],
            'results.*.quantity_delivered' => ['required', 'numeric', 'min:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'],
            'results.*.quantity_returned' => ['required', 'numeric', 'min:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'results.required' => 'Isi hasil pengiriman untuk setiap barang.',
            'results.*.quantity_delivered.min' => 'Jumlah terkirim tidak boleh negatif.',
            'results.*.quantity_returned.min' => 'Jumlah kembali tidak boleh negatif.',
            'results.*.quantity_delivered.decimal' => 'Jumlah maksimal 3 angka desimal.',
            'results.*.quantity_returned.decimal' => 'Jumlah maksimal 3 angka desimal.',
        ];
    }
}
