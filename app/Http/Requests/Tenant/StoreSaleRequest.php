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
            'payments.*.method' => ['required', Rule::in(array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::forSale()))],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_AMOUNT, 'decimal:0,2'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
            'delivery_note' => ['nullable', 'array'],
            'delivery_note.recipient_name' => [Rule::requiredIf($this->isCod(...)), 'nullable', 'string', 'max:255'],
            'delivery_note.recipient_address' => [Rule::requiredIf($this->isCod(...)), 'nullable', 'string', 'max:1000'],
            'delivery_note.recipient_phone' => ['nullable', 'string', 'max:255'],
            'delivery_note.driver_name' => ['nullable', 'string', 'max:255'],
            'delivery_note.vehicle_number' => ['nullable', 'string', 'max:255'],
            'delivery_note.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * A COD sale goes out with a delivery note, so who and where to deliver are asked at the same time.
     */
    private function isCod(): bool
    {
        return collect($this->input('payments'))->contains(fn (mixed $payment): bool => is_array($payment) && ($payment['method'] ?? null) === PaymentMethod::Cod->value);
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
            'delivery_note.recipient_name.required' => 'Nama penerima wajib diisi untuk nota COD.',
            'delivery_note.recipient_address.required' => 'Alamat pengantaran wajib diisi untuk nota COD.',
        ];
    }
}
