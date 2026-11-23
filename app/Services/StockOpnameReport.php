<?php

namespace App\Services;

use App\Enums\OpnameReasonBehavior;
use App\Models\Brand;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads an opname for the review screen: its items with the live system quantity and variance, and the totals.
 * The variance is worked out in SQL here ({@see self::varianceSql()}) with the same rule as
 * {@see StockOpname::varianceOf()}, which posting uses; change both together.
 *
 * @phpstan-type DamagedGoodsRow array{product_name: string, base_unit: string, dead_stock_quantity: string, damaged_goods_quantity: string, full_price_quantity: string, cost_price: string, original_price: string, damaged_goods_price: string|null, existing_price: string|null, loss: string, loss_profit: string}
 * @phpstan-type OpnameSummary array{items_count: int, counted_count: int, variance_count: int, reviewed_count: int, review_count: int, variance_value: string}
 */
class StockOpnameReport
{
    public const ITEM_FILTER_UNCOUNTED = 'uncounted';

    public const ITEM_FILTER_VARIANCE = 'variance';

    public const ITEM_FILTER_UNREVIEWED = 'unreviewed';

    /** The cost price an item's variance is valued at: today's until the opname is posted, then the one it was posted with. */
    private const COST_PRICE_SQL = 'coalesce(stock_opname_items.cost_price_at_posting, products.cost_price)';

    /** Live stock until the opname is posted, then the quantity it was posted against. */
    private const SYSTEM_QUANTITY_SQL = 'coalesce(stock_opname_items.system_quantity_at_posting, stocks.quantity, 0)';

    /** The whole sealed packs counted in the system quantity, read the same way. */
    private const SYSTEM_PACKS_SQL = 'coalesce(stock_opname_items.system_packs_at_posting, stocks.intact_packs, 0)';

    public function __construct(private readonly DamagedGoodsService $damagedGoods) {}

    /**
     * The items of an opname with `system_quantity` and `system_intact_packs` (live stock until posted, then the stored value),
     * `product_cost_price`, and the product's name and SKU for searching.
     *
     * @return Builder<StockOpnameItem>
     */
    public function itemsQuery(StockOpname $opname): Builder
    {
        return StockOpnameItem::query()
            ->where('stock_opname_items.stock_opname_id', $opname->id)
            ->join('products', 'products.id', '=', 'stock_opname_items.product_id')
            ->leftJoin('stocks', fn ($join) => $join
                ->on('stocks.product_id', '=', 'stock_opname_items.product_id')
                ->where('stocks.warehouse_id', '=', $opname->warehouse_id));
    }

    /**
     * One page of {@see self::itemsQuery()}. Each item also carries `damaged_goods_context`, the DG twin and the SKU
     * problem of its product worked out for the whole page at once ({@see DamagedGoodsService::decisionContextFor()}).
     *
     * @return LengthAwarePaginator<int, StockOpnameItem>
     */
    public function paginateItems(StockOpname $opname, ?string $search, ?string $filter, int $perPage): LengthAwarePaginator
    {
        $variance = $this->varianceSql($opname);

        $term = trim((string) $search);

        $items = $this->itemsQuery($opname)
            ->with(['product.baseUnit', 'product.productUnits', 'product.brand', 'reason', 'stockOpname' => fn ($opnames) => $opnames->withTrashed()])
            ->selectRaw('stock_opname_items.*, '.self::COST_PRICE_SQL.' as product_cost_price')
            ->selectRaw(self::SYSTEM_QUANTITY_SQL.' as system_quantity')
            ->selectRaw(self::SYSTEM_PACKS_SQL.' as system_intact_packs')
            ->selectRaw("{$variance} as variance_quantity")
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('products.name', 'like', "%{$term}%")
                ->orWhere('products.sku', 'like', "%{$term}%")
                ->orWhereIn('products.brand_id', Brand::query()->where('name', 'like', "%{$term}%")->select('id'))))
            ->when($filter === self::ITEM_FILTER_UNCOUNTED, fn (Builder $query) => $query->whereNull('stock_opname_items.counted_quantity'))
            ->when($filter === self::ITEM_FILTER_VARIANCE, fn (Builder $query) => $query
                ->whereNotNull('stock_opname_items.counted_quantity')
                ->whereRaw("{$variance} != 0"))
            ->when($filter === self::ITEM_FILTER_UNREVIEWED, fn (Builder $query) => $query
                ->whereRaw($this->needsReviewSql($opname))
                ->whereNull('stock_opname_items.reviewed_at'))
            ->orderBy('products.name')
            ->paginate($perPage)
            ->withQueryString();

        $damagedGoodsContexts = $this->damagedGoods->decisionContextFor($items->getCollection()->pluck('product'));
        $items->getCollection()->each(fn (StockOpnameItem $item) => $item->setAttribute('damaged_goods_context', $damagedGoodsContexts[$item->product_id]));

        return $items;
    }

    /**
     * The value of the variance is an estimate: variance × the product's current cost price (no FIFO in R1).
     *
     * @return OpnameSummary
     */
    public function summarize(StockOpname $opname): array
    {
        $variance = $this->varianceSql($opname);
        $needsReview = $this->needsReviewSql($opname);

        $totals = $this->itemsQuery($opname)->toBase()->selectRaw(
            "count(*) as items_count,
            count(stock_opname_items.counted_quantity) as counted_count,
            coalesce(sum(stock_opname_items.counted_quantity is not null and {$variance} != 0), 0) as variance_count,
            coalesce(sum({$needsReview} and stock_opname_items.reviewed_at is not null), 0) as reviewed_count,
            coalesce(sum({$needsReview}), 0) as review_count,
            coalesce(sum({$variance} * ".self::COST_PRICE_SQL.'), 0) as variance_value',
        )->first();

        return [
            'items_count' => (int) $totals->items_count,
            'counted_count' => (int) $totals->counted_count,
            'variance_count' => (int) $totals->variance_count,
            'reviewed_count' => (int) $totals->reviewed_count,
            'review_count' => (int) $totals->review_count,
            'variance_value' => (string) BigDecimal::of((string) $totals->variance_value)->toScale(2, RoundingMode::HalfUp),
        ];
    }

    /**
     * The items whose count leaves fewer whole sealed packs on the shelf than the system has now (3 whole rolls on the shelf
     * counted as 100 m of loose pieces and no roll), for the confirmation before posting. Whole packs cannot be sold as such
     * once they are gone from the count, so a count typed in the small unit only is worth a second look. A count of arrivals only
     * adds packs, and an item counted before its product had a sealed pack unit has no pack count to compare.
     *
     * @return list<array{product_name: string, pack_unit: string, system_packs: int, counted_packs: int}>
     */
    public function sealedPackDrops(StockOpname $opname): array
    {
        if ($opname->reason_code?->isArrivalCount()) {
            return [];
        }

        return $this->itemsQuery($opname)
            ->with('product.productUnits')
            ->whereNotNull('stock_opname_items.counted_packs')
            ->whereRaw(self::SYSTEM_PACKS_SQL.' > stock_opname_items.counted_packs')
            ->selectRaw('stock_opname_items.*')
            ->selectRaw(self::SYSTEM_PACKS_SQL.' as system_intact_packs')
            ->orderBy('products.name')
            ->get()
            ->filter(fn (StockOpnameItem $item): bool => $item->product->sealedPackUnit() !== null)
            ->map(fn (StockOpnameItem $item): array => [
                'product_name' => $item->product->name,
                'pack_unit' => $item->product->sealedPackUnit()->unit_code,
                'system_packs' => (int) $item->getAttribute('system_intact_packs'),
                'counted_packs' => $item->counted_packs,
            ])
            ->values()
            ->all();
    }

    /**
     * What the damaged-goods decisions of an opname add up to, for the confirmation before posting: for every item with a
     * complete decision the units going to dead stock, to a DG product and back on the shelf at full price, and what that
     * costs. A real loss (rugi) is measured against the purchase price: only the part of the price drop that takes the
     * selling price below cost is a loss, and the part that stays above cost is profit given up (loss profit). Both are
     * counted, so a DG unit sold below cost has a loss and the margin it gave up. Dead stock is sold for nothing, so it
     * loses its whole cost and the margin on top of it is loss profit. Prices are per base unit.
     *
     * @return array{items: list<DamagedGoodsRow>, dead_stock_loss: string, damaged_goods_loss: string, total_loss: string, loss_profit: string}
     */
    public function damagedGoodsSummary(StockOpname $opname): array
    {
        $rows = StockOpnameItem::query()
            ->where('stock_opname_id', $opname->id)
            ->when($opname->reason_code?->isReturnsCheck() !== true, fn (Builder $items) => $items->whereHas('reason', fn (Builder $reasons) => $reasons->where('behavior', OpnameReasonBehavior::Damaged)))
            ->whereNotNull('counted_quantity')
            ->whereNotNull('dead_stock_quantity')
            ->whereNotNull('damaged_goods_quantity')
            ->whereNotNull('full_price_quantity')
            ->with(['product.productUnits', 'product.baseUnit'])
            ->get()
            ->sortBy(fn (StockOpnameItem $item): string => $item->product->name)
            ->values();
        $damagedGoodsContexts = $this->damagedGoods->decisionContextFor($rows->pluck('product'));

        $items = [];
        $deadStockLoss = BigDecimal::zero();
        $damagedGoodsLoss = BigDecimal::zero();
        $totalLossProfit = BigDecimal::zero();
        $zero = BigDecimal::zero();

        foreach ($rows as $item) {
            $originalPrice = BigDecimal::of((string) ($item->product->productUnits->firstWhere('is_base', true)?->selling_price ?? 0));
            $damagedGoodsPrice = BigDecimal::of((string) ($item->damaged_goods_price ?? 0));
            $costPrice = BigDecimal::of((string) $item->product->cost_price);

            $deadQuantity = BigDecimal::of($item->dead_stock_quantity);
            $deadLoss = $deadQuantity->multipliedBy($costPrice);
            $deadLossProfit = $deadQuantity->multipliedBy(BigDecimal::max($originalPrice->minus($costPrice), $zero));

            $priceDrop = BigDecimal::max($originalPrice->minus($damagedGoodsPrice), $zero);
            $belowCost = BigDecimal::max($costPrice->minus($damagedGoodsPrice), $zero);
            $lossPerUnit = BigDecimal::min($priceDrop, $belowCost);
            $damagedQuantity = BigDecimal::of($item->damaged_goods_quantity);
            $dgLoss = $damagedQuantity->multipliedBy($lossPerUnit);
            $dgLossProfit = $damagedQuantity->multipliedBy($priceDrop->minus($lossPerUnit));

            $deadStockLoss = $deadStockLoss->plus($deadLoss);
            $damagedGoodsLoss = $damagedGoodsLoss->plus($dgLoss);
            $rowLossProfit = $deadLossProfit->plus($dgLossProfit);
            $totalLossProfit = $totalLossProfit->plus($rowLossProfit);

            $items[] = [
                'product_name' => $item->product->name,
                'base_unit' => $item->product->baseUnit->code,
                'dead_stock_quantity' => $item->dead_stock_quantity,
                'damaged_goods_quantity' => $item->damaged_goods_quantity,
                'full_price_quantity' => $item->full_price_quantity,
                'cost_price' => (string) $costPrice->toScale(2, RoundingMode::HalfUp),
                'original_price' => (string) $originalPrice->toScale(2, RoundingMode::HalfUp),
                'damaged_goods_price' => $item->damaged_goods_price,
                'existing_price' => $damagedGoodsContexts[$item->product_id]['existing']['price'] ?? null,
                'loss' => (string) $deadLoss->plus($dgLoss)->toScale(2, RoundingMode::HalfUp),
                'loss_profit' => (string) $rowLossProfit->toScale(2, RoundingMode::HalfUp),
            ];
        }

        return [
            'items' => $items,
            'dead_stock_loss' => (string) $deadStockLoss->toScale(2, RoundingMode::HalfUp),
            'damaged_goods_loss' => (string) $damagedGoodsLoss->toScale(2, RoundingMode::HalfUp),
            'total_loss' => (string) $deadStockLoss->plus($damagedGoodsLoss)->toScale(2, RoundingMode::HalfUp),
            'loss_profit' => (string) $totalLossProfit->toScale(2, RoundingMode::HalfUp),
        ];
    }

    /**
     * SQL that is true for an item the admin has to review: a counted one with a variance, or, in an inspection of returns,
     * every counted one (it still has to be decided where the goods go). Mirrors `StockOpnameService::isReviewNeeded()`.
     */
    private function needsReviewSql(StockOpname $opname): string
    {
        return $opname->reason_code?->isReturnsCheck()
            ? 'stock_opname_items.counted_quantity is not null'
            : '(stock_opname_items.counted_quantity is not null and '.$this->varianceSql($opname).' != 0)';
    }

    /**
     * SQL for the variance of an item (null while it is not counted); the same rule as {@see StockOpname::varianceOf()}.
     */
    private function varianceSql(StockOpname $opname): string
    {
        return $opname->reason_code?->isArrivalCount()
            ? 'stock_opname_items.counted_quantity'
            : '(stock_opname_items.counted_quantity - '.self::SYSTEM_QUANTITY_SQL.')';
    }
}
