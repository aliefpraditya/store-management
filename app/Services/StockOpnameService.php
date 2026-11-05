<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Enums\WarehouseType;
use App\Exceptions\StockOpnameException;
use App\Models\OpnameReason;
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
 * Every variance of a routine opname is explained and reviewed before posting: the item gets a reason from the store's list,
 * an admin marks it reviewed, and for a damaged reason (Rusak) the admin also splits the damaged units into dead stock, a
 * DG product sold at its own price, and units that turn out fine to sell at full price. Posting writes those splits as
 * stock movements.
 *
 * @phpstan-type CountPart array{unit_code: string, quantity: string|null}
 * @phpstan-type CountRow array{item_id: int, parts: list<CountPart>, reason_id: int|null, note: string|null}
 * @phpstan-type ReviewRow array{item_id: int, reason_id: int|null, note: string|null, is_reviewed: bool, dead_stock_quantity: string|null, damaged_goods_quantity: string|null, full_price_quantity: string|null, damaged_goods_price: string|null}
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
        private readonly DamagedGoodsService $damagedGoods,
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
                    'reason_id' => $count['reason_id'],
                    'note' => filled($count['note']) ? trim($count['note']) : null,
                ])->save();
            }
        });
    }

    /**
     * Saves what the admin decided while reviewing the variances: the reason, a note, whether the item is reviewed, and for
     * a damaged reason where the damaged units go. An item can only be marked reviewed when it is complete, so a half
     * decided one is refused with the reason, and the rest of the page is not saved either.
     *
     * @param  list<ReviewRow>  $rows
     *
     * @throws StockOpnameException
     */
    public function saveReview(StockOpname $opname, int $userId, array $rows): void
    {
        DB::transaction(function () use ($opname, $userId, $rows): void {
            $opname = $this->lockedInStatus($opname, 'direview', StockOpnameStatus::Review);

            $itemsById = $opname->items()->with(['product', 'reason'])->whereIn('id', array_column($rows, 'item_id'))->get()->keyBy('id');
            $reasonsById = OpnameReason::query()->get()->keyBy('id');
            $systemQuantities = $this->currentQuantities($opname, $itemsById->pluck('product_id')->all());

            foreach ($rows as $row) {
                $item = $itemsById->get($row['item_id']);

                if ($item === null) {
                    continue;
                }

                $reason = $row['reason_id'] === null ? null : $reasonsById->get($row['reason_id']);
                $isDamaged = $reason?->behavior->needsDamageDecision() ?? false;

                $item->forceFill([
                    'reason_id' => $reason?->id,
                    'note' => filled($row['note']) ? trim($row['note']) : null,
                    'dead_stock_quantity' => $isDamaged ? $row['dead_stock_quantity'] : null,
                    'damaged_goods_quantity' => $isDamaged ? $row['damaged_goods_quantity'] : null,
                    'full_price_quantity' => $isDamaged ? $row['full_price_quantity'] : null,
                    'damaged_goods_price' => $isDamaged ? $row['damaged_goods_price'] : null,
                ])->setRelation('reason', $reason);

                $this->markReviewed($opname, $item, $row['is_reviewed'], $userId, $systemQuantities[$item->product_id] ?? '0');

                $item->save();
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

            // A new count can change a variance, so what was reviewed on the old one has to be looked at again.
            $opname->items()->update(['reviewed_at' => null, 'reviewed_by' => null]);
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
     * Before anything is written, a routine opname is checked against the stock as it is locked now: every variance needs
     * a reason, a decision when the reason is damaged goods, and the admin's review mark, and a variance above the store's
     * limit needs a note. A sale landing during the review can change a variance, which is why this is checked here and not
     * only when the admin marked the item.
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

            $countedItems = $opname->items()->with(['product', 'reason'])->whereNotNull('counted_quantity')->orderBy('product_id')->get();
            $systemQuantities = $this->stockLedger->lockedQuantities($countedItems->pluck('product_id')->all(), $opname->warehouse_id);

            $this->requireNotesForLargeVariances($opname, $countedItems, $systemQuantities);
            $this->requireReviewedVariances($opname, $countedItems, $systemQuantities);

            foreach ($countedItems as $item) {
                $systemQuantity = $systemQuantities[$item->product_id];
                $variance = $opname->varianceOf($item->counted_quantity, $systemQuantity);

                $item->forceFill([
                    'system_quantity_at_posting' => $systemQuantity,
                    'cost_price_at_posting' => $item->product->cost_price,
                    'variance' => (string) $variance,
                ])->save();

                if ($variance->isZero()) {
                    continue;
                }

                if ($this->isDamagedGoodsDecision($opname, $item)) {
                    $this->postDamagedItem($opname, $item, $systemQuantity, $movementType, $userId);

                    continue;
                }

                $this->stockLedger->record($item->product_id, $opname->warehouse_id, $movementType, (string) $variance, $opname, $userId, $this->movementNote($opname, $item), $systemQuantity);
            }

            $opname->forceFill(['status' => StockOpnameStatus::Posted, 'approved_by' => $userId, 'posted_at' => now()])->save();
        }, StockService::DEADLOCK_ATTEMPTS);
    }

    /**
     * A variance worth more than the store's limit (the variance times the last cost price) needs a note on its row.
     * Checked before anything is written, against the stock as it is locked now, so nothing is half posted.
     *
     * @param  Collection<int, StockOpnameItem>  $countedItems
     * @param  array<int, string>  $systemQuantities  live quantity by product id
     *
     * @throws StockOpnameException
     */
    private function requireNotesForLargeVariances(StockOpname $opname, Collection $countedItems, array $systemQuantities): void
    {
        $threshold = $this->settings->largeVarianceThreshold();

        if ($threshold === null) {
            return;
        }

        $namesWithoutNote = $countedItems
            ->filter(fn (StockOpnameItem $item): bool => blank($item->note)
                && $opname->varianceOf($item->counted_quantity, $systemQuantities[$item->product_id])->abs()->multipliedBy($item->product->cost_price)->isGreaterThan($threshold))
            ->map(fn (StockOpnameItem $item): string => $item->product->name)
            ->values()
            ->all();

        if ($namesWithoutNote !== []) {
            throw StockOpnameException::largeVarianceNeedsNote($namesWithoutNote, number_format((float) (string) $threshold, 0, ',', '.'));
        }
    }

    /**
     * @param  Collection<int, StockOpnameItem>  $countedItems
     * @param  array<int, string>  $systemQuantities  live quantity by product id
     *
     * @throws StockOpnameException
     */
    private function requireReviewedVariances(StockOpname $opname, Collection $countedItems, array $systemQuantities): void
    {
        if (! $opname->reason_code?->requiresVarianceReview()) {
            return;
        }

        $problems = [];

        foreach ($countedItems as $item) {
            $variance = $opname->varianceOf($item->counted_quantity, $systemQuantities[$item->product_id]);
            $problem = $variance->isZero() ? null : $this->reviewProblem($item, $variance, true);

            if ($problem !== null) {
                $problems[] = "{$item->product->name} ({$problem})";
            }
        }

        if ($problems !== []) {
            throw StockOpnameException::reviewIncomplete($problems);
        }
    }

    /**
     * Marks an item reviewed or not. Marking needs the item counted and complete, judged against the live stock.
     *
     * @param  numeric-string  $systemQuantity
     *
     * @throws StockOpnameException
     */
    private function markReviewed(StockOpname $opname, StockOpnameItem $item, bool $isReviewed, int $userId, string $systemQuantity): void
    {
        if (! $isReviewed) {
            $item->forceFill(['reviewed_at' => null, 'reviewed_by' => null]);

            return;
        }

        if (! $item->isCounted()) {
            throw StockOpnameException::cannotReview($item->product->name, 'belum dihitung, jadi belum bisa ditandai sudah direview.');
        }

        $variance = $opname->varianceOf((string) $item->counted_quantity, $systemQuantity);
        $problem = $variance->isZero() ? null : $this->reviewProblem($item, $variance, false);

        if ($problem !== null) {
            throw StockOpnameException::cannotReview($item->product->name, $problem);
        }

        $item->forceFill(['reviewed_at' => $item->reviewed_at ?? now(), 'reviewed_by' => $item->reviewed_by ?? $userId]);
    }

    /**
     * What is still missing on an item with a variance before it can be posted, in words for the admin, or null when it
     * is complete. The reason comes first; for a damaged reason the three parts of the decision must be filled and add up
     * to the shortage, and units that become a DG product need a price and a DG SKU that is free.
     *
     * @param  bool  $needsReviewMark  whether the admin's "reviewed" mark is also required
     */
    private function reviewProblem(StockOpnameItem $item, BigDecimal $variance, bool $needsReviewMark): ?string
    {
        if ($item->reason === null) {
            return 'alasan belum dipilih';
        }

        if ($item->reason->behavior->needsDamageDecision()) {
            $problem = $this->damageDecisionProblem($item, $variance);

            if ($problem !== null) {
                return $problem;
            }
        }

        return $needsReviewMark && ! $item->isReviewed() ? 'belum ditandai sudah direview' : null;
    }

    private function damageDecisionProblem(StockOpnameItem $item, BigDecimal $variance): ?string
    {
        if ($variance->isPositive()) {
            return 'alasan Rusak hanya untuk selisih kurang';
        }

        if ($item->dead_stock_quantity === null || $item->damaged_goods_quantity === null || $item->full_price_quantity === null) {
            return 'keputusan barang rusak belum diisi';
        }

        $deadStock = BigDecimal::of($item->dead_stock_quantity);
        $damagedGoods = BigDecimal::of($item->damaged_goods_quantity);
        $shortage = $variance->abs();

        if (! $deadStock->plus($damagedGoods)->plus($item->full_price_quantity)->isEqualTo($shortage)) {
            return 'keputusan barang rusak harus berjumlah sama dengan selisih ('.rtrim(rtrim((string) $shortage->toScale(3), '0'), '.').')';
        }

        if ($damagedGoods->isPositive()) {
            if ($item->damaged_goods_price === null) {
                return 'harga jual barang DG belum diisi';
            }

            $skuProblem = $this->damagedGoods->problemFor($item->product);

            if ($skuProblem !== null) {
                return $skuProblem;
            }
        }

        return $deadStock->isPositive() && $this->deadStockWarehouse() === null ? 'gudang dead stock tidak ada' : null;
    }

    /**
     * Whether the item is a damaged-goods shortage whose decision splits where the units go (only routine opnames ask for it).
     */
    private function isDamagedGoodsDecision(StockOpname $opname, StockOpnameItem $item): bool
    {
        return $opname->reason_code?->requiresVarianceReview() === true && $item->reason?->behavior->needsDamageDecision() === true;
    }

    /**
     * Posts a damaged-goods shortage: the units that stay on the shelf at full price are not touched, and the others
     * leave the shelf of the opname's warehouse for dead stock (the same product in the dead stock warehouse) or for the
     * DG product, which is made now when it does not exist. A shortage is the only thing that gets here, so the units
     * leaving are a part of what the count found missing.
     *
     * @param  numeric-string  $systemQuantity  the locked stock of the item in the opname's warehouse
     */
    private function postDamagedItem(StockOpname $opname, StockOpnameItem $item, string $systemQuantity, StockMovementType $movementType, int $userId): void
    {
        $deadStock = BigDecimal::of($item->dead_stock_quantity);
        $damagedGoods = BigDecimal::of($item->damaged_goods_quantity);
        $leavingShelf = $deadStock->plus($damagedGoods);
        $note = $this->movementNote($opname, $item);

        if ($leavingShelf->isPositive()) {
            $this->stockLedger->record($item->product_id, $opname->warehouse_id, $movementType, (string) $leavingShelf->negated(), $opname, $userId, $note, $systemQuantity);
        }

        if ($deadStock->isPositive()) {
            $this->stockLedger->record($item->product_id, $this->deadStockWarehouse()->id, $movementType, (string) $deadStock, $opname, $userId, "{$note} (dead stock)");
        }

        if ($damagedGoods->isPositive()) {
            $twin = $this->damagedGoods->productFor($item->product, (string) $item->damaged_goods_price);

            $this->stockLedger->record($twin->id, $opname->warehouse_id, $movementType, (string) $damagedGoods, $opname, $userId, "{$note} (barang DG {$twin->sku})");
        }
    }

    private function deadStockWarehouse(): ?Warehouse
    {
        return Warehouse::query()->where('type', WarehouseType::DeadStock)->where('is_active', true)->orderByDesc('is_default')->orderBy('id')->first();
    }

    /**
     * The note a stock movement of the item carries: its reason (or what the opname is for) and the note typed with it.
     */
    private function movementNote(StockOpname $opname, StockOpnameItem $item): ?string
    {
        $note = collect([$item->reason?->name ?? $opname->reason_code?->label(), $item->note])->filter(fn (?string $part): bool => filled($part))->implode(': ');

        return $note === '' ? null : $note;
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
