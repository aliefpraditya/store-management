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
use App\Models\Warehouse;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Runs a stock opname: create, snapshot, count, review, post. Counting is allowed while sales go on: the
 * system quantity of an item is simply the live stock (snapshot + movements since the snapshot), and the
 * variance is written to the ledger when the opname is posted.
 *
 * @phpstan-type CountPart array{unit_code: string, quantity: string|null}
 * @phpstan-type CountRow array{item_id: int, parts: list<CountPart>, reason: string|null}
 */
class StockOpnameService
{
    private const NUMBER_TYPE = 'OPN';

    private const INSERT_CHUNK_SIZE = 500;

    /** The most a decimal(15,3) quantity column holds. */
    private const MAX_BASE_QUANTITY = '999999999999.999';

    public function __construct(
        private readonly StockService $stockLedger,
        private readonly DocumentNumberGenerator $documentNumbers,
        private readonly StoreSettingService $settings,
    ) {}

    /**
     * @param  array{warehouse_id: int, opname_date: string, scope: string, category_id?: int|null, brand_id?: int|null, reason_code: string}  $opnameInput
     */
    public function create(array $opnameInput, int $userId): StockOpname
    {
        return DB::transaction(function () use ($opnameInput, $userId): StockOpname {
            $date = Carbon::parse($opnameInput['opname_date']);
            $scope = StockOpnameScope::from($opnameInput['scope']);

            return StockOpname::query()->create([
                'number' => $this->documentNumbers->next(self::NUMBER_TYPE, $date),
                'warehouse_id' => $opnameInput['warehouse_id'],
                'opname_date' => $date,
                'scope' => $scope,
                'category_id' => $scope === StockOpnameScope::Category ? ($opnameInput['category_id'] ?? null) : null,
                'brand_id' => $scope === StockOpnameScope::Category ? ($opnameInput['brand_id'] ?? null) : null,
                'status' => StockOpnameStatus::Draft,
                'reason_code' => $opnameInput['reason_code'],
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
            $this->lockWarehouseClaims($opname);

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

            $this->lockWarehouseClaims($opname);

            if ($opname->scope === StockOpnameScope::Selected) {
                $this->refreshSelectedSnapshots($opname);
                $skippedCount = 0;
            } else {
                $skippedCount = $this->createSnapshotItems($opname);
            }

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

            $itemsById = $opname->items()
                ->with('product.productUnits')
                ->whereIn('id', array_column($counts, 'item_id'))
                ->get()
                ->keyBy('id');

            foreach ($counts as $count) {
                $item = $itemsById->get($count['item_id']);

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
     * The stock rows of all counted items are locked in one query, in product order (the order a sale locks them in, so
     * the two do not deadlock on existing rows) and are handed to the ledger, which does not read them again. A product's
     * first stock row can still be created by both at once, so a deadlock is retried. The cost price is kept on each item,
     * so the value of the variance does not move when a cost price is edited later.
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

            $countedItems = $opname->items()->with('product:id,name,cost_price')->whereNotNull('counted_quantity')->orderBy('product_id')->get();
            $systemQuantities = $this->stockLedger->lockedQuantities($countedItems->pluck('product_id')->all(), $opname->warehouse_id);

            $this->requireReasonsForLargeVariances($opname, $countedItems, $systemQuantities);

            foreach ($countedItems as $item) {
                $systemQuantity = $systemQuantities[$item->product_id];
                $variance = $opname->varianceOf($item->counted_quantity, $systemQuantity);

                $item->forceFill([
                    'system_quantity_at_posting' => $systemQuantity,
                    'cost_price_at_posting' => $item->product->cost_price,
                    'variance' => (string) $variance,
                ])->save();

                if (! $variance->isZero()) {
                    $this->stockLedger->record(
                        $item->product_id,
                        $opname->warehouse_id,
                        $movementType,
                        (string) $variance,
                        $opname,
                        $userId,
                        $item->reason ?? $opname->reason_code?->label(),
                        $systemQuantity,
                    );
                }
            }

            $opname->forceFill(['status' => StockOpnameStatus::Posted, 'approved_by' => $userId, 'posted_at' => now()])->save();
        }, StockService::DEADLOCK_ATTEMPTS);
    }

    /**
     * A variance worth more than the store's limit (the variance times the last cost price) needs a reason on its row.
     * Checked before anything is written, against the stock as it is locked now, so nothing is half posted.
     *
     * @param  Collection<int, StockOpnameItem>  $countedItems
     * @param  array<int, string>  $systemQuantities  live quantity by product id
     *
     * @throws StockOpnameException
     */
    private function requireReasonsForLargeVariances(StockOpname $opname, Collection $countedItems, array $systemQuantities): void
    {
        $threshold = $this->settings->largeVarianceThreshold();

        if ($threshold === null) {
            return;
        }

        $namesWithoutReason = $countedItems
            ->filter(fn (StockOpnameItem $item): bool => blank($item->reason)
                && $opname->varianceOf($item->counted_quantity, $systemQuantities[$item->product_id])->abs()->multipliedBy($item->product->cost_price)->isGreaterThan($threshold))
            ->map(fn (StockOpnameItem $item): string => $item->product->name)
            ->values()
            ->all();

        if ($namesWithoutReason !== []) {
            throw StockOpnameException::largeVarianceNeedsReason($namesWithoutReason, number_format((float) (string) $threshold, 0, ',', '.'));
        }
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
     * @param  list<CountPart>  $parts
     *
     * @throws StockOpnameException
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

        if ($total->isGreaterThan(self::MAX_BASE_QUANTITY)) {
            throw StockOpnameException::quantityTooLarge($product->name);
        }

        return (string) $total->toScale(3, RoundingMode::HalfUp);
    }

    /**
     * Re-reads the opname under a row lock and checks its status, so two requests cannot both move it on.
     *
     * @throws StockOpnameException
     */
    private function lockedInStatus(StockOpname $opname, string $attemptedAction, StockOpnameStatus ...$allowed): StockOpname
    {
        $lockedOpname = StockOpname::query()->lockForUpdate()->findOrFail($opname->id);

        if (! in_array($lockedOpname->status, $allowed, true)) {
            throw StockOpnameException::wrongStatus($attemptedAction, $lockedOpname->status->label());
        }

        return $lockedOpname;
    }

    /**
     * "A product is in one active opname per warehouse" is checked by reading the other opnames, which two requests
     * can do at the same time and both pass. Taking the warehouse row first makes those checks queue up, so the second
     * one sees the first one's products. Called right after the opname is locked, before anything is read.
     */
    private function lockWarehouseClaims(StockOpname $opname): void
    {
        Warehouse::query()->lockForUpdate()->findOrFail($opname->warehouse_id);
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
    private function refreshSelectedSnapshots(StockOpname $opname): void
    {
        $productIds = $opname->items()->pluck('product_id')->all();

        if ($productIds === []) {
            throw StockOpnameException::noProducts();
        }

        $quantities = $this->currentQuantities($opname, $productIds);

        foreach ($opname->items()->get() as $item) {
            $item->forceFill(['snapshot_quantity' => $quantities[$item->product_id] ?? '0'])->save();
        }
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
            $busyInChunk = StockOpnameItem::query()
                ->join('stock_opnames', 'stock_opnames.id', '=', 'stock_opname_items.stock_opname_id')
                ->where('stock_opnames.warehouse_id', $opname->warehouse_id)
                ->whereIn('stock_opnames.status', StockOpnameStatus::activeValues())
                ->where('stock_opnames.id', '!=', $opname->id)
                ->whereIn('stock_opname_items.product_id', $chunk)
                ->pluck('stock_opname_items.product_id')
                ->all();

            $busyIds = [...$busyIds, ...$busyInChunk];
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
