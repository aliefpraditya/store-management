<?php

namespace App\Http\Requests\Tenant;

use App\Services\StoreSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The store's settings page: what its paper says and how wide the receipt roll is, and two rules of the stock opname.
 */
class SaveStoreSettingsRequest extends FormRequest
{
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
            'receipt_name' => ['nullable', 'string', 'max:255'],
            'receipt_address' => ['nullable', 'string', 'max:500'],
            'receipt_phone' => ['nullable', 'string', 'max:100'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
            'paper_width' => ['required', 'integer', Rule::in(StoreSettingService::PAPER_WIDTHS)],
            'large_variance_threshold' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_AMOUNT, 'decimal:0,2'],
            'lock_sales_during_opname' => ['required', 'boolean'],
            'auto_print_receipt' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paper_width.in' => 'Lebar kertas nota hanya 58 atau 80 mm.',
            'large_variance_threshold.numeric' => 'Ambang selisih harus berupa angka.',
            'large_variance_threshold.min' => 'Ambang selisih tidak boleh negatif.',
            'large_variance_threshold.decimal' => 'Ambang selisih maksimal 2 angka desimal.',
        ];
    }
}
