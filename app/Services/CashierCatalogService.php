<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\CustomerOrderItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\StockOpnameItem;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

/**
 * The item lookup of the cashier screen: active products with the units they can be sold in, their price, and
 * how much is in the register's warehouse. Items in stock are listed before sold-out ones, so a broad term such as
 * "cat" does not open with empty shelves; a specific term whose matches are all sold out still shows them. A term
 * that is exactly a unit's barcode finds that product alone and tells which unit was scanned.
 *
 * A product with a sealed pack unit (a roll of wire) also tells how many whole packs are left (`intact_packs`, null for other
 * products), because the stock in the base unit alone does not say how many packs can still be sold whole.
 *
 * @phpstan-type CatalogUnit array{id: int, code: string, conversion_factor: string, price: string, barcode: string|null, is_sealed_pack: bool, is_default_sale: bool}
 * @phpstan-type CatalogItem array{id: int, sku: string, name: string, base_unit: string, stock: string, reserved: string, intact_packs: int|null, units: list<CatalogUnit>, matched_unit_id: int|null, is_being_counted: bool}
 */
class CashierCatalogService
{
    public function __construct(private readonly StoreSettingService $settings) {}

    private const RESULT_LIMIT = 20;

    private const BEST_SELLER_LIMIT = 12;

    private const BEST_SELLER_DAYS = 30;

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
     * The items this warehouse sold most often (by number of receipt lines) in the last days, best seller first, for the
     * empty till: a cashier adds them without typing. Items that cannot be sold any more are left out.
     *
     * @return list<CatalogItem>
     */
    public function bestSellers(int $warehouseId): array
    {
        $rankedProductIds = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', SaleStatus::Completed)
            ->where('sales.warehouse_id', $warehouseId)
            ->where('sales.sold_at', '>=', now()->subDays(self::BEST_SELLER_DAYS))
            ->groupBy('sale_items.product_id')
            ->orderByRaw('count(*) desc')
            ->limit(self::BEST_SELLER_LIMIT)
            ->pluck('sale_items.product_id')
            ->all();

        $products = Product::query()
            ->where('is_active', true)
            ->whereKey($rankedProductIds)
            ->with(['productUnits' => fn ($units) => $units->where('is_sellable', true)])
            ->get()
            ->filter(fn (Product $product): bool => $product->productUnits->isNotEmpty())
            ->sortBy(fn (Product $product): int|false => array_search($product->id, $rankedProductIds, true));

        return $this->catalogItems($products, $warehouseId, null);
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
        $reserved = CustomerOrderItem::reservedQuantities($warehouseId, $products->modelKeys());
        $countedProductIds = $this->settings->isSalesLockedDuringOpname() ? StockOpnameItem::productIdsBeingCounted($warehouseId, $products->modelKeys()) : [];

        return $products->map(fn (Product $product): array => [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'base_unit' => $product->base_unit_code,
            'stock' => (string) BigDecimal::max(BigDecimal::of((string) ($stocks[$product->id]->quantity ?? '0'))->minus($reserved[$product->id] ?? '0'), '0')->toScale(3),
            'reserved' => (string) BigDecimal::of($reserved[$product->id] ?? '0')->toScale(3),
            'intact_packs' => $product->sealedPackUnit() === null ? null : (int) ($stocks[$product->id]->intact_packs ?? 0),
            'units' => $product->productUnits->map(fn (ProductUnit $unit): array => [
                'id' => $unit->id,
                'code' => $unit->unit_code,
                'conversion_factor' => $unit->conversion_factor,
                'price' => $unit->selling_price,
                'barcode' => $unit->barcode,
                'is_sealed_pack' => $unit->is_sealed_pack,
                'is_default_sale' => (bool) $unit->is_default_sale,
            ])->values()->all(),
            'matched_unit_id' => $scannedUnit?->product_id === $product->id ? $scannedUnit->id : null,
            'is_being_counted' => in_array($product->id, $countedProductIds, true),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Stock> the stock row (base unit quantity and whole sealed packs) per product id
     */
    private function stockOf(Collection $products, int $warehouseId): Collection
    {
        return Stock::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $products->modelKeys())
            ->get()
            ->keyBy('product_id');
    }
}
