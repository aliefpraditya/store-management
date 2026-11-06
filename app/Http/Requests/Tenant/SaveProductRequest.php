<?php

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use App\Models\Unit;
use App\Services\ProductService;
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
            'version' => $editedProduct === null ? ['nullable'] : ['required', 'integer'],
            'units' => ['required', 'array', 'min:1'],
            'units.*.id' => ['nullable', 'integer'],
            'units.*.unit' => ['required', 'array'],
            'units.*.unit.code' => ['required', 'string', Rule::exists('units', 'code')],
            'units.*.conversion_factor' => ['required', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,3'],
            'units.*.cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'units.*.is_sellable' => ['required', 'boolean'],
            'units.*.selling_price' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'units.*.is_default_sale' => ['required', 'boolean'],
            'units.*.is_sealed_pack' => ['sometimes', 'boolean'],
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
                $this->validateSealedPack($validator);
                $this->validateUnitsCanBeSaved($validator);
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

        $defaultSaleRows = collect($rows)->filter(fn (array $row): bool => (bool) $row['is_default_sale']);

        if ($defaultSaleRows->count() !== 1) {
            $validator->errors()->add('units', 'Pilih tepat satu satuan default untuk kasir.');

            return;
        }

        if (! $defaultSaleRows->first()['is_sellable']) {
            $validator->errors()->add('units', 'Satuan default kasir harus bisa dijual.');
        }
    }

    /**
     * At most one unit is the sealed pack (the roll), it is a unit the item is sold in, and it is not the base unit, which
     * is the piece a pack is cut into.
     */
    private function validateSealedPack(Validator $validator): void
    {
        $sealedIndexes = collect($this->unitRows())->keys()->filter(fn (int $index): bool => (bool) ($this->unitRows()[$index]['is_sealed_pack'] ?? false))->values();

        if ($sealedIndexes->isEmpty()) {
            return;
        }

        if ($sealedIndexes->count() > 1) {
            $validator->errors()->add('units', 'Hanya satu satuan yang boleh ditandai kemasan utuh.');

            return;
        }

        $index = $sealedIndexes->first();

        if ($index === 0) {
            $validator->errors()->add('units.0.is_sealed_pack', 'Satuan dasar tidak bisa jadi kemasan utuh: itu satuan potongannya.');
        } elseif (! $this->unitRows()[$index]['is_sellable']) {
            $validator->errors()->add("units.{$index}.is_sealed_pack", 'Kemasan utuh harus satuan yang bisa dijual.');
        }
    }

    /**
     * Shows the unit problems the service would refuse the save for (units locked by stock or history, two rows
     * swapping units) as errors on the form, before anything is written. The service checks them again under the lock.
     */
    private function validateUnitsCanBeSaved(Validator $validator): void
    {
        $product = $this->editedProduct();

        if ($product === null) {
            return;
        }

        foreach (app(ProductService::class)->unitProblems($product, $this->unitRows()) as $problem) {
            $validator->errors()->add($problem['field'], $problem['message']);
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
