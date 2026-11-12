<?php

namespace App\Http\Requests\Tenant;

use App\Enums\CostChangeMethod;
use App\Enums\SellingChangeMethod;
use App\Services\BulkPriceService;
use App\Services\PriceChangeCalculator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The items picked and the price change to run on them, for both the preview and the apply.
 *
 * @phpstan-import-type PriceChange from PriceChangeCalculator
 */
class BulkPriceRequest extends FormRequest
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
        $sellingMethod = SellingChangeMethod::tryFrom((string) $this->input('selling_method'));

        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:'.BulkPriceService::MAX_PRODUCTS],
            'product_ids.*' => ['integer', 'distinct'],
            'unit_code' => ['nullable', 'string', Rule::exists('units', 'code')],
            'cost_method' => ['nullable', Rule::enum(CostChangeMethod::class)],
            'cost_value' => [Rule::requiredIf($this->filled('cost_method')), 'nullable', 'numeric', 'decimal:0,2', 'between:-999999999999,999999999999', ...$this->percentRule($this->input('cost_method') === CostChangeMethod::Percent->value), ...$this->setRule($this->input('cost_method') === CostChangeMethod::Set->value)],
            'selling_method' => ['nullable', Rule::enum(SellingChangeMethod::class)],
            'selling_value' => [Rule::requiredIf($sellingMethod?->takesValue() ?? false), 'nullable', 'numeric', 'decimal:0,2', 'between:-999999999999,999999999999', ...$this->percentRule($sellingMethod === SellingChangeMethod::Percent), ...$this->setRule($sellingMethod === SellingChangeMethod::Set), ...$this->marginRule($sellingMethod === SellingChangeMethod::Margin)],
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
            'unit_code.exists' => 'Satuan tidak dikenal.',
            'cost_value.required' => 'Isi angka perubahan harga beli.',
            'cost_value.numeric' => 'Perubahan harga beli harus berupa angka.',
            'cost_value.decimal' => 'Perubahan harga beli maksimal 2 angka desimal.',
            'cost_value.gt' => 'Penurunan harga beli harus kurang dari 100 persen.',
            'cost_value.gte' => 'Harga beli tidak boleh negatif.',
            'selling_value.required' => 'Isi angka perubahan harga jual.',
            'selling_value.numeric' => 'Perubahan harga jual harus berupa angka.',
            'selling_value.decimal' => 'Perubahan harga jual maksimal 2 angka desimal.',
            'selling_value.gt' => 'Penurunan harga jual harus kurang dari 100 persen.',
            'selling_value.gte' => 'Harga jual tidak boleh negatif.',
            'selling_value.min' => 'Margin tidak boleh negatif.',
            'selling_value.lt' => 'Margin harus kurang dari 100 persen.',
        ];
    }

    /**
     * A drop of 100 percent or more would take the price to zero or below.
     *
     * @return list<string>
     */
    private function percentRule(bool $isPercent): array
    {
        return $isPercent ? ['gt:-100'] : [];
    }

    /**
     * A price to set every unit to cannot be below zero.
     *
     * @return list<string>
     */
    private function setRule(bool $isSet): array
    {
        return $isSet ? ['gte:0'] : [];
    }

    /**
     * Margin is a share of the selling price, so it stays from 0 up to below 100 percent.
     *
     * @return list<string>
     */
    private function marginRule(bool $isMargin): array
    {
        return $isMargin ? ['min:0', 'lt:100'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $costMethod = $this->input('cost_method');
                $sellingMethod = $this->input('selling_method');

                if (blank($costMethod) && blank($sellingMethod)) {
                    $validator->errors()->add('cost_method', 'Pilih harga yang mau diubah: harga beli, harga jual, atau keduanya.');
                }

                $isNominalChange = $costMethod === CostChangeMethod::Nominal->value || $sellingMethod === SellingChangeMethod::Nominal->value;

                if ($isNominalChange && blank($this->input('unit_code'))) {
                    $validator->errors()->add('unit_code', 'Nominal Rp perlu dibatasi ke satu satuan: tambahan Rp yang sama tidak masuk akal untuk semua satuan (PCS dan DUS isi 12 tidak naik sama besar).');
                }

                if ($sellingMethod === SellingChangeMethod::KeepMargin->value && blank($costMethod)) {
                    $validator->errors()->add('selling_method', 'Mempertahankan margin hanya berarti bila harga beli ikut diubah.');
                }
            },
        ];
    }

    /**
     * @return PriceChange
     */
    public function change(): array
    {
        $costMethod = CostChangeMethod::tryFrom((string) $this->input('cost_method'));
        $sellingMethod = SellingChangeMethod::tryFrom((string) $this->input('selling_method'));

        return [
            'unit_code' => $this->filled('unit_code') ? (string) $this->input('unit_code') : null,
            'cost_method' => $costMethod,
            'cost_value' => $costMethod === null ? null : (string) $this->input('cost_value'),
            'selling_method' => $sellingMethod,
            'selling_value' => $sellingMethod?->takesValue() ? (string) $this->input('selling_value') : null,
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
