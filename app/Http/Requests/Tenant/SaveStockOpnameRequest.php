<?php

namespace App\Http\Requests\Tenant;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Models\Warehouse;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Rule;

/**
 * Starts a new opname: the header only. A category/brand opname takes its products when it is created, a selected one
 * gets them as they are added by hand.
 */
class SaveStockOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An opname of a returns warehouse is always the inspection of what waits there: its products are the ones in stock, so
     * the reason and the scope are not asked.
     */
    protected function prepareForValidation(): void
    {
        if ($this->chosenWarehouse()?->isReturns()) {
            $this->merge(['reason_code' => StockOpnameReason::ReturnsCheck->value, 'scope' => StockOpnameScope::Selected->value]);
        }
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
            'reason_code' => ['required', Rule::enum(StockOpnameReason::class)->only([...StockOpnameReason::selectable(), StockOpnameReason::ReturnsCheck]), $this->isReturnsCheckOnlyForReturnsWarehouse(...)],
        ];
    }

    /**
     * The inspection of returns belongs to a returns warehouse; for any other warehouse it is not a reason to choose.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    private function isReturnsCheckOnlyForReturnsWarehouse(string $attribute, mixed $value, Closure $fail): void
    {
        $isReturnsWarehouse = $this->chosenWarehouse()?->isReturns() ?? false;

        if ($value === StockOpnameReason::ReturnsCheck->value && ! $isReturnsWarehouse) {
            $fail('Pemeriksaan retur hanya untuk gudang retur.');
        }
    }

    /**
     * The warehouse typed in the form, or null when it is missing or not a single id (an array would make `find` return
     * a list and break the checks before validation can refuse it).
     */
    private function chosenWarehouse(): ?Warehouse
    {
        $warehouseId = $this->input('warehouse_id');

        return is_scalar($warehouseId) ? Warehouse::query()->find($warehouseId) : null;
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
