<?php

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adds a product to an opname, optionally with its count (`parts`: one quantity per unit, e.g. 5 DUS and 3 PAIL) so adding
 * and counting a shelf item is one step.
 */
class AddStockOpnameProductRequest extends FormRequest
{
    private const MAX_QUANTITY = 999999999;

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
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'parts' => ['nullable', 'array', 'min:1'],
            'parts.*.unit_code' => ['required', 'string'],
            'parts.*.quantity' => ['required', 'numeric', 'min:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'],
        ];
    }

    /**
     * Each unit must be one of the product's units.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->input('parts') === null) {
                    return;
                }

                $unitCodes = Product::query()->findOrFail($this->input('product_id'))->productUnits()->pluck('unit_code')->all();

                foreach ($this->input('parts') as $partIndex => $part) {
                    if (! in_array($part['unit_code'], $unitCodes, true)) {
                        $validator->errors()->add("parts.{$partIndex}.unit_code", 'Satuan tidak dipakai barang ini.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Pilih barang.',
            'product_id.exists' => 'Barang tidak ditemukan.',
            'parts.*.quantity.required' => 'Isi jumlah hitungan.',
            'parts.*.quantity.numeric' => 'Jumlah harus berupa angka.',
            'parts.*.quantity.min' => 'Jumlah tidak boleh negatif.',
            'parts.*.quantity.decimal' => 'Jumlah maksimal 3 angka desimal.',
            'parts.*.quantity.max' => 'Jumlah terlalu besar.',
        ];
    }
}
