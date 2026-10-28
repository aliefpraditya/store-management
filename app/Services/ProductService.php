<?php

namespace App\Services;

use App\Exceptions\ProductHasTransactionsException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Saves a product with its units. The first unit is the base unit (conversion 1, `base_unit_code`); its purchase
 * price is also kept on the product as `cost_price`, the price per base unit.
 * Category and brand are either picked by `id` or typed by `name` and created on the way; units must already exist.
 *
 * @phpstan-type NamedOrExisting array{id?: int|string|null, name?: string|null}
 * @phpstan-type UnitRow array{id?: int|null, unit: array{code: string, name?: string|null}, conversion_factor: numeric-string|float|int, barcode?: string|null, is_sellable: bool, cost_price: numeric-string|float|int, selling_price: numeric-string|float|int, is_default_sale: bool}
 * @phpstan-type ProductData array{name: string, sku: string, category: NamedOrExisting, brand?: NamedOrExisting|null, min_stock: numeric-string|float|int, is_active: bool, units: list<UnitRow>}
 */
class ProductService
{
    /**
     * @param  ProductData  $data
     */
    public function create(array $data): Product
    {
        return DB::transaction(fn (): Product => $this->save(new Product, $data));
    }

    /**
     * @param  ProductData  $data
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(fn (): Product => $this->save($product, $data));
    }

    /**
     * Removes a product that never had stock or history. The deletion is permanent so its SKU and
     * barcodes can be reused; the audit trail keeps what it looked like. Its empty stock rows go with it.
     *
     * @throws ProductHasTransactionsException
     */
    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            if ($product->hasTransactions()) {
                throw ProductHasTransactionsException::forProduct($product->name);
            }

            $product->stocks()->delete();
            $product->forceDelete();
        });
    }

    /**
     * @param  ProductData  $data
     */
    private function save(Product $product, array $data): Product
    {
        $units = array_map(fn (array $row): array => [...$row, 'resolved_unit' => $this->resolveUnit($row['unit'])], array_values($data['units']));

        $product->fill([
            'sku' => trim($data['sku']),
            'name' => trim($data['name']),
            'category_id' => $this->resolveCategory($data['category'])->id,
            'brand_id' => $this->resolveBrand($data['brand'] ?? null)?->id,
            'base_unit_code' => $units[0]['resolved_unit']->code,
            'cost_price' => $units[0]['cost_price'],
            'min_stock' => $data['min_stock'],
            'is_active' => $data['is_active'],
        ])->save();

        $this->syncUnits($product, $units);

        return $product;
    }

    /**
     * Units left out of the list are removed; the order of the list becomes the display order.
     *
     * @param  list<array<string, mixed>>  $units
     */
    private function syncUnits(Product $product, array $units): void
    {
        $keptIds = collect($units)->pluck('id')->filter()->all();

        $product->productUnits()->whereNotIn('id', $keptIds)->delete();

        foreach ($units as $index => $row) {
            $productUnit = filled($row['id'] ?? null)
                ? $product->productUnits()->findOrFail($row['id'])
                : new ProductUnit(['product_id' => $product->id]);

            $productUnit->fill([
                'unit_code' => $row['resolved_unit']->code,
                'conversion_factor' => $index === 0 ? 1 : $row['conversion_factor'],
                'is_base' => $index === 0,
                'barcode' => filled($row['barcode'] ?? null) ? trim($row['barcode']) : null,
                'sort_order' => $index,
                'is_sellable' => $row['is_sellable'],
                'cost_price' => $row['cost_price'],
                'selling_price' => $row['selling_price'],
                'is_default_sale' => $row['is_default_sale'],
            ])->save();
        }
    }

    /**
     * @param  NamedOrExisting  $category
     */
    private function resolveCategory(array $category): Category
    {
        return filled($category['id'] ?? null)
            ? Category::query()->findOrFail($category['id'])
            : Category::query()->firstOrCreate(['name' => trim($category['name'])]);
    }

    /**
     * @param  NamedOrExisting|null  $brand
     */
    private function resolveBrand(?array $brand): ?Brand
    {
        if (blank($brand['id'] ?? null) && blank($brand['name'] ?? null)) {
            return null;
        }

        return filled($brand['id'] ?? null)
            ? Brand::query()->findOrFail($brand['id'])
            : Brand::query()->firstOrCreate(['name' => trim($brand['name'])]);
    }

    /**
     * Units are added from the unit settings, so a unit is only looked up here, by its code in any letter case.
     *
     * @param  array{code: string, name?: string|null}  $unit
     */
    private function resolveUnit(array $unit): Unit
    {
        return Unit::query()->findOrFail(Unit::normalizeCode($unit['code']));
    }
}
