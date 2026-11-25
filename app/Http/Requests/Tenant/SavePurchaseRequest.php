<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PurchaseStatus;
use App\Enums\WarehouseType;
use App\Models\Purchase;
use App\Services\PurchaseAttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * A purchase (nota) with its lines as the form sends it: a new one (POST, which carries a `client_uuid`) or the changes to a
 * draft (PUT; the purchase is the `purchase` route parameter). Nota photos or PDFs may come along as `attachments`.
 */
class SavePurchaseRequest extends FormRequest
{
    private const DATE_FORMAT = 'Y-m-d';

    private const MAX_ITEMS = 300;

    private const MAX_FILE_KILOBYTES = 5120;

    private const MAX_QUANTITY = 999999999;

    private const MAX_UNIT_COST = 999999999999;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $editedPurchase = $this->route('purchase');

        return [
            'client_uuid' => [$editedPurchase instanceof Purchase ? 'prohibited' : 'required', 'uuid'],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'supplier_document_number' => ['nullable', 'string', 'max:100', $this->documentNumberIsFreeRule($editedPurchase instanceof Purchase ? $editedPurchase : null)],
            'purchase_date' => ['required', 'date_format:'.self::DATE_FORMAT],
            'expected_date' => ['nullable', 'date_format:'.self::DATE_FORMAT, 'after_or_equal:purchase_date'],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('type', WarehouseType::Sellable->value)->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.unit_code' => ['required', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'],
            'items.*.unit_cost' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_UNIT_COST, 'decimal:0,2'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'attachments' => ['sometimes', 'array', 'max:'.PurchaseAttachmentService::MAX_PER_PURCHASE],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.self::MAX_FILE_KILOBYTES],
        ];
    }

    /**
     * A supplier's nota number is used once among the purchases that were not cancelled. The purchase being edited does not
     * count against itself, and neither does the one this very request already made: a double tap sends the same
     * `client_uuid` again, and must get that purchase back rather than a complaint about its own number.
     */
    private function documentNumberIsFreeRule(?Purchase $editedPurchase): Unique
    {
        $rule = Rule::unique('purchases', 'supplier_document_number')
            ->where('supplier_id', $this->input('supplier_id'))
            ->where(fn ($purchases) => $purchases->where('status', '!=', PurchaseStatus::Cancelled->value));

        if ($editedPurchase !== null) {
            return $rule->ignore($editedPurchase->id);
        }

        return filled($this->input('client_uuid')) ? $rule->ignore($this->input('client_uuid'), 'client_uuid') : $rule;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_uuid.required' => 'Nota tidak punya penanda. Muat ulang layar lalu coba lagi.',
            'client_uuid.uuid' => 'Penanda nota tidak valid. Muat ulang layar lalu coba lagi.',
            'supplier_id.required' => 'Pilih supplier.',
            'supplier_id.exists' => 'Supplier tidak ditemukan.',
            'supplier_document_number.unique' => 'Nomor nota supplier ini sudah dipakai di nota lain dari supplier yang sama.',
            'purchase_date.required' => 'Isi tanggal nota.',
            'purchase_date.date_format' => 'Tanggal nota tidak valid.',
            'expected_date.date_format' => 'Perkiraan tanggal tiba tidak valid.',
            'expected_date.after_or_equal' => 'Perkiraan tiba tidak boleh sebelum tanggal nota.',
            'warehouse_id.required' => 'Pilih gudang tujuan.',
            'warehouse_id.exists' => 'Gudang tujuan harus gudang jual yang aktif.',
            'items.required' => 'Tambahkan minimal satu barang.',
            'items.min' => 'Tambahkan minimal satu barang.',
            'items.max' => 'Satu nota paling banyak '.self::MAX_ITEMS.' barang.',
            'items.*.product_id.required' => 'Pilih barangnya.',
            'items.*.product_id.exists' => 'Ada barang yang tidak ditemukan.',
            'items.*.unit_code.required' => 'Pilih satuan tiap barang.',
            'items.*.quantity.required' => 'Isi jumlah tiap barang.',
            'items.*.quantity.numeric' => 'Jumlah harus berupa angka.',
            'items.*.quantity.gt' => 'Jumlah harus lebih dari nol.',
            'items.*.quantity.decimal' => 'Jumlah maksimal 3 angka desimal.',
            'items.*.unit_cost.required' => 'Isi harga tiap barang.',
            'items.*.unit_cost.numeric' => 'Harga harus berupa angka.',
            'items.*.unit_cost.gt' => 'Harga harus lebih dari nol.',
            'items.*.unit_cost.decimal' => 'Harga maksimal 2 angka desimal.',
            'attachments.max' => 'Satu nota paling banyak '.PurchaseAttachmentService::MAX_PER_PURCHASE.' lampiran.',
            'attachments.*.mimes' => 'Lampiran harus berupa foto (JPG, PNG, WebP) atau PDF.',
            'attachments.*.max' => 'Satu lampiran paling besar 5 MB.',
            'attachments.*.uploaded' => 'Lampiran gagal diunggah. Coba lagi.',
        ];
    }
}
