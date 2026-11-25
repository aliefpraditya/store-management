<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\CustomerOrderItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SaleItem;
use App\Models\Stock;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

/**
 * The item lookup of the cashier screen: active products with the units they can be sold in, their price, and
 * how much is in the register's warehouse. Items in stock are listed before sold-out ones, so a broad term such as
 * "cat" does not open with empty shelves; a specific term whose matches are all sold out still shows them. A scanned
 * barcode is the barcode of the item's packaging, or its SKU when it has none, so it is found like any typed term.
 *
 * A product with a sealed pack unit (a roll of wire) also tells how many whole packs are left (`intact_packs`, null for other
 * products), because the stock in the base unit alone does not say how many packs can still be sold whole.
 *
 * @phpstan-type CatalogUnit array{id: int, code: string, conversion_factor: string, price: string, is_sealed_pack: bool, is_default_sale: bool}
 * @phpstan-type CatalogItem array{id: int, sku: string, barcode: string|null, name: string, base_unit: string, stock: string, reserved: string, intact_packs: int|null, units: list<CatalogUnit>, is_being_counted: bool}
 */
class CashierCatalogService
{
    public function __construct(
        private readonly StoreSettingService $settings,
        private readonly StockOpnameService $stockOpnames,
    ) {}

    private const RESULT_LIMIT = 20;

    private const BEST_SELLER_LIMIT = 20;

    /** How many ranked candidates are looked at, so that some can still drop out (reserved for orders, being counted) and the list stays full. */
    private const BEST_SELLER_CANDIDATES = 60;

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

        $products = Product::query()
            ->where('is_active', true)
            ->with(['productUnits' => fn ($units) => $units->where('is_sellable', true)->confirmed()])
            ->search($term)
            ->orderByRaw(self::IN_STOCK_FIRST_SQL.' desc', [$warehouseId])
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->filter(fn (Product $product): bool => $product->productUnits->isNotEmpty());

        return $this->catalogItems($products, $warehouseId);
    }

    /**
     * The items this warehouse sold most often (by number of receipt lines) in the last days, best seller first, for the
     * empty till: a cashier adds them without typing. Only items that can be sold right now are offered: sold-out ones,
     * ones whose stock is all kept for orders, and ones being counted are left out, as are items that cannot be sold any more.
     *
     * @return list<CatalogItem>
     */
    public function bestSellers(int $warehouseId): array
    {
        $rankedProductIds = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('stocks', fn ($join) => $join->on('stocks.product_id', '=', 'sale_items.product_id')->where('stocks.warehouse_id', '=', $warehouseId))
            ->where('sales.status', SaleStatus::Completed)
            ->where('sales.warehouse_id', $warehouseId)
            ->where('sales.sold_at', '>=', now()->subDays(self::BEST_SELLER_DAYS))
            ->where('stocks.quantity', '>', 0)
            ->groupBy('sale_items.product_id')
            ->orderByRaw('count(*) desc')
            ->limit(self::BEST_SELLER_CANDIDATES)
            ->pluck('sale_items.product_id')
            ->all();

        $products = Product::query()
            ->where('is_active', true)
            ->whereKey($rankedProductIds)
            ->with(['productUnits' => fn ($units) => $units->where('is_sellable', true)->confirmed()])
            ->get()
            ->filter(fn (Product $product): bool => $product->productUnits->isNotEmpty())
            ->sortBy(fn (Product $product): int|false => array_search($product->id, $rankedProductIds, true));

        return array_slice(
            array_values(array_filter(
                $this->catalogItems($products, $warehouseId),
                fn (array $item): bool => (float) $item['stock'] > 0 && ! $item['is_being_counted'],
            )),
            0,
            self::BEST_SELLER_LIMIT,
        );
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
        $productIds = ProductUnit::query()->whereIn('id', $productUnitIds)->where('is_sellable', true)->confirmed()->pluck('product_id')->unique();

        $products = Product::query()
            ->where('is_active', true)
            ->whereKey($productIds)
            ->with(['productUnits' => fn ($units) => $units->where('is_sellable', true)->confirmed()])
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product): bool => $product->productUnits->isNotEmpty());

        return $this->catalogItems($products, $warehouseId);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<CatalogItem>
     */
    private function catalogItems(Collection $products, int $warehouseId): array
    {
        $stocks = $this->stockOf($products, $warehouseId);
        $reserved = CustomerOrderItem::reservedQuantities($warehouseId, $products->modelKeys());
        $reservedPacks = CustomerOrderItem::reservedPacks($warehouseId, $products->modelKeys());
        $countedProductIds = $this->settings->isSalesLockedDuringOpname() ? $this->stockOpnames->productIdsBeingCounted($warehouseId, $products->modelKeys()) : [];

        return $products->map(fn (Product $product): array => [
            'id' => $product->id,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'base_unit' => $product->base_unit_code,
            'stock' => (string) $this->sellableQuantity($stocks[$product->id] ?? null, $reserved[$product->id] ?? '0')->toScale(3),
            'reserved' => (string) BigDecimal::of($reserved[$product->id] ?? '0')->toScale(3),
            'intact_packs' => $this->sellableIntactPacks($product, $stocks[$product->id] ?? null, $reserved[$product->id] ?? '0', $reservedPacks[$product->id] ?? 0),
            'units' => $product->productUnits->map(fn (ProductUnit $unit): array => [
                'id' => $unit->id,
                'code' => $unit->unit_code,
                'conversion_factor' => $unit->conversion_factor,
                'price' => $unit->selling_price,
                'is_sealed_pack' => $unit->is_sealed_pack,
                'is_default_sale' => (bool) $unit->is_default_sale,
            ])->values()->all(),
            'is_being_counted' => in_array($product->id, $countedProductIds, true),
        ])->values()->all();
    }

    private function sellableQuantity(?Stock $stock, string $reservedQuantity): BigDecimal
    {
        return BigDecimal::max(BigDecimal::of((string) ($stock->quantity ?? '0'))->minus($reservedQuantity), '0');
    }

    /**
     * The whole packs a cashier can still sell as packs: not the ones ready orders hold, and no more than the sellable
     * quantity holds, so that `stock - packs x conversion` (the loose pieces the screen shows) is never negative.
     *
     * @return int|null null for a product without a sealed pack unit
     */
    private function sellableIntactPacks(Product $product, ?Stock $stock, string $reservedQuantity, int $reservedPacks): ?int
    {
        $packUnit = $product->sealedPackUnit();

        if ($packUnit === null) {
            return null;
        }

        $fittingPacks = $this->sellableQuantity($stock, $reservedQuantity)->dividedBy($packUnit->conversion_factor, 0, RoundingMode::Down)->toInt();

        return max(0, min((int) ($stock->intact_packs ?? 0) - $reservedPacks, $fittingPacks));
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
