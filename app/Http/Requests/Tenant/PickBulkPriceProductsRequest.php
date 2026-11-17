<?php

namespace App\Http\Requests\Tenant;

use App\Services\BulkPriceService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The items picked on the item list to change prices for, asked about before any change is set (which units they come in).
 */
class PickBulkPriceProductsRequest extends FormRequest
{
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
            'product_ids' => ['required', 'array', 'min:1', 'max:'.BulkPriceService::MAX_PRODUCTS],
            'product_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_ids.required' => 'Pilih barang yang mau diubah.',
            'product_ids.min' => 'Pilih barang yang mau diubah.',
            'product_ids.max' => 'Paling banyak '.BulkPriceService::MAX_PRODUCTS.' barang sekali ubah. Persempit pilihan atau ubah per kategori.',
        ];
    }

    /**
     * @return list<int>
     */
    public function productIds(): array
    {
        return array_map('intval', $this->validated('product_ids'));
    }
}
