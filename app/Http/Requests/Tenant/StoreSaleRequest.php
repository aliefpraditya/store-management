<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What one press of "Bayar" sends: the cart and the payments of the receipt, and optionally an `order` (a customer
 * order for the part of the cart that is not in stock) or a `customer_order_id` (collecting an earlier order, whose lines
 * and prices come from the order itself). Only the product unit and the quantity of each line are used: prices come
 * from the database.
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
            'customer_order_id' => ['nullable', 'integer', 'prohibits:items,order', Rule::exists('customer_orders', 'id')],
            'items' => ['nullable', 'array', 'required_without_all:order,customer_order_id'],
            'items.*.product_unit_id' => ['required', 'integer', Rule::exists('product_units', 'id')],
            'items.*.quantity' => $this->quantityRules(),
            'payments' => ['nullable', 'array', 'required_with:items,order.deposit_amount'],
            'payments.*.method' => ['required', Rule::in(array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::forSale()))],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_AMOUNT, 'decimal:0,2'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
            'hold_delivery' => ['nullable', 'boolean', Rule::prohibitedIf(fn (): bool => ! $this->filled('order'))],
            'delivery_note' => ['nullable', 'array'],
            'delivery_note.recipient_name' => [Rule::requiredIf($this->isDeliveryNoteNeeded(...)), 'nullable', 'string', 'max:255'],
            'delivery_note.recipient_address' => [Rule::requiredIf($this->isDeliveryNoteNeeded(...)), 'nullable', 'string', 'max:1000'],
            'delivery_note.recipient_phone' => ['nullable', 'string', 'max:255'],
            'delivery_note.driver_name' => ['nullable', 'string', 'max:255'],
            'delivery_note.vehicle_number' => ['nullable', 'string', 'max:255'],
            'delivery_note.notes' => ['nullable', 'string', 'max:1000'],
            'delivery_note.include_linked_sale_items' => ['nullable', 'boolean'],
            'order' => ['nullable', 'array'],
            'order.client_uuid' => ['required_with:order', 'uuid'],
            'order.customer_name' => ['required_with:order', 'string', 'max:255'],
            'order.customer_phone' => ['required_with:order', 'string', 'max:255'],
            'order.customer_address' => ['nullable', 'string', 'max:1000'],
            'order.source_note' => ['nullable', 'string', 'max:255'],
            'order.expected_date' => ['nullable', 'date', 'after_or_equal:today'],
            'order.items' => ['required_with:order', 'array', 'min:1'],
            'order.items.*.product_unit_id' => ['required', 'integer', Rule::exists('product_units', 'id')],
            'order.items.*.quantity' => $this->quantityRules(),
            'order.deposit_amount' => ['nullable', 'numeric', 'gt:0', 'max:'.self::MAX_AMOUNT, 'decimal:0,2'],
        ];
    }

    /**
     * @return list<mixed>
     */
    private function quantityRules(): array
    {
        return ['required', 'numeric', 'gt:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'];
    }

    /**
     * A COD sale goes out with a delivery note, so who and where to deliver are asked at the same time, unless the delivery
     * waits for the order made with it.
     */
    private function isDeliveryNoteNeeded(): bool
    {
        return $this->isCod() && ! $this->boolean('hold_delivery');
    }

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
            'items.required_without_all' => 'Keranjang masih kosong.',
            'items.*.quantity.gt' => 'Jumlah barang harus lebih dari 0.',
            'items.*.quantity.decimal' => 'Jumlah barang maksimal 3 angka desimal.',
            'payments.required_with' => 'Isi pembayaran.',
            'payments.*.method.in' => 'Metode pembayaran tidak valid.',
            'payments.*.amount.gt' => 'Jumlah pembayaran harus lebih dari 0.',
            'payments.*.amount.decimal' => 'Jumlah pembayaran maksimal 2 angka desimal.',
            'delivery_note.recipient_name.required' => 'Nama penerima wajib diisi untuk nota COD.',
            'delivery_note.recipient_address.required' => 'Alamat pengantaran wajib diisi untuk nota COD.',
            'order.customer_name.required_with' => 'Nama pelanggan wajib diisi untuk pesanan.',
            'order.customer_phone.required_with' => 'Nomor telepon pelanggan wajib diisi untuk pesanan.',
            'order.items.required_with' => 'Pesanan belum berisi barang.',
            'order.items.*.quantity.gt' => 'Jumlah barang pesanan harus lebih dari 0.',
            'order.items.*.quantity.decimal' => 'Jumlah barang pesanan maksimal 3 angka desimal.',
            'order.expected_date.after_or_equal' => 'Perkiraan barang datang tidak boleh sebelum hari ini.',
            'order.deposit_amount.gt' => 'Uang muka harus lebih dari 0.',
            'order.deposit_amount.decimal' => 'Uang muka maksimal 2 angka desimal.',
            'customer_order_id.prohibits' => 'Pengambilan pesanan tidak bisa digabung dengan barang lain.',
        ];
    }
}
