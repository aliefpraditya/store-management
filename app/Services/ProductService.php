<?php

namespace App\Services;

use App\Enums\ProductConditionFilter;
use App\Enums\ProductSortColumn;
use App\Enums\ProductStatusFilter;
use App\Exceptions\ProductHasTransactionsException;
use App\Exceptions\ProductSaveException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Saves a product with its units. The first unit is the base unit (conversion 1, `base_unit_code`); its purchase
 * price is also kept on the product as `cost_price`, the price per base unit.
 * Category and brand are either picked by `id` or typed by `name` and created on the way; units must already exist.
 *
 * Saving is guarded in two ways, both under the product's row lock so a sale or another save cannot slip in between
 * the check and the write: the form carries the `version` it was opened with (a save from an older version is
 * refused), and once a product has stock or history its units and conversions are locked.
 *
 * @phpstan-type NamedOrExisting array{id?: int|string|null, name?: string|null}
 * @phpstan-type UnitRow array{id?: int|null, unit: array{code: string, name?: string|null}, conversion_factor: numeric-string|float|int, is_sellable: bool, cost_price: numeric-string|float|int, selling_price: numeric-string|float|int, is_default_sale: bool, is_sealed_pack?: bool}
 * @phpstan-type ProductInput array{name: string, sku: string, barcode?: string|null, category: NamedOrExisting, brand?: NamedOrExisting|null, min_stock: numeric-string|float|int, is_active: bool, version?: int|null, units: list<UnitRow>}
 * @phpstan-type UnitProblem array{field: string, message: string}
 */
class ProductService
{
    private const PICKER_LIMIT = 15;

    public function __construct(private readonly StockService $stockLedger) {}

    /**
     * The item list: stock totals and flags per row, narrowed by a search, a category, a brand, an active/inactive choice
     * and whether the item is damaged goods (DG), in the order of the column asked for.
     *
     * @param  'asc'|'desc'  $direction
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateProducts(string $search, ?int $categoryId, ?int $brandId, ?ProductStatusFilter $status, ?ProductConditionFilter $condition, ProductSortColumn $sort, string $direction, int $perPage): LengthAwarePaginator
    {
        $products = $this->filteredProducts($search, $categoryId, $brandId, $status, $condition)
            ->withStockTotals()
            ->withTransactionFlag()
            ->with(['category', 'brand', 'productUnits']);

        $sort->applyTo($products, $direction);

        return $products->paginate($perPage)->withQueryString();
    }

    /**
     * Every item the same narrowing matches, up to what one bulk price change takes, so the list can offer "select all".
     *
     * @return list<int>
     */
    public function matchingIds(string $search, ?int $categoryId, ?int $brandId, ?ProductStatusFilter $status, ?ProductConditionFilter $condition): array
    {
        return $this->filteredProducts($search, $categoryId, $brandId, $status, $condition)
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->limit(BulkPriceService::MAX_PRODUCTS)
            ->pluck('products.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return Builder<Product>
     */
    private function filteredProducts(string $search, ?int $categoryId, ?int $brandId, ?ProductStatusFilter $status, ?ProductConditionFilter $condition): Builder
    {
        return Product::query()
            ->search($search)
            ->when($categoryId, fn (Builder $query, int $categoryId) => $query->where('products.category_id', $categoryId))
            ->when($brandId, fn (Builder $query, int $brandId) => $query->where('products.brand_id', $brandId))
            ->when($status, fn (Builder $query, ProductStatusFilter $status) => $query->where('products.is_active', $status->isActive()))
            ->when($condition, fn (Builder $query, ProductConditionFilter $condition) => $query->where('products.is_damaged_goods', $condition->isDamagedGoods()));
    }

    /**
     * The few best matches for a product picker (opname, stock history).
     *
     * @return Collection<int, Product>
     */
    public function searchForPicker(string $term): Collection
    {
        return Product::query()
            ->with(['baseUnit', 'productUnits', 'brand'])
            ->search($term)
            ->orderBy('name')
            ->limit(self::PICKER_LIMIT)
            ->get();
    }

    /**
     * The SKU the add form starts with: the store's prefix followed by the next number after the highest one in use
     * (MU3470 -> MU3471, padded to four digits). Deleted items count too, so a number is never handed out twice. It is
     * only a suggestion: the person can type another, and the unique rule still decides when it is saved.
     *
     * @param  string  $prefix  letters and digits only, as the store's receipt prefix is
     */
    public function suggestSku(string $prefix): string
    {
        $numberStart = strlen($prefix) + 1;

        $highestNumber = Product::withTrashed()
            ->whereRaw('sku REGEXP ?', ['^'.$prefix.'[0-9]{1,9}$'])
            ->max(DB::raw("CAST(SUBSTRING(sku, {$numberStart}) AS UNSIGNED)"));

        return $prefix.sprintf('%04d', (int) $highestNumber + 1);
    }

    /**
     * @param  ProductInput  $productInput
     */
    public function create(array $productInput): Product
    {
        return DB::transaction(fn (): Product => $this->save(new Product, $productInput));
    }

    /**
     * @param  ProductInput  $productInput
     *
     * @throws ProductSaveException
     */
    public function update(Product $product, array $productInput): Product
    {
        return DB::transaction(function () use ($product, $productInput): Product {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($lockedProduct->version !== (int) ($productInput['version'] ?? 0)) {
                throw ProductSaveException::changedByAnother();
            }

            $problems = $this->unitProblems($lockedProduct, $productInput['units']);

            if ($problems !== []) {
                throw ProductSaveException::unitsLocked($problems[0]['message']);
            }

            return $this->save($lockedProduct, $productInput);
        });
    }

    /**
     * Removes a product that never had stock or history. The deletion is permanent so its SKU
     * can be reused; the audit trail keeps what it looked like. Its empty stock rows go with it.
     * Locking the product row first holds off a sale that is just being rung up for it.
     *
     * @throws ProductHasTransactionsException
     */
    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($lockedProduct->hasTransactions()) {
                throw ProductHasTransactionsException::forProduct($lockedProduct->name);
            }

            $lockedProduct->stocks()->delete();
            $lockedProduct->productUnits()->get()->each->delete();
            $lockedProduct->forceDelete();
        });
    }

    /**
     * What is wrong with saving these unit rows onto this product, as the form field and the message to show. Read
     * by the form request for its field errors, and again by {@see self::update()} under the product's lock.
     *
     * @param  list<array<string, mixed>>  $unitRows
     * @return list<UnitProblem>
     */
    public function unitProblems(Product $product, array $unitRows): array
    {
        $unitRows = array_values($unitRows);
        $existingUnitsById = $product->productUnits()->get()->keyBy('id');
        $problems = [];

        foreach ($unitRows as $index => $row) {
            if (filled($row['id'] ?? null) && ! $existingUnitsById->has($row['id'])) {
                $problems[] = ['field' => "units.{$index}.id", 'message' => 'Satuan tidak dikenal.'];
            }
        }

        $problems = [...$problems, ...$this->unitSwapProblems($existingUnitsById, $unitRows)];

        if (! $product->hasTransactions()) {
            return $problems;
        }

        return [...$problems, ...$this->lockedUnitProblems($existingUnitsById, $unitRows)];
    }

    /**
     * A product has one row per unit code. Moving a unit code to a row while the row that holds it is also being
     * changed (two rows swapping units) cannot be saved row by row without a clash, so it is asked to be done in steps.
     *
     * @param  Collection<int, ProductUnit>  $existingUnitsById
     * @param  list<array<string, mixed>>  $unitRows
     * @return list<UnitProblem>
     */
    private function unitSwapProblems(Collection $existingUnitsById, array $unitRows): array
    {
        $keptIds = collect($unitRows)->pluck('id')->filter()->map(fn ($id): int => (int) $id);
        $problems = [];

        foreach ($unitRows as $index => $row) {
            $currentUnit = $existingUnitsById->get($row['id'] ?? null);

            if ($currentUnit === null || $this->unitKey($row) === Unit::normalizeCode($currentUnit->unit_code)) {
                continue;
            }

            $holder = $existingUnitsById->first(fn (ProductUnit $other): bool => $other->id !== $currentUnit->id
                && $keptIds->contains($other->id)
                && Unit::normalizeCode($other->unit_code) === $this->unitKey($row));

            if ($holder !== null) {
                $problems[] = ['field' => "units.{$index}.unit.code", 'message' => 'Satuan ini masih dipakai baris lain yang ikut diganti. Ubah satu baris dulu, simpan, lalu ubah yang lain.'];
            }
        }

        return $problems;
    }

    /**
     * After stock or transactions exist, existing units keep their identity and conversion and cannot be
     * removed; the base unit stays first. New units can still be added.
     *
     * @param  Collection<int, ProductUnit>  $existingUnitsById
     * @param  list<array<string, mixed>>  $unitRows
     * @return list<UnitProblem>
     */
    private function lockedUnitProblems(Collection $existingUnitsById, array $unitRows): array
    {
        $problems = [];
        $submittedIds = collect($unitRows)->pluck('id')->filter()->map(fn ($id): int => (int) $id);

        if ($existingUnitsById->keys()->diff($submittedIds)->isNotEmpty()) {
            $problems[] = ['field' => 'units', 'message' => 'Satuan yang sudah dipakai tidak bisa dihapus.'];
        }

        if ((int) ($unitRows[0]['id'] ?? 0) !== (int) $existingUnitsById->firstWhere('is_base', true)?->id) {
            $problems[] = ['field' => 'units.0.unit.code', 'message' => 'Satuan dasar tidak bisa diganti setelah ada stok atau transaksi.'];
        }

        foreach ($unitRows as $index => $row) {
            $currentUnit = $existingUnitsById->get($row['id'] ?? null);

            if ($currentUnit === null) {
                continue;
            }

            $isUnitChanged = $this->unitKey($row) !== Unit::normalizeCode($currentUnit->unit_code);
            $isConversionChanged = BigDecimal::of((string) $row['conversion_factor'])->compareTo($currentUnit->conversion_factor) !== 0;

            if ($isUnitChanged || $isConversionChanged) {
                $problems[] = ['field' => "units.{$index}.conversion_factor", 'message' => 'Satuan dan konversi terkunci setelah ada stok atau transaksi.'];
            }
        }

        return $problems;
    }

    /**
     * @param  ProductInput  $productInput
     */
    private function save(Product $product, array $productInput): Product
    {
        $unitRows = array_map(fn (array $row): array => [...$row, 'resolved_unit' => $this->resolveUnit($row['unit'])], array_values($productInput['units']));

        $product->fill([
            'sku' => trim($productInput['sku']),
            'barcode' => filled($productInput['barcode'] ?? null) ? trim($productInput['barcode']) : null,
            'name' => trim($productInput['name']),
            'category_id' => $this->resolveCategory($productInput['category'])->id,
            'brand_id' => $this->resolveBrand($productInput['brand'] ?? null)?->id,
            'base_unit_code' => $unitRows[0]['resolved_unit']->code,
            'cost_price' => $unitRows[0]['cost_price'],
            'min_stock' => $productInput['min_stock'],
            'is_active' => $productInput['is_active'],
        ])->forceFill(['version' => $product->exists ? $product->version + 1 : 1])->save();

        $this->syncUnits($product, $unitRows);

        return $product;
    }

    /**
     * Units left out of the list are removed; the order of the list becomes the display order. Removed one by one, so
     * the audit trail keeps what each unit's last prices were.
     *
     * @param  list<array<string, mixed>>  $unitRows
     */
    private function syncUnits(Product $product, array $unitRows): void
    {
        $previousPackUnit = $product->exists ? $product->productUnits()->where('is_sealed_pack', true)->first() : null;
        $keptIds = collect($unitRows)->pluck('id')->filter()->all();

        $product->productUnits()->whereNotIn('id', $keptIds)->get()->each->delete();

        foreach ($unitRows as $index => $row) {
            $productUnit = filled($row['id'] ?? null)
                ? $product->productUnits()->findOrFail($row['id'])
                : new ProductUnit(['product_id' => $product->id]);

            $productUnit->fill([
                'unit_code' => $row['resolved_unit']->code,
                'conversion_factor' => $index === 0 ? 1 : $row['conversion_factor'],
                'is_base' => $index === 0,
                'sort_order' => $index,
                'is_sellable' => $row['is_sellable'],
                'cost_price' => $row['cost_price'],
                'selling_price' => $row['selling_price'],
                'is_default_sale' => $row['is_default_sale'],
                'is_sealed_pack' => $index !== 0 && ($row['is_sealed_pack'] ?? false),
            ])->save();
        }

        $this->startOrStopCountingPacks($product, $previousPackUnit);
    }

    /**
     * The stock rows of a product start counting whole packs when it gets a sealed pack unit (or a different one), and stop
     * when it loses it, see {@see StockService::resetIntactPacks()}. Saving the same unit again changes nothing.
     */
    private function startOrStopCountingPacks(Product $product, ?ProductUnit $previousPackUnit): void
    {
        $product->unsetRelation('productUnits');
        $packUnit = $product->sealedPackUnit();

        if ($packUnit?->id === $previousPackUnit?->id) {
            return;
        }

        $this->stockLedger->resetIntactPacks($product);
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

    /**
     * @param  array<string, mixed>  $row
     */
    private function unitKey(array $row): string
    {
        return Unit::normalizeCode((string) data_get($row, 'unit.code'));
    }
}
