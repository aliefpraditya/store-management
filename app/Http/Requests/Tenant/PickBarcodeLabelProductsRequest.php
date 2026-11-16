<?php

namespace App\Http\Requests\Tenant;

use App\Services\BarcodeLabelService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The items picked on the item list to print barcode stickers for.
 */
class PickBarcodeLabelProductsRequest extends FormRequest
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
            'product_ids' => ['required', 'array', 'min:1', 'max:'.BarcodeLabelService::MAX_PRODUCTS],
            'product_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_ids.required' => 'Pilih barang yang mau dicetak barcodenya.',
            'product_ids.min' => 'Pilih barang yang mau dicetak barcodenya.',
            'product_ids.max' => 'Paling banyak '.BarcodeLabelService::MAX_PRODUCTS.' barang sekali cetak barcode. Persempit pilihan atau cetak bertahap.',
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
