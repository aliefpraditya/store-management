<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PaymentMethod;
use App\Enums\ReturnDisposition;
use App\Enums\ReturnResolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new return for one receipt: how much of which receipt lines comes back, where the goods go, and how the money
 * is paid back. A line left at zero is simply not part of the return.
 */
class SaveSaleReturnRequest extends FormRequest
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
            'resolution' => ['nullable', Rule::enum(ReturnResolution::class)],
            'refund_method' => ['required', Rule::in($this->paymentMethodValues())],
            'refund_account' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'distinct', Rule::exists('sale_items', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'],
            'items.*.disposition' => ['required', Rule::enum(ReturnDisposition::class)],
            'exchange_items' => [Rule::requiredIf(fn (): bool => $this->input('resolution') === ReturnResolution::Exchange->value), 'array'],
            'exchange_items.*.product_unit_id' => ['required', 'integer', Rule::exists('product_units', 'id')],
            'exchange_items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'],
            'exchange_payments' => ['nullable', 'array'],
            'exchange_payments.*.method' => ['required', Rule::in($this->paymentMethodValues())],
            'exchange_payments.*.amount' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'exchange_payments.*.reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The ways money changes hands at the till; return credit is only made by an exchange itself.
     *
     * @return list<string>
     */
    private function paymentMethodValues(): array
    {
        return array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::forCashier());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_uuid.required' => 'Pengajuan retur tidak punya penanda. Muat ulang layar lalu coba lagi.',
            'client_uuid.uuid' => 'Penanda pengajuan retur tidak valid. Muat ulang layar lalu coba lagi.',
            'sale_id.required' => 'Pilih nota.',
            'sale_id.exists' => 'Nota tidak ditemukan.',
            'refund_method.required' => 'Pilih cara pengembalian uang.',
            'refund_method.in' => 'Cara pengembalian uang tidak dikenal.',
            'items.required' => 'Pilih minimal satu barang yang diretur.',
            'items.min' => 'Pilih minimal satu barang yang diretur.',
            'items.*.quantity.min' => 'Jumlah tidak boleh negatif.',
            'items.*.quantity.decimal' => 'Jumlah maksimal 3 angka desimal.',
            'items.*.disposition.required' => 'Pilih tujuan barang: dijual lagi atau dead stock.',
            'exchange_items.required' => 'Pilih barang pengganti.',
            'exchange_items.*.quantity.gt' => 'Jumlah barang pengganti harus lebih dari nol.',
            'exchange_items.*.quantity.decimal' => 'Jumlah maksimal 3 angka desimal.',
            'exchange_payments.*.amount.min' => 'Jumlah pembayaran tidak boleh negatif.',
        ];
    }
}
