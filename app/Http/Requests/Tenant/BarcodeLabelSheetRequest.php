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
    public const MIN_WIDTH_MM = 20;

    public const MAX_WIDTH_MM = 210;

    public const MIN_HEIGHT_MM = 10;

    public const MAX_HEIGHT_MM = 297;

    public const MAX_COLUMNS = 6;

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
            'width' => ['required', 'integer', 'between:'.self::MIN_WIDTH_MM.','.self::MAX_WIDTH_MM],
            'height' => ['required', 'integer', 'between:'.self::MIN_HEIGHT_MM.','.self::MAX_HEIGHT_MM],
            'columns' => ['nullable', 'integer', 'between:1,'.self::MAX_COLUMNS],
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
            'width.between' => 'Lebar stok '.self::MIN_WIDTH_MM.' sampai '.self::MAX_WIDTH_MM.' mm.',
            'height.between' => 'Tinggi stok '.self::MIN_HEIGHT_MM.' sampai '.self::MAX_HEIGHT_MM.' mm.',
            'columns.between' => 'Label ke samping 1 sampai '.self::MAX_COLUMNS.'.',
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

    public function widthMm(): int
    {
        return (int) $this->validated('width');
    }

    public function heightMm(): int
    {
        return (int) $this->validated('height');
    }

    /**
     * How many stickers share one row of the stock, side by side; the width is that of the whole row.
     */
    public function columns(): int
    {
        return (int) ($this->validated('columns') ?? 1);
    }
}
