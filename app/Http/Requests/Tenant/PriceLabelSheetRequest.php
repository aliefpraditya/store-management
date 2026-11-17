<?php

namespace App\Http\Requests\Tenant;

use App\Services\PriceLabelService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What the price label sheet prints: how many labels each unit of an item gets (`labels`, by product unit id) and the
 * stock they are printed on. It is a GET, so the sheet prints from a frame that can be reloaded.
 */
class PriceLabelSheetRequest extends FormRequest
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
            // Every entry needs at least one label, so the total cap below also bounds how many entries there can be.
            'labels' => ['required', 'array', 'min:1', 'max:'.PriceLabelService::MAX_LABELS],
            'labels.*' => ['integer', 'min:1', 'max:'.PriceLabelService::MAX_COPIES_PER_UNIT],
            ...$this->stockRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'labels.required' => 'Isi jumlah label harga yang mau dicetak.',
            'labels.min' => 'Isi jumlah label harga yang mau dicetak.',
            'labels.max' => 'Paling banyak '.PriceLabelService::MAX_LABELS.' label harga sekali cetak.',
            'labels.*.min' => 'Jumlah label harga minimal 1.',
            'labels.*.max' => 'Paling banyak '.PriceLabelService::MAX_COPIES_PER_UNIT.' label harga per satuan.',
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
                if (array_sum((array) $this->input('labels')) > PriceLabelService::MAX_LABELS) {
                    $validator->errors()->add('labels', 'Paling banyak '.PriceLabelService::MAX_LABELS.' label harga sekali cetak.');
                }
            },
        ];
    }

    /**
     * @return array<int, int> labels wanted, by product unit id
     */
    public function copiesByProductUnitId(): array
    {
        return collect($this->validated('labels'))
            ->mapWithKeys(fn (mixed $copies, mixed $productUnitId): array => [(int) $productUnitId => (int) $copies])
            ->all();
    }
}
