<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new delivery note for one receipt: who it goes to and how much of which receipt lines. A line left at zero is
 * simply not on the note.
 */
class SaveDeliveryNoteRequest extends FormRequest
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
            'client_uuid' => ['required', 'uuid'],
            'sale_id' => ['required', 'integer', Rule::exists('sales', 'id')],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:255'],
            'recipient_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', Rule::exists('sale_items', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_uuid.required' => 'Pengajuan surat jalan tidak punya penanda. Muat ulang layar lalu coba lagi.',
            'client_uuid.uuid' => 'Penanda pengajuan surat jalan tidak valid. Muat ulang layar lalu coba lagi.',
            'sale_id.required' => 'Pilih nota.',
            'sale_id.exists' => 'Nota tidak ditemukan.',
            'recipient_name.required' => 'Nama penerima wajib diisi.',
            'items.required' => 'Pilih minimal satu barang yang diantar.',
            'items.min' => 'Pilih minimal satu barang yang diantar.',
            'items.*.quantity.min' => 'Jumlah tidak boleh negatif.',
            'items.*.quantity.decimal' => 'Jumlah maksimal 3 angka desimal.',
        ];
    }
}
