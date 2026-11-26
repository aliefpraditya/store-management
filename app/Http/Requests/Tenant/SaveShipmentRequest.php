<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Concerns\ValidatesPackingListFiles;
use App\Models\Shipment;
use App\Services\ShipmentAttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A shipment with its purchases and packing list as the form sends it: a new one (POST, which carries a `client_uuid`) or the
 * changes to a draft (PUT; the shipment is the `shipment` route parameter). `allocations` is the packing list, one row per
 * container and purchase line.
 */
class SaveShipmentRequest extends FormRequest
{
    use ValidatesPackingListFiles;

    private const DATE_FORMAT = 'Y-m-d';

    private const MAX_PURCHASES = 50;

    private const MAX_ROWS = 1000;

    private const MAX_QUANTITY = 999999999;

    private const MAX_FREIGHT_COST = 999999999999;

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
            'client_uuid' => [$this->route('shipment') instanceof Shipment ? 'prohibited' : 'required', 'uuid'],
            'carrier_id' => ['required', 'integer', Rule::exists('carriers', 'id')],
            'vessel_name' => ['nullable', 'string', 'max:100'],
            'departure_date' => ['nullable', 'date_format:'.self::DATE_FORMAT],
            'estimated_arrival_date' => ['nullable', 'date_format:'.self::DATE_FORMAT, 'after_or_equal:departure_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'purchase_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_PURCHASES],
            'purchase_ids.*' => ['integer', 'distinct', Rule::exists('purchases', 'id')],
            'allocations' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'allocations.*.purchase_item_id' => ['required', 'integer', Rule::exists('purchase_items', 'id')],
            'allocations.*.container_number' => ['required', 'string', 'max:50'],
            'allocations.*.quantity' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'],
            'allocations.*.freight_cost' => ['required', 'numeric', 'min:0', 'max:'.self::MAX_FREIGHT_COST, 'decimal:0,2'],
            'attachments' => ['nullable', 'array', 'max:'.ShipmentAttachmentService::MAX_PER_SHIPMENT],
            'attachments.*' => $this->packingListFileRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_uuid.required' => 'Pengiriman tidak punya penanda. Muat ulang layar lalu coba lagi.',
            'client_uuid.uuid' => 'Penanda pengiriman tidak valid. Muat ulang layar lalu coba lagi.',
            'carrier_id.required' => 'Pilih ekspedisi.',
            'carrier_id.exists' => 'Ekspedisi tidak ditemukan.',
            'vessel_name.max' => 'Nama kapal terlalu panjang.',
            'departure_date.date_format' => 'Tanggal berangkat tidak valid.',
            'estimated_arrival_date.date_format' => 'Estimasi sampai tidak valid.',
            'estimated_arrival_date.after_or_equal' => 'Estimasi sampai tidak boleh sebelum tanggal berangkat.',
            'purchase_ids.required' => 'Tambahkan minimal satu nota.',
            'purchase_ids.min' => 'Tambahkan minimal satu nota.',
            'purchase_ids.max' => 'Satu pengiriman paling banyak '.self::MAX_PURCHASES.' nota.',
            'purchase_ids.*.exists' => 'Ada nota yang tidak ditemukan.',
            'allocations.*.purchase_item_id.exists' => 'Ada barang yang tidak ditemukan.',
            'allocations.*.container_number.required' => 'Isi nomor container tiap baris.',
            'allocations.*.container_number.max' => 'Nomor container terlalu panjang.',
            'allocations.*.quantity.required' => 'Isi jumlah tiap baris.',
            'allocations.*.quantity.numeric' => 'Jumlah harus berupa angka.',
            'allocations.*.quantity.gt' => 'Jumlah harus lebih dari nol.',
            'allocations.*.quantity.decimal' => 'Jumlah maksimal 3 angka desimal.',
            'allocations.*.freight_cost.required' => 'Isi biaya tiap baris (boleh 0).',
            'allocations.*.freight_cost.numeric' => 'Biaya harus berupa angka.',
            'allocations.*.freight_cost.min' => 'Biaya tidak boleh negatif.',
            'allocations.*.freight_cost.decimal' => 'Biaya maksimal 2 angka desimal.',
            ...$this->packingListFileMessages(),
        ];
    }
}
