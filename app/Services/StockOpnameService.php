<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Exceptions\StockOpnameException;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Runs a stock opname: create, snapshot, count, review, post. Counting is allowed while sales go on: the
 * system quantity of an item is simply the live stock (snapshot + movements since the snapshot), and the
 * variance is written to the ledger when the opname is posted.
 *
 * @phpstan-type CountPart array{unit_code: string, quantity: string|null}
 * @phpstan-type CountRow array{item_id: int, parts: list<CountPart>, reason: string|null}
 * @phpstan-type OpnameSummary array{items_count: int, counted_count: int, variance_count: int, variance_value: string}
 */
class StockOpnameService
{
    private const NUMBER_TYPE = 'OPN';

    private const INSERT_CHUNK_SIZE = 500;

    /** Live stock until the opname is posted, then the quantity it was posted against. */
    private const SYSTEM_QUANTITY_SQL = 'coalesce(stock_opname_items.system_quantity_at_posting, stocks.quantity, 0)';

    public const ITEM_FILTER_UNCOUNTED = 'uncounted';

    public const ITEM_FILTER_VARIANCE = 'variance';

    public function __construct(
        private readonly StockService $stock,
        private readonly DocumentNumberGenerator $numbers,
    ) {}

    /**
     * @param  array{warehouse_id: int, opname_date: string, scope: string, category_id?: int|null, brand_id?: int|null, reason_code: string}  $data
     */
    public function create(array $data, int $userId): StockOpname
    {
        return DB::transaction(function () use ($data, $userId): StockOpname {
            $date = Carbon::parse($data['opname_date']);
            $scope = StockOpnameScope::from($data['scope']);

            return StockOpname::query()->create([
                'number' => $this->numbers->next(self::NUMBER_TYPE, $date),
                'warehouse_id' => $data['warehouse_id'],
                'opname_date' => $date,
                'scope' => $scope,
                'category_id' => $scope === StockOpnameScope::Category ? ($data['category_id'] ?? null) : null,
                'brand_id' => $scope === StockOpnameScope::Category ? ($data['brand_id'] ?? null) : null,
                'status' => StockOpnameStatus::Draft,
                'reason_code' => $data['reason_code'],
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Adds a product to an opname with the "selected products" scope. Adding it twice does nothing.
     *
     * @throws StockOpnameException
     */
    public function addProduct(StockOpname $opname, Product $product): void
    {
        DB::transaction(function () use ($opname, $product): void {
            $opname = $this->lockedInStatus($opname, 'menambah barang', StockOpnameStatus::Draft, StockOpnameStatus::Counting);
            $this->requireSelectedScope($opname);

            if ($opname->items()->where('product_id', $product->id)->exists()) {
                return;
            }

            if ($this->busyProductIds($opname, [$product->id]) !== []) {
                throw StockOpnameException::productIsBusy($product->name);
            }

            $opname->items()->create([
                'product_id' => $product->id,
                'snapshot_quantity' => $this->currentQuantities($opname, [$product->id])[$product->id] ?? '0',
            ]);
        });
    }

    /**
     * @throws StockOpnameException
     */
    public function removeItem(StockOpname $opname, StockOpnameItem $item): void
    {
        DB::transaction(function () use ($opname, $item): void {
            $opname = $this->lockedInStatus($opname, 'menghapus barang', StockOpnameStatus::Draft, StockOpnameStatus::Counting);
            $this->requireSelectedScope($opname);

            $item->delete();
        });
    }

    /**
     * Takes the snapshot and opens counting. For the category/brand scope the items are created here; products
     * another active opname of the same warehouse already holds are left out.
     *
     * @return int how many products were left out
     *
     * @throws StockOpnameException
     */
    public function start(StockOpname $opname): int
    {
        return DB::transaction(function () use ($opname): int {
            $opname = $this->lockedInStatus($opname, 'dimulai', StockOpnameStatus::Draft);

            $skippedCount = $opname->scope === StockOpnameScope::Selected
                ? $this->refreshSelectedSnapshots($opname)
                : $this->createSnapshotItems($opname);

            $opname->forceFill(['status' => StockOpnameStatus::Counting, 'snapshot_at' => now()])->save();

            return $skippedCount;
        });
    }

    /**
     * Saves counted quantities. Each count is a list of quantities in any of the product's units, e.g. 5 DUS
     * and 3 PAIL, added up into the base unit; a count with no quantity at all marks the item as not counted.
     *
     * @param  list<CountRow>  $counts
     *
     * @throws StockOpnameException
     */
    public function saveCounts(StockOpname $opname, array $counts): void
    {
        DB::transaction(function () use ($opname, $counts): void {
            $opname = $this->lockedInStatus($opname, 'diisi hitungannya', StockOpnameStatus::Counting);

            $items = $opname->items()
                ->with('product.productUnits')
                ->whereIn('id', array_column($counts, 'item_id'))
                ->get()
                ->keyBy('id');

            foreach ($counts as $count) {
                $item = $items->get($count['item_id']);

                $item?->forceFill([
                    'counted_quantity' => $this->toBaseQuantity($item->product, $count['parts']),
                    'reason' => filled($count['reason']) ? trim($count['reason']) : null,
                ])->save();
            }
        });
    }

    /**
     * The explicit "not counted means zero" action: blank is never treated as zero by itself.
     *
     * @throws StockOpnameException
     */
    public function countUncountedAsZero(StockOpname $opname): void
    {
        DB::transaction(function () use ($opname): void {
            $opname = $this->lockedInStatus($opname, 'diisi hitungannya', StockOpnameStatus::Counting);

            $opname->items()->whereNull('counted_quantity')->update(['counted_quantity' => 0]);
        });
    }

    /**
     * @throws StockOpnameException
     */
    public function finishCounting(StockOpname $opname): void
    {
        DB::transaction(function () use ($opname): void {
            $opname = $this->lockedInStatus($opname, 'diselesaikan hitungannya', StockOpnameStatus::Counting);

            if (! $opname->items()->whereNotNull('counted_quantity')->exists()) {
                throw StockOpnameException::nothingCounted();
            }

            $opname->forceFill(['status' => StockOpnameStatus::Review])->save();
        });
    }

    /**
     * @throws StockOpnameException
     */
    public function reopenCounting(StockOpname $opname): void
    {
        DB::transaction(function () use ($opname): void {
            $opname = $this->lockedInStatus($opname, 'dihitung ulang', StockOpnameStatus::Review);

            $opname->forceFill(['status' => StockOpnameStatus::Counting])->save();
        });
    }

    /**
     * Writes the variances to the stock ledger. Items that were never counted are left alone. The system
     * quantity is the live stock under a row lock, so a sale that lands during posting is not overwritten.
     * For an opname that counts arrivals the typed quantity is the variance itself: it is added to the stock.
     *
     * @throws StockOpnameException
     */
    public function post(StockOpname $opname, int $userId): void
    {
        DB::transaction(function () use ($opname, $userId): void {
            $opname = $this->lockedInStatus($opname, 'diposting', StockOpnameStatus::Review);
            $movementType = $opname->reason_code === StockOpnameReason::OpeningBalance
                ? StockMovementType::OpeningBalance
                : StockMovementType::OpnameAdjustment;

            foreach ($opname->items()->whereNotNull('counted_quantity')->get() as $item) {
                $systemQuantity = $this->stock->lockedQuantity($item->product_id, $opname->warehouse_id);
                $variance = $opname->reason_code?->countsArrivals()
                    ? BigDecimal::of($item->counted_quantity)
                    : BigDecimal::of($item->counted_quantity)->minus($systemQuantity);

                $item->forceFill(['system_quantity_at_posting' => $systemQuantity, 'variance' => (string) $variance])->save();

                if (! $variance->isZero()) {
                    $this->stock->record(
                        $item->product_id,
                        $opname->warehouse_id,
                        $movementType,
                        (string) $variance,
                        $opname,
                        $userId,
                        $item->reason ?? $opname->reason_code?->label(),
                    );
                }
            }

            $opname->forceFill(['status' => StockOpnameStatus::Posted, 'approved_by' => $userId, 'posted_at' => now()])->save();
        });
    }

    /**
     * A draft is erased for good (its number is not handed out again). Once counting has started the opname is
     * marked cancelled and soft deleted: hidden from the list, but its counts stay as a record.
     *
     * @return bool true when the draft was erased, false when it was kept soft deleted
     *
     * @throws StockOpnameException
     */
    public function cancel(StockOpname $opname): bool
    {
        return DB::transaction(function () use ($opname): bool {
            $opname = $this->lockedInStatus($opname, 'dibatalkan', StockOpnameStatus::Draft, StockOpnameStatus::Counting, StockOpnameStatus::Review);

            if ($opname->status->isErasedWhenCancelled()) {
                $opname->items()->delete();
                $opname->forceDelete();

                return true;
            }

            $opname->forceFill(['status' => StockOpnameStatus::Cancelled])->save();
            $opname->delete();

            return false;
        });
    }

    /**
     * The items of an opname with `system_quantity` (live stock until posted, then the stored value),
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
     * @return LengthAwarePaginator<int, StockOpnameItem>
     */
    public function paginateItems(StockOpname $opname, ?string $search, ?string $filter, int $perPage): LengthAwarePaginator
    {
        $variance = $this->varianceSql($opname);

        $term = trim((string) $search);

        return $this->itemsQuery($opname)
            ->with('product.baseUnit', 'product.productUnits')
            ->select('stock_opname_items.*', 'products.cost_price as product_cost_price')
            ->selectRaw(self::SYSTEM_QUANTITY_SQL.' as system_quantity')
            ->selectRaw("{$variance} as variance_quantity")
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('products.name', 'like', "%{$term}%")
                ->orWhere('products.sku', 'like', "%{$term}%")))
            ->when($filter === self::ITEM_FILTER_UNCOUNTED, fn (Builder $query) => $query->whereNull('stock_opname_items.counted_quantity'))
            ->when($filter === self::ITEM_FILTER_VARIANCE, fn (Builder $query) => $query
                ->whereNotNull('stock_opname_items.counted_quantity')
                ->whereRaw("{$variance} != 0"))
            ->orderBy('products.name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The value of the variance is an estimate: variance × the product's current cost price (no FIFO in R1).
     *
     * @return OpnameSummary
     */
    public function summarize(StockOpname $opname): array
    {
        $variance = $this->varianceSql($opname);

        $row = $this->itemsQuery($opname)->toBase()->selectRaw(
            "count(*) as items_count,
            count(stock_opname_items.counted_quantity) as counted_count,
            coalesce(sum(stock_opname_items.counted_quantity is not null and {$variance} != 0), 0) as variance_count,
            coalesce(sum({$variance} * products.cost_price), 0) as variance_value",
        )->first();

        return [
            'items_count' => (int) $row->items_count,
            'counted_count' => (int) $row->counted_count,
            'variance_count' => (int) $row->variance_count,
            'variance_value' => (string) BigDecimal::of((string) $row->variance_value)->toScale(2, RoundingMode::HalfUp),
        ];
    }

    /**
     * SQL for the variance of an item (null while it is not counted): counted minus the system quantity, or just
     * the counted quantity when the opname counts arrivals.
     */
    private function varianceSql(StockOpname $opname): string
    {
        return $opname->reason_code?->countsArrivals()
            ? 'stock_opname_items.counted_quantity'
            : '(stock_opname_items.counted_quantity - '.self::SYSTEM_QUANTITY_SQL.')';
    }

    /**
     * @param  list<CountPart>  $parts
     */
    private function toBaseQuantity(Product $product, array $parts): ?string
    {
        $filledParts = array_filter($parts, fn (array $part): bool => filled($part['quantity']));

        if ($filledParts === []) {
            return null;
        }

        $factors = $product->productUnits->pluck('conversion_factor', 'unit_code');
        $total = BigDecimal::zero();

        foreach ($filledParts as $part) {
            $total = $total->plus(BigDecimal::of($part['quantity'])->multipliedBy($factors[$part['unit_code']]));
        }

        return (string) $total->toScale(3, RoundingMode::HalfUp);
    }

    /**
     * Re-reads the opname under a row lock and checks its status, so two requests cannot both move it on.
     *
     * @throws StockOpnameException
     */
    private function lockedInStatus(StockOpname $opname, string $action, StockOpnameStatus ...$allowed): StockOpname
    {
        $locked = StockOpname::query()->lockForUpdate()->findOrFail($opname->id);

        if (! in_array($locked->status, $allowed, true)) {
            throw StockOpnameException::wrongStatus($action, $locked->status->label());
        }

        return $locked;
    }

    private function requireSelectedScope(StockOpname $opname): void
    {
        if ($opname->scope !== StockOpnameScope::Selected) {
            throw StockOpnameException::notSelectedScope();
        }
    }

    /**
     * @throws StockOpnameException
     */
    private function refreshSelectedSnapshots(StockOpname $opname): int
    {
        $productIds = $opname->items()->pluck('product_id')->all();

        if ($productIds === []) {
            throw StockOpnameException::noProducts();
        }

        $quantities = $this->currentQuantities($opname, $productIds);

        foreach ($opname->items()->get() as $item) {
            $item->forceFill(['snapshot_quantity' => $quantities[$item->product_id] ?? '0'])->save();
        }

        return 0;
    }

    /**
     * @throws StockOpnameException
     */
    private function createSnapshotItems(StockOpname $opname): int
    {
        $productIds = Product::query()
            ->when($opname->category_id, fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($opname->brand_id, fn (Builder $query, int $brandId) => $query->where('brand_id', $brandId))
            ->pluck('id')
            ->all();

        if ($productIds === []) {
            throw StockOpnameException::noMatchingProducts();
        }

        $freeProductIds = array_values(array_diff($productIds, $this->busyProductIds($opname, $productIds)));
        $quantities = $this->currentQuantities($opname, $freeProductIds);
        $timestamp = now();

        foreach (array_chunk($freeProductIds, self::INSERT_CHUNK_SIZE) as $chunk) {
            StockOpnameItem::query()->insert(array_map(fn (int $productId): array => [
                'stock_opname_id' => $opname->id,
                'product_id' => $productId,
                'snapshot_quantity' => $quantities[$productId] ?? '0',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $chunk));
        }

        return count($productIds) - count($freeProductIds);
    }

    /**
     * The given products that another active opname of the same warehouse already holds.
     *
     * @param  list<int>  $productIds
     * @return list<int>
     */
    private function busyProductIds(StockOpname $opname, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $busyIds = [];

        foreach (array_chunk($productIds, self::INSERT_CHUNK_SIZE) as $chunk) {
            $busy = StockOpnameItem::query()
                ->join('stock_opnames', 'stock_opnames.id', '=', 'stock_opname_items.stock_opname_id')
                ->where('stock_opnames.warehouse_id', $opname->warehouse_id)
                ->whereIn('stock_opnames.status', StockOpnameStatus::activeValues())
                ->where('stock_opnames.id', '!=', $opname->id)
                ->whereIn('stock_opname_items.product_id', $chunk)
                ->pluck('stock_opname_items.product_id')
                ->all();

            $busyIds = [...$busyIds, ...$busy];
        }

        return array_map('intval', $busyIds);
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, string> base-unit quantity per product id; products without a stock row are absent
     */
    private function currentQuantities(StockOpname $opname, array $productIds): array
    {
        $quantities = [];

        foreach (array_chunk($productIds, self::INSERT_CHUNK_SIZE) as $chunk) {
            $quantities += DB::table('stocks')
                ->where('warehouse_id', $opname->warehouse_id)
                ->whereIn('product_id', $chunk)
                ->pluck('quantity', 'product_id')
                ->map(fn ($quantity): string => (string) $quantity)
                ->all();
        }

        return $quantities;
    }
}
