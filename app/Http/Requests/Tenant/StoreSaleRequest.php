<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The cart and the payments as the cashier screen sends them. Only the product unit and the quantity of each line
 * are used: prices come from the database.
 */
class StoreSaleRequest extends FormRequest
{
    private const MAX_QUANTITY = 999999;

    private const MAX_AMOUNT = 999999999999;

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
            'customer_name' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_unit_id' => ['required', 'integer', Rule::exists('product_units', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', Rule::in(array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::forCashier()))],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_AMOUNT, 'decimal:0,2'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Keranjang masih kosong.',
            'items.min' => 'Keranjang masih kosong.',
            'items.*.quantity.gt' => 'Jumlah barang harus lebih dari 0.',
            'items.*.quantity.decimal' => 'Jumlah barang maksimal 3 angka desimal.',
            'payments.required' => 'Isi pembayaran.',
            'payments.min' => 'Isi pembayaran.',
            'payments.*.method.in' => 'Metode pembayaran tidak valid.',
            'payments.*.amount.gt' => 'Jumlah pembayaran harus lebih dari 0.',
            'payments.*.amount.decimal' => 'Jumlah pembayaran maksimal 2 angka desimal.',
        ];
    }
}
