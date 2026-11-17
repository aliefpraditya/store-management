<?php

namespace App\Http\Requests\Tenant;

use App\Services\BarcodeLabelService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What the sticker sheet prints: how many stickers each item gets (`labels`, by product id) and the stock they are
 * printed on. It is a GET, so the sheet opens in a window of its own and can be reloaded.
 */
class BarcodeLabelSheetRequest extends FormRequest
{
    use ReadsLabelStock;

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
            'labels' => ['required', 'array', 'min:1', 'max:'.BarcodeLabelService::MAX_PRODUCTS],
            'labels.*' => ['integer', 'min:1', 'max:'.BarcodeLabelService::MAX_COPIES_PER_PRODUCT],
            ...$this->stockRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'labels.required' => 'Isi jumlah barcode yang mau dicetak.',
            'labels.min' => 'Isi jumlah barcode yang mau dicetak.',
            'labels.max' => 'Paling banyak '.BarcodeLabelService::MAX_PRODUCTS.' barang sekali cetak barcode.',
            'labels.*.min' => 'Jumlah barcode minimal 1.',
            'labels.*.max' => 'Paling banyak '.BarcodeLabelService::MAX_COPIES_PER_PRODUCT.' barcode per barang.',
            ...$this->stockMessages(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (array_sum((array) $this->input('labels')) > BarcodeLabelService::MAX_LABELS) {
                    $validator->errors()->add('labels', 'Paling banyak '.BarcodeLabelService::MAX_LABELS.' barcode sekali cetak.');
                }
            },
        ];
    }

    /**
     * @return array<int, int> stickers wanted, by product id
     */
    public function copiesByProductId(): array
    {
        return collect($this->validated('labels'))
            ->mapWithKeys(fn (mixed $copies, mixed $productId): array => [(int) $productId => (int) $copies])
            ->all();
    }
}
