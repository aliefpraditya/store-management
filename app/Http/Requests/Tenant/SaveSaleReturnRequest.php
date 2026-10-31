<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PaymentMethod;
use App\Enums\ReturnDisposition;
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
            'sale_id' => ['required', 'integer', Rule::exists('sales', 'id')],
            'refund_method' => ['required', Rule::in(array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::forCashier()))],
            'reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'distinct', Rule::exists('sale_items', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'],
            'items.*.disposition' => ['required', Rule::enum(ReturnDisposition::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sale_id.required' => 'Pilih nota.',
            'sale_id.exists' => 'Nota tidak ditemukan.',
            'refund_method.required' => 'Pilih cara pengembalian uang.',
            'refund_method.in' => 'Cara pengembalian uang tidak dikenal.',
            'items.required' => 'Pilih minimal satu barang yang diretur.',
            'items.min' => 'Pilih minimal satu barang yang diretur.',
            'items.*.quantity.min' => 'Jumlah tidak boleh negatif.',
            'items.*.quantity.decimal' => 'Jumlah maksimal 3 angka desimal.',
            'items.*.disposition.required' => 'Pilih tujuan barang: dijual lagi atau dead stock.',
        ];
    }
}
