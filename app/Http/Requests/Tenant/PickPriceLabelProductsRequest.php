<?php

namespace App\Http\Requests\Tenant;

use App\Services\PriceLabelService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The items picked on the item list to print price labels for.
 */
class PickPriceLabelProductsRequest extends FormRequest
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
            'product_ids' => ['required', 'array', 'min:1', 'max:'.PriceLabelService::MAX_PRODUCTS],
            'product_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_ids.required' => 'Pilih barang yang mau dicetak label harganya.',
            'product_ids.min' => 'Pilih barang yang mau dicetak label harganya.',
            'product_ids.max' => 'Paling banyak '.PriceLabelService::MAX_PRODUCTS.' barang sekali cetak label harga. Persempit pilihan atau cetak bertahap.',
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
