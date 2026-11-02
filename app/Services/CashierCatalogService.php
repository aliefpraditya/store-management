<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Stock;
use Illuminate\Support\Collection;

/**
 * The item lookup of the cashier screen: active products with the units they can be sold in, their price, and
 * how much is in the register's warehouse. Items in stock are listed before sold-out ones, so a broad term such as
 * "cat" does not open with empty shelves; a specific term whose matches are all sold out still shows them. A term
 * that is exactly a unit's barcode finds that product alone and tells which unit was scanned.
 *
 * @phpstan-type CatalogUnit array{id: int, code: string, conversion_factor: string, price: string, barcode: string|null}
 * @phpstan-type CatalogItem array{id: int, sku: string, name: string, base_unit: string, stock: string, units: list<CatalogUnit>, matched_unit_id: int|null}
 */
class CashierCatalogService
{
    private const RESULT_LIMIT = 20;

    /** Whether the product has stock in the warehouse (the `?`); items that are sold out come after the ones in stock. */
    private const IN_STOCK_FIRST_SQL = 'coalesce((select stocks.quantity from stocks where stocks.product_id = products.id and stocks.warehouse_id = ?), 0) > 0';

    /**
     * @return list<CatalogItem>
     */
    public function search(int $warehouseId, string $term): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $scannedUnit = ProductUnit::query()->where('barcode', $term)->where('is_sellable', true)->first();

        $products = Product::query()
            ->where('is_active', true)
            ->with(['productUnits' => fn ($units) => $units->where('is_sellable', true)])
            ->when(
                $scannedUnit,
                fn ($query, ProductUnit $unit) => $query->whereKey($unit->product_id),
                fn ($query) => $query->search($term),
            )
            ->orderByRaw(self::IN_STOCK_FIRST_SQL.' desc', [$warehouseId])
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->filter(fn (Product $product): bool => $product->productUnits->isNotEmpty());

        return $this->catalogItems($products, $warehouseId, $scannedUnit);
    }

    /**
     * The same items as {@see self::search()} gives, found by the ids of units already chosen. The cart is kept in the
     * browser, so after a refresh its prices and stock can be a while old; this brings them up to date. Items that
     * are no longer active, or whose unit is no longer sold, are simply absent.
     *
     * @param  list<int>  $productUnitIds
     * @return list<CatalogItem>
     */
    public function forUnits(int $warehouseId, array $productUnitIds): array
    {
        $productIds = ProductUnit::query()->whereIn('id', $productUnitIds)->where('is_sellable', true)->pluck('product_id')->unique();

        $products = Product::query()
            ->where('is_active', true)
            ->whereKey($productIds)
            ->with(['productUnits' => fn ($units) => $units->where('is_sellable', true)])
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product): bool => $product->productUnits->isNotEmpty());

        return $this->catalogItems($products, $warehouseId, null);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<CatalogItem>
     */
    private function catalogItems(Collection $products, int $warehouseId, ?ProductUnit $scannedUnit): array
    {
        $stocks = $this->stockOf($products, $warehouseId);

        return $products->map(fn (Product $product): array => [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'base_unit' => $product->base_unit_code,
            'stock' => (string) ($stocks[$product->id] ?? '0.000'),
            'units' => $product->productUnits->map(fn (ProductUnit $unit): array => [
                'id' => $unit->id,
                'code' => $unit->unit_code,
                'conversion_factor' => $unit->conversion_factor,
                'price' => $unit->selling_price,
                'barcode' => $unit->barcode,
            ])->values()->all(),
            'matched_unit_id' => $scannedUnit?->product_id === $product->id ? $scannedUnit->id : null,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, string> quantity in the base unit per product id
     */
    private function stockOf(Collection $products, int $warehouseId): Collection
    {
        return Stock::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $products->modelKeys())
            ->pluck('quantity', 'product_id');
    }
}
