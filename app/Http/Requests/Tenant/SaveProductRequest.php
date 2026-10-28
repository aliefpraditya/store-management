<?php

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use App\Models\Unit;
use Brick\Math\BigDecimal;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adds (POST) or edits (PUT) a product together with all of its units.
 *
 * The first unit is the base unit (conversion 1); every unit has its own purchase price (`cost_price`). Category, brand and unit may be an existing record
 * (`id`) or a new one typed by name. Rules follow analisis-sistem 6.2a and 7.1.
 */
class SaveProductRequest extends FormRequest
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
        $editedProduct = $this->editedProduct();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($editedProduct)],
            'category' => ['required', 'array'],
            'category.id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'category.name' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'array'],
            'brand.id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'brand.name' => ['nullable', 'string', 'max:255'],
            'min_stock' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,3'],
            'is_active' => ['required', 'boolean'],
            'units' => ['required', 'array', 'min:1'],
            'units.*.id' => ['nullable', 'integer'],
            'units.*.unit' => ['required', 'array'],
            'units.*.unit.code' => ['required', 'string', Rule::exists('units', 'code')],
            'units.*.conversion_factor' => ['required', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,3'],
            'units.*.cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'units.*.is_sellable' => ['required', 'boolean'],
            'units.*.selling_price' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'units.*.is_default_sale' => ['required', 'boolean'],
        ];

        foreach (array_keys((array) $this->input('units', [])) as $index) {
            $rules["units.{$index}.barcode"] = [
                'nullable', 'string', 'max:64',
                Rule::unique('product_units', 'barcode')->ignore($this->input("units.{$index}.id")),
            ];
        }

        return $rules;
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateCategoryIsChosen($validator);
                $this->validateUnitsAreDistinct($validator);
                $this->validateSellingSetup($validator);
                $this->validateLockedUnits($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category.name' => 'kategori',
            'brand.name' => 'merek',
            'units.*.unit.code' => 'satuan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'units.*.unit.code.required' => 'Pilih satuan.',
            'units.*.unit.code.exists' => 'Satuan tidak ditemukan. Tambahkan dulu lewat "Tambah satuan".',
        ];
    }

    private function editedProduct(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function unitRows(): array
    {
        return array_values((array) $this->input('units', []));
    }

    private function validateCategoryIsChosen(Validator $validator): void
    {
        if (blank($this->input('category.id')) && blank($this->input('category.name'))) {
            $validator->errors()->add('category.name', 'Pilih atau ketik kategori.');
        }
    }

    /**
     * A product may list each unit (by code, any letter case) and each barcode once.
     */
    private function validateUnitsAreDistinct(Validator $validator): void
    {
        $seenUnits = [];
        $seenBarcodes = [];

        foreach ($this->unitRows() as $index => $row) {
            $unitKey = $this->unitKey($row);

            if (isset($seenUnits[$unitKey])) {
                $validator->errors()->add("units.{$index}.unit.code", 'Satuan ini sudah dipakai di baris lain.');
            }
            $seenUnits[$unitKey] = true;

            $barcode = trim((string) data_get($row, 'barcode'));

            if ($barcode === '') {
                continue;
            }

            if (isset($seenBarcodes[$barcode])) {
                $validator->errors()->add("units.{$index}.barcode", 'Barcode sama dengan baris lain.');
            }
            $seenBarcodes[$barcode] = true;
        }
    }

    /**
     * At least one unit is sold, and exactly one sellable unit is the default at the cashier.
     */
    private function validateSellingSetup(Validator $validator): void
    {
        $rows = $this->unitRows();

        if (! collect($rows)->contains(fn (array $row): bool => (bool) $row['is_sellable'])) {
            $validator->errors()->add('units', 'Minimal satu satuan harus bisa dijual.');
        }

        $defaults = collect($rows)->filter(fn (array $row): bool => (bool) $row['is_default_sale']);

        if ($defaults->count() !== 1) {
            $validator->errors()->add('units', 'Pilih tepat satu satuan default untuk kasir.');

            return;
        }

        if (! $defaults->first()['is_sellable']) {
            $validator->errors()->add('units', 'Satuan default kasir harus bisa dijual.');
        }
    }

    /**
     * After stock or transactions exist, existing units keep their identity and conversion and cannot be
     * removed; the base unit stays first. New units can still be added.
     */
    private function validateLockedUnits(Validator $validator): void
    {
        $product = $this->editedProduct();

        if ($product === null) {
            return;
        }

        $existing = $product->productUnits()->get()->keyBy('id');
        $rows = $this->unitRows();

        foreach ($rows as $index => $row) {
            if (filled($row['id'] ?? null) && ! $existing->has($row['id'])) {
                $validator->errors()->add("units.{$index}.id", 'Satuan tidak dikenal.');
            }
        }

        if (! $product->hasTransactions()) {
            return;
        }

        $submittedIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id);

        if ($existing->keys()->diff($submittedIds)->isNotEmpty()) {
            $validator->errors()->add('units', 'Satuan yang sudah dipakai tidak bisa dihapus.');
        }

        if ((int) ($rows[0]['id'] ?? 0) !== (int) $existing->firstWhere('is_base', true)?->id) {
            $validator->errors()->add('units.0.unit.code', 'Satuan dasar tidak bisa diganti setelah ada stok atau transaksi.');
        }

        foreach ($rows as $index => $row) {
            $current = $existing->get($row['id'] ?? null);

            if ($current === null) {
                continue;
            }

            $unitChanged = $this->unitKey($row) !== Unit::normalizeCode($current->unit_code);
            $conversionChanged = BigDecimal::of((string) $row['conversion_factor'])->compareTo($current->conversion_factor) !== 0;

            if ($unitChanged || $conversionChanged) {
                $validator->errors()->add("units.{$index}.conversion_factor", 'Satuan dan konversi terkunci setelah ada stok atau transaksi.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function unitKey(array $row): string
    {
        return Unit::normalizeCode((string) data_get($row, 'unit.code'));
    }
}
