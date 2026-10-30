<?php

namespace App\Http\Requests\Tenant;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Starts a new opname: the header only. The products come later (all/category at start, selected by hand).
 */
class SaveStockOpnameRequest extends FormRequest
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
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'opname_date' => ['required', 'date'],
            'scope' => ['required', Rule::enum(StockOpnameScope::class)],
            'category_id' => [
                Rule::requiredIf(fn (): bool => $this->isCategoryScope() && blank($this->input('brand_id'))),
                'nullable', 'integer', Rule::exists('categories', 'id'),
            ],
            'brand_id' => [
                Rule::requiredIf(fn (): bool => $this->isCategoryScope() && blank($this->input('category_id'))),
                'nullable', 'integer', Rule::exists('brands', 'id'),
            ],
            'reason_code' => ['required', Rule::enum(StockOpnameReason::class)],
        ];
    }

    /**
     * The category/brand scope needs at least one of the two, otherwise it would be the whole store.
     */
    private function isCategoryScope(): bool
    {
        return $this->input('scope') === StockOpnameScope::Category->value;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'Pilih gudang.',
            'warehouse_id.exists' => 'Gudang tidak valid.',
            'opname_date.required' => 'Tanggal wajib diisi.',
            'scope.required' => 'Pilih cakupan.',
            'category_id.required' => 'Pilih kategori atau merek, boleh salah satu.',
            'brand_id.required' => 'Pilih kategori atau merek, boleh salah satu.',
            'reason_code.required' => 'Pilih alasan.',
        ];
    }
}
