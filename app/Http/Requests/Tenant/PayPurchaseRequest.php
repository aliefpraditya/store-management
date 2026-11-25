<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PurchasePaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The payment that comes with confirming a purchase ("Konfirmasi & Bayar"): the whole nota is paid, so only how, when and a
 * note are asked.
 */
class PayPurchaseRequest extends FormRequest
{
    private const DATE_FORMAT = 'Y-m-d';

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
            'payment_method' => ['required', Rule::enum(PurchasePaymentMethod::class)],
            'paid_date' => ['required', 'date_format:'.self::DATE_FORMAT, 'before_or_equal:today'],
            'payment_notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Pilih cara bayar.',
            'payment_method.enum' => 'Cara bayar tidak dikenal.',
            'paid_date.required' => 'Isi tanggal bayar.',
            'paid_date.date_format' => 'Tanggal bayar tidak valid.',
            'paid_date.before_or_equal' => 'Tanggal bayar tidak boleh di masa depan.',
            'payment_notes.max' => 'Catatan pembayaran terlalu panjang.',
        ];
    }
}
