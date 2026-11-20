<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The money received for a delivered COD sale. Whether the amount is the one owed, and whether it can be received at all,
 * is up to the service: it needs the sale under lock.
 */
class ReceiveCodSettlementRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::forCashier()))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,2'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'difference_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'method.required' => 'Pilih cara uang diterima.',
            'method.in' => 'Uang COD hanya bisa diterima lewat tunai, transfer, QRIS, atau EDC.',
            'amount.required' => 'Isi jumlah yang diterima.',
            'amount.numeric' => 'Jumlah yang diterima harus berupa angka.',
            'amount.gt' => 'Jumlah yang diterima harus lebih dari 0.',
            'amount.decimal' => 'Jumlah yang diterima maksimal 2 angka desimal.',
        ];
    }
}
