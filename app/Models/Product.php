<?php

namespace App\Models;

use App\Enums\WarehouseType;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. Quantities and `cost_price` are per base unit. A damaged-goods (DG) product is made by an
 * opname from the product it was damaged from (`damaged_from_product_id`), with its own SKU and price.
 */
#[Fillable(['sku', 'barcode', 'name', 'category_id', 'brand_id', 'base_unit_code', 'cost_price', 'min_stock', 'is_active', 'is_damaged_goods', 'damaged_from_product_id'])]
class Product extends Model implements AuditableContract
{
    /** @use HasFactory<ProductFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * The save counter is bookkeeping for the edit form, not a change someone made.
     *
     * @var list<string>
     */
    protected array $auditExclude = ['version'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
            'is_damaged_goods' => 'boolean',
            'version' => 'integer',
        ];
    }

    /**
     * The code the item is scanned by: the barcode of its packaging, or the SKU when it has none.
     */
    public function scanCode(): string
    {
        return $this->barcode ?? $this->sku;
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function damagedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'damaged_from_product_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_code');
    }

    /**
     * @return HasMany<ProductUnit, $this>
     */
    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('sort_order');
    }

    /**
     * The unit that cannot be put back together once cut (a roll of wire), when the product has one. Reads the loaded
     * units, so load `productUnits` first.
     */
    public function sealedPackUnit(): ?ProductUnit
    {
        return $this->productUnits->firstWhere('is_sealed_pack', true);
    }

    /**
     * @return HasMany<Stock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * True when the product has stock or appears in any stock movement, receipt or opname. Such a product
     * keeps its units and conversions and can no longer be deleted, only deactivated.
     */
    private const HAS_TRANSACTIONS_SQL = '(exists (select 1 from stock_movements where stock_movements.product_id = products.id)
        or exists (select 1 from sale_items where sale_items.product_id = products.id)
        or exists (select 1 from stock_opname_items where stock_opname_items.product_id = products.id)
        or exists (select 1 from stocks where stocks.product_id = products.id and stocks.quantity != 0))';

    public function hasTransactions(): bool
    {
        return (bool) static::query()
            ->whereKey($this->getKey())
            ->selectRaw(self::HAS_TRANSACTIONS_SQL.' as has_transactions')
            ->value('has_transactions');
    }

    /**
     * Adds a `has_transactions` flag to every row, see {@see self::hasTransactions()}.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeWithTransactionFlag(Builder $query): void
    {
        $query->selectRaw(self::HAS_TRANSACTIONS_SQL.' as has_transactions');
    }

    /**
     * The product has a minimum and its stock in the sellable warehouses (the `?`) is under it. Products without a
     * minimum are never low. The one definition of "below minimum", used by the list flag and the dashboard count.
     */
    private const BELOW_MINIMUM_SQL = '(products.min_stock > 0 and (select coalesce(sum(stocks.quantity), 0) from stocks
        join warehouses on warehouses.id = stocks.warehouse_id
        where stocks.product_id = products.id and warehouses.type = ?) < products.min_stock)';

    /**
     * Adds `sellable_stock`, `dead_stock` and `returns_stock` (returned goods waiting to be inspected, not for sale): the product's
     * total in the warehouses of each type, `sellable_intact_packs` and
     * `dead_intact_packs`: how many whole sealed packs of it are in the warehouses of each type, and `is_below_minimum`, see {@see self::BELOW_MINIMUM_SQL}.
     *
     * With `$asOf` the totals are what they were at the end of that day: the sum of what the stock ledger recorded up to
     * then, not the `stocks` cache, which only knows the present. A product with nothing recorded by then has 0.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeWithStockTotals(Builder $query, ?CarbonImmutable $asOf = null): void
    {
        if ($asOf !== null) {
            $this->addLedgerStockTotals($query, $asOf);

            return;
        }

        $totalIn = fn (WarehouseType $type, string $column = 'quantity') => Stock::query()
            ->selectRaw("coalesce(sum(stocks.{$column}), 0)")
            ->whereColumn('stocks.product_id', 'products.id')
            ->whereIn('stocks.warehouse_id', Warehouse::query()->select('id')->where('type', $type));

        $query->addSelect([
            'products.*',
            'sellable_stock' => $totalIn(WarehouseType::Sellable),
            'dead_stock' => $totalIn(WarehouseType::DeadStock),
            'returns_stock' => $totalIn(WarehouseType::Returns),
            'sellable_intact_packs' => $totalIn(WarehouseType::Sellable, 'intact_packs'),
            'dead_intact_packs' => $totalIn(WarehouseType::DeadStock, 'intact_packs'),
        ])->selectRaw(self::BELOW_MINIMUM_SQL.' as is_below_minimum', [WarehouseType::Sellable->value]);
    }

    /**
     * The columns of {@see self::scopeWithStockTotals()} worked out from the ledger up to the end of `$asOf`. A product
     * with a minimum is below it when its sellable total then was, the same rule as {@see self::BELOW_MINIMUM_SQL}.
     *
     * @param  Builder<Product>  $query
     */
    private function addLedgerStockTotals(Builder $query, CarbonImmutable $asOf): void
    {
        $totalIn = fn (WarehouseType $type, string $column = 'quantity') => StockMovement::query()
            ->selectRaw("coalesce(sum(stock_movements.{$column}), 0)")
            ->whereColumn('stock_movements.product_id', 'products.id')
            ->where('stock_movements.moved_at', '<=', $asOf->endOfDay()->toDateTimeString())
            ->whereIn('stock_movements.warehouse_id', Warehouse::query()->select('id')->where('type', $type));

        $sellableStock = $totalIn(WarehouseType::Sellable);

        $query->addSelect([
            'products.*',
            'sellable_stock' => $sellableStock,
            'dead_stock' => $totalIn(WarehouseType::DeadStock),
            'returns_stock' => $totalIn(WarehouseType::Returns),
            'sellable_intact_packs' => $totalIn(WarehouseType::Sellable, 'pack_change'),
            'dead_intact_packs' => $totalIn(WarehouseType::DeadStock, 'pack_change'),
        ])->selectRaw('(products.min_stock > 0 and ('.$sellableStock->toSql().') < products.min_stock) as is_below_minimum', $sellableStock->getBindings());
    }

    /**
     * Matches the name, the SKU, the barcode or the brand name (a name does not always carry its brand).
     *
     * @param  Builder<Product>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        // A typed % or _ is part of the name, not a wildcard.
        $contains = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $query) use ($contains): void {
            $query->where('products.name', 'like', $contains)
                ->orWhere('products.sku', 'like', $contains)
                ->orWhere('products.barcode', 'like', $contains)
                ->orWhereIn('products.brand_id', Brand::query()->where('name', 'like', $contains)->select('id'));
        });
    }

    /**
     * Active products whose stock in the sellable warehouses is under their minimum.
     * Products without a minimum are never "low".
     *
     * @param  Builder<Product>  $query
     */
    public function scopeBelowMinimumStock(Builder $query): void
    {
        $query->where('is_active', true)->whereRaw(self::BELOW_MINIMUM_SQL, [WarehouseType::Sellable->value]);
    }
}
