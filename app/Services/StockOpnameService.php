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
 * @phpstan-type PostingWarehouses array{dead_stock: Warehouse|null, returns_origin_id: int|null}
 * @phpstan-type ReviewRow array{item_id: int, reason_id: int|null, note: string|null, is_reviewed: bool, dead_stock_quantity: string|null, damaged_goods_quantity: string|null, full_price_quantity: string|null, damaged_goods_price: string|null, dead_stock_packs?: int|null, damaged_goods_packs?: int|null, full_price_packs?: int|null}
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

            $opname = StockOpname::query()->create([
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

            if ($opname->reason_code?->isReturnsCheck()) {
                $this->lockWarehouseClaims($opname);
                $this->addProductsWaitingInWarehouse($opname);
            }

            return $opname;
        });
    }

    /**
     * The inspection of a returns warehouse counts what waits there, so its products are the ones that have stock in it
     * and nobody picks them. Products another active opname of the warehouse already holds are left out.
     */
    private function addProductsWaitingInWarehouse(StockOpname $opname): void
    {
        $waitingQuantities = DB::table('stocks')->where('warehouse_id', $opname->warehouse_id)->where('quantity', '>', 0)->pluck('quantity', 'product_id');
        $freeProductIds = array_values(array_diff($waitingQuantities->keys()->map(fn ($productId): int => (int) $productId)->all(), $this->busyProductIds($opname, $waitingQuantities->keys()->map(fn ($productId): int => (int) $productId)->all())));

        foreach ($freeProductIds as $productId) {
            $opname->items()->create(['product_id' => $productId, 'snapshot_quantity' => (string) $waitingQuantities[$productId]]);
        }
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
                $skippedCount = $this->dropProductsHeldElsewhere($opname);
                $this->refreshSelectedSnapshots($opname);
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
            $reasonsById = OpnameReason::query()->whereIn('id', array_filter(array_column($counts, 'reason_id')))->get()->keyBy('id');

            foreach ($counts as $count) {
                $this->requireSellableWarehouseForDamage($opname, $reasonsById->get($count['reason_id']));

                $item = $itemsById->get($count['item_id']);

                $item?->forceFill([
                    'counted_quantity' => $this->toBaseQuantity($item->product, $count['parts']),
                    'counted_packs' => $this->toWholePacks($item->product, $count['parts']),
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

            $itemsById = $opname->items()->with(['product.productUnits', 'reason'])->whereIn('id', array_column($rows, 'item_id'))->get()->keyBy('id');
            $reasonsById = OpnameReason::query()->get()->keyBy('id');
            $systemQuantities = $this->currentQuantities($opname, $itemsById->pluck('product_id')->all());
            $systemPacks = $this->currentIntactPacks($opname, $itemsById->pluck('product_id')->all());

            foreach ($rows as $row) {
                $item = $itemsById->get($row['item_id']);

                if ($item === null) {
                    continue;
                }

                $reason = $row['reason_id'] === null ? null : $reasonsById->get($row['reason_id']);
                $this->requireSellableWarehouseForDamage($opname, $reason);

                $isDamaged = ($reason?->behavior->needsDamageDecision() ?? false) || $opname->reason_code?->isReturnsCheck() === true;
                $isDecidingPacks = $isDamaged && $item->product->sealedPackUnit() !== null;

                $item->forceFill([
                    'reason_id' => $reason?->id,
                    'note' => filled($row['note']) ? trim($row['note']) : null,
                    'dead_stock_quantity' => $isDamaged ? $row['dead_stock_quantity'] : null,
                    'damaged_goods_quantity' => $isDamaged ? $row['damaged_goods_quantity'] : null,
                    'full_price_quantity' => $isDamaged ? $row['full_price_quantity'] : null,
                    'damaged_goods_price' => $isDamaged ? $row['damaged_goods_price'] : null,
                    'dead_stock_packs' => $isDecidingPacks ? $row['dead_stock_packs'] ?? null : null,
                    'damaged_goods_packs' => $isDecidingPacks ? $row['damaged_goods_packs'] ?? null : null,
                    'full_price_packs' => $isDecidingPacks ? $row['full_price_packs'] ?? null : null,
                ])->setRelation('reason', $reason);

                $this->markReviewed($opname, $item, $row['is_reviewed'], $userId, $systemQuantities[$item->product_id] ?? '0', $systemPacks[$item->product_id] ?? 0);

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

            $countedItems = $opname->items()->with(['product.productUnits', 'reason'])->whereNotNull('counted_quantity')->orderBy('product_id')->get();
            $lockedStocks = $this->stockLedger->lockedStocks($countedItems->pluck('product_id')->all(), $opname->warehouse_id);
            $systemQuantities = array_map(fn (array $stock): string => $stock['quantity'], $lockedStocks);
            $systemPacks = array_map(fn (array $stock): int => $stock['intact_packs'], $lockedStocks);

            $this->requireNotesForLargeVariances($opname, $countedItems, $systemQuantities);
            $this->requireReviewedVariances($opname, $countedItems, $systemQuantities, $systemPacks);

            $warehouses = $this->postingWarehouses($opname);

            foreach ($countedItems as $item) {
                $systemQuantity = $systemQuantities[$item->product_id];
                $variance = $opname->varianceOf($item->counted_quantity, $systemQuantity);
                $packChange = $this->packChangeOf($opname, $item, $lockedStocks[$item->product_id]['intact_packs'], BigDecimal::of($systemQuantity)->plus($variance));

                $item->forceFill([
                    'system_quantity_at_posting' => $systemQuantity,
                    'system_packs_at_posting' => $item->product->sealedPackUnit() === null ? null : $lockedStocks[$item->product_id]['intact_packs'],
                    'cost_price_at_posting' => $item->product->cost_price,
                    'variance' => (string) $variance,
                ])->save();

                if ($opname->reason_code?->isReturnsCheck()) {
                    $this->postReturnsItem($opname, $item, $systemQuantity, $variance, $movementType, $userId, $lockedStocks[$item->product_id]['intact_packs'], $warehouses);

                    continue;
                }

                if ($variance->isZero() && $packChange === 0) {
                    continue;
                }

                if (! $variance->isZero() && $this->isDamagedGoodsDecision($opname, $item)) {
                    $this->postDamagedItem($opname, $item, $systemQuantity, $movementType, $userId, $packChange, $warehouses);

                    continue;
                }

                $this->stockLedger->record($item->product_id, $opname->warehouse_id, $movementType, (string) $variance, $opname, $userId, $this->movementNote($opname, $item), $systemQuantity, $packChange);
            }

            $opname->forceFill(['status' => StockOpnameStatus::Posted, 'approved_by' => $userId, 'posted_at' => now()])->save();
        }, StockService::DEADLOCK_ATTEMPTS);
    }

    /**
     * A variance worth more than the store's limit (the variance times the last cost price) needs a note on its row,
     * except in an opening-stock or arrival count ({@see StockOpnameReason::needsNoteForLargeVariance()}).
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

        if ($threshold === null || $opname->reason_code?->needsNoteForLargeVariance() === false) {
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
     * @param  array<int, int>  $systemPacks  live number of whole sealed packs by product id
     *
     * @throws StockOpnameException
     */
    private function requireReviewedVariances(StockOpname $opname, Collection $countedItems, array $systemQuantities, array $systemPacks): void
    {
        if (! $opname->reason_code?->requiresVarianceReview()) {
            return;
        }

        $problems = [];

        foreach ($countedItems as $item) {
            $variance = $opname->varianceOf($item->counted_quantity, $systemQuantities[$item->product_id]);
            $problem = $this->isReviewNeeded($opname, $variance) ? $this->reviewProblem($opname, $item, $variance, true, $systemPacks[$item->product_id]) : null;

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
     * @param  int  $systemPacks  the number of whole sealed packs on the shelf now
     *
     * @throws StockOpnameException
     */
    private function markReviewed(StockOpname $opname, StockOpnameItem $item, bool $isReviewed, int $userId, string $systemQuantity, int $systemPacks): void
    {
        if (! $isReviewed) {
            $item->forceFill(['reviewed_at' => null, 'reviewed_by' => null]);

            return;
        }

        if (! $item->isCounted()) {
            throw StockOpnameException::cannotReview($item->product->name, 'belum dihitung, jadi belum bisa ditandai sudah direview.');
        }

        $variance = $opname->varianceOf((string) $item->counted_quantity, $systemQuantity);
        $problem = $this->isReviewNeeded($opname, $variance) ? $this->reviewProblem($opname, $item, $variance, false, $systemPacks) : null;

        if ($problem !== null) {
            throw StockOpnameException::cannotReview($item->product->name, $problem);
        }

        $item->forceFill(['reviewed_at' => $item->reviewed_at ?? now(), 'reviewed_by' => $item->reviewed_by ?? $userId]);
    }

    /**
     * What is still missing on an item with a variance before it can be posted, in words for the admin, or null when it
     * is complete. The reason comes first (an inspection of returns is its own reason, so it is never asked); for a damaged reason the three parts of the decision must be filled and add up
     * to the shortage, and units that become a DG product need a price and a DG SKU that is free.
     *
     * @param  bool  $needsReviewMark  whether the admin's "reviewed" mark is also required
     * @param  int  $systemPacks  the number of whole sealed packs on the shelf now
     */
    private function reviewProblem(StockOpname $opname, StockOpnameItem $item, BigDecimal $variance, bool $needsReviewMark, int $systemPacks): ?string
    {
        $isReturnsCheck = $opname->reason_code?->isReturnsCheck() === true;

        if ($item->reason === null && ! $isReturnsCheck) {
            return 'alasan belum dipilih';
        }

        if (! $isReturnsCheck && $item->reason->behavior->needsDamageDecision() && ! $this->isSellableWarehouse($opname)) {
            return 'alasan Rusak hanya untuk opname gudang jual';
        }

        $problem = match (true) {
            $isReturnsCheck => $this->returnsDecisionProblem($item, $systemPacks),
            $item->reason->behavior->needsDamageDecision() => $this->damageDecisionProblem($item, $variance, $systemPacks),
            default => null,
        };

        if ($problem !== null) {
            return $problem;
        }

        return $needsReviewMark && ! $item->isReviewed() ? 'belum ditandai sudah direview' : null;
    }

    /**
     * An inspection of returns has to account for every unit counted: how many go back on the shelf, to dead stock and to a DG
     * product, adding up to what was counted. Nothing counted means nothing to send on.
     */
    private function returnsDecisionProblem(StockOpnameItem $item, int $systemPacks): ?string
    {
        $counted = BigDecimal::of((string) $item->counted_quantity);

        if ($counted->isZero()) {
            return null;
        }

        if ($item->dead_stock_quantity === null || $item->damaged_goods_quantity === null || $item->full_price_quantity === null) {
            return 'belum ditentukan ke mana barang pergi';
        }

        $deadStock = BigDecimal::of($item->dead_stock_quantity);
        $damagedGoods = BigDecimal::of($item->damaged_goods_quantity);

        if (! $deadStock->plus($damagedGoods)->plus($item->full_price_quantity)->isEqualTo($counted)) {
            return 'pembagian barang harus berjumlah sama dengan yang dihitung ('.rtrim(rtrim((string) $counted->toScale(3), '0'), '.').')';
        }

        $packsProblem = $this->decidedPacksProblem($item, $this->countedPacksInReturns($item, $systemPacks));

        if ($packsProblem !== null) {
            return $packsProblem;
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
     * A variance is always reviewed; an inspection of returns is reviewed even when the count matches, since it still has to
     * be decided where the goods go.
     */
    private function isReviewNeeded(StockOpname $opname, BigDecimal $variance): bool
    {
        return ! $variance->isZero() || $opname->reason_code?->isReturnsCheck() === true;
    }

    private function damageDecisionProblem(StockOpnameItem $item, BigDecimal $variance, int $systemPacks): ?string
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

        $packsProblem = $this->decidedPacksProblem($item, $this->packsLostIn($item, $systemPacks));

        if ($packsProblem !== null) {
            return $packsProblem;
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
     * A damaged-goods decision puts the DG product into the opname's own warehouse, which only sells from a sellable one.
     *
     * @throws StockOpnameException
     */
    private function requireSellableWarehouseForDamage(StockOpname $opname, ?OpnameReason $reason): void
    {
        if ($reason?->behavior->needsDamageDecision() === true && ! $this->isSellableWarehouse($opname)) {
            throw StockOpnameException::damageNeedsSellableWarehouse();
        }
    }

    private function isSellableWarehouse(StockOpname $opname): bool
    {
        return Warehouse::query()->whereKey($opname->warehouse_id)->where('type', WarehouseType::Sellable)->exists();
    }

    /**
     * Whether the item is a damaged-goods shortage whose decision splits where the units go (only routine opnames ask for it).
     */
    private function isDamagedGoodsDecision(StockOpname $opname, StockOpnameItem $item): bool
    {
        return $opname->reason_code?->requiresVarianceReview() === true && $item->reason?->behavior->needsDamageDecision() === true;
    }

    /**
     * Posts a damaged-goods shortage. Every damaged unit the count found missing first leaves the shelf of the opname's
     * warehouse in one movement, so the history shows all of them as checked; then they go where the admin decided: dead
     * stock (the same product in the dead stock warehouse), the DG product (made now when it does not exist), or back on the
     * shelf when they turned out fine to sell at full price, in a movement of their own. The shelf ends up with the count.
     *
     * @param  numeric-string  $systemQuantity  the locked stock of the item in the opname's warehouse
     * @param  int  $packChange  the change in whole sealed packs, taken in the first movement
     * @param  PostingWarehouses  $warehouses
     */
    private function postDamagedItem(StockOpname $opname, StockOpnameItem $item, string $systemQuantity, StockMovementType $movementType, int $userId, int $packChange, array $warehouses): void
    {
        $deadStock = BigDecimal::of($item->dead_stock_quantity);
        $damagedGoods = BigDecimal::of($item->damaged_goods_quantity);
        $fullPrice = BigDecimal::of($item->full_price_quantity);
        $shortage = $deadStock->plus($damagedGoods)->plus($fullPrice);
        $note = $this->movementNote($opname, $item);

        $this->stockLedger->record($item->product_id, $opname->warehouse_id, $movementType, (string) $shortage->negated(), $opname, $userId, $note, $systemQuantity, $packChange);

        if ($deadStock->isPositive()) {
            $this->stockLedger->record($item->product_id, $warehouses['dead_stock']->id, $movementType, (string) $deadStock, $opname, $userId, "{$note} (dead stock)", packChange: $this->packsDecidedFor($item, $item->dead_stock_packs));
        }

        if ($damagedGoods->isPositive()) {
            $twin = $this->damagedGoods->productFor($item->product, (string) $item->damaged_goods_price);

            $this->stockLedger->record($twin->id, $opname->warehouse_id, $movementType, (string) $damagedGoods, $opname, $userId, "{$note} (barang DG {$twin->sku})", packChange: $this->packsDecidedFor($item, $item->damaged_goods_packs));
        }

        if ($fullPrice->isPositive()) {
            $this->stockLedger->record($item->product_id, $opname->warehouse_id, $movementType, (string) $fullPrice, $opname, $userId, "{$note} (layak jual penuh, kembali ke stok jual)", packChange: $this->packsDecidedFor($item, $item->full_price_packs));
        }
    }

    /**
     * Posts the inspection of one product in a returns warehouse. What the count found different from the books is written
     * first (so the history shows a shortage or an extra), then everything counted leaves the returns warehouse and goes where
     * the admin decided: back on the shelf of the warehouse the goods were sold from, to the dead-stock warehouse, or to the
     * DG product (made now when it does not exist) in the warehouse they came from. The returns warehouse ends up empty of it.
     *
     * Whole sealed packs counted go back as whole packs on the shelf, as far as the quantity going back on the shelf holds them;
     * dead stock and DG units are loose.
     *
     * @param  numeric-string  $systemQuantity  the locked stock of the item in the returns warehouse
     * @param  int  $systemPacks  the locked number of whole packs in it
     * @param  PostingWarehouses  $warehouses
     *
     * @throws StockOpnameException
     */
    private function postReturnsItem(StockOpname $opname, StockOpnameItem $item, string $systemQuantity, BigDecimal $variance, StockMovementType $movementType, int $userId, int $systemPacks, array $warehouses): void
    {
        $origin = $warehouses['returns_origin_id'] ?? throw StockOpnameException::notAReturnsWarehouse();

        $counted = BigDecimal::of($item->counted_quantity);
        $deadStock = BigDecimal::of($item->dead_stock_quantity ?? '0');
        $damagedGoods = BigDecimal::of($item->damaged_goods_quantity ?? '0');
        $fullPrice = BigDecimal::of($item->full_price_quantity ?? '0');
        $note = $this->movementNote($opname, $item);

        $packUnit = $item->product->sealedPackUnit();
        $countedPacks = $this->countedPacksInReturns($item, $systemPacks);
        $deadStockPacks = $this->packsDecidedFor($item, $item->dead_stock_packs);
        $damagedGoodsPacks = $this->packsDecidedFor($item, $item->damaged_goods_packs);
        $packsOnShelf = $packUnit === null ? 0 : $item->full_price_packs ?? max(0, min($countedPacks - $deadStockPacks - $damagedGoodsPacks, $fullPrice->dividedBy($packUnit->conversion_factor, 0, RoundingMode::Down)->toInt()));
        $packVariance = $countedPacks - $systemPacks;

        if (! $variance->isZero() || $packVariance !== 0) {
            $this->stockLedger->record($item->product_id, $opname->warehouse_id, $movementType, (string) $variance, $opname, $userId, $note, $systemQuantity, $packVariance);
        }

        if ($counted->isPositive()) {
            $this->stockLedger->record($item->product_id, $opname->warehouse_id, $movementType, (string) $counted->negated(), $opname, $userId, "{$note} (diperiksa, dilepas dari gudang retur)", packChange: -$countedPacks);
        }

        if ($deadStock->isPositive()) {
            $this->stockLedger->record($item->product_id, $warehouses['dead_stock']->id, $movementType, (string) $deadStock, $opname, $userId, "{$note} (dead stock)", packChange: $deadStockPacks);
        }

        if ($damagedGoods->isPositive()) {
            $twin = $this->damagedGoods->productFor($item->product, (string) $item->damaged_goods_price);

            $this->stockLedger->record($twin->id, $origin, $movementType, (string) $damagedGoods, $opname, $userId, "{$note} (barang DG {$twin->sku})", packChange: $damagedGoodsPacks);
        }

        if ($fullPrice->isPositive()) {
            $this->stockLedger->record($item->product_id, $origin, $movementType, (string) $fullPrice, $opname, $userId, "{$note} (layak jual, kembali ke stok jual)", packChange: $packsOnShelf);
        }
    }

    /**
     * How many whole sealed packs the count in a returns warehouse holds: the packs counted, as far as the counted quantity
     * holds them. Zero for a product without a sealed pack unit.
     */
    private function countedPacksInReturns(StockOpnameItem $item, int $systemPacks): int
    {
        $packUnit = $item->product->sealedPackUnit();

        if ($packUnit === null) {
            return 0;
        }

        $fittingPacks = BigDecimal::of((string) $item->counted_quantity)->dividedBy($packUnit->conversion_factor, 0, RoundingMode::Down)->toInt();

        return min($item->counted_packs ?? $systemPacks, $fittingPacks);
    }

    /**
     * How many whole sealed packs a routine count found missing from the shelf: the ones the shelf had and the count did not.
     */
    private function packsLostIn(StockOpnameItem $item, int $systemPacks): int
    {
        return max(0, $systemPacks - $this->countedPacksInReturns($item, $systemPacks));
    }

    /**
     * Whether the whole packs the admin said go to dead stock, the DG product and the shelf fit in the quantity each of them
     * gets and do not come to more than there are to send on. Said in words for the admin, or null when they do.
     *
     * @param  int  $availablePacks  the whole packs the decision can divide
     */
    private function decidedPacksProblem(StockOpnameItem $item, int $availablePacks): ?string
    {
        $packUnit = $item->product->sealedPackUnit();

        if ($packUnit === null) {
            return null;
        }

        $totalPacks = 0;

        foreach ([[$item->dead_stock_packs, $item->dead_stock_quantity], [$item->damaged_goods_packs, $item->damaged_goods_quantity], [$item->full_price_packs, $item->full_price_quantity]] as [$packs, $quantity]) {
            $packs ??= 0;

            if ($packs > BigDecimal::of((string) $quantity)->dividedBy($packUnit->conversion_factor, 0, RoundingMode::Down)->toInt()) {
                return "jumlah {$packUnit->unit_code} utuh lebih banyak dari yang muat di jumlah barangnya";
            }

            $totalPacks += $packs;
        }

        return $totalPacks > $availablePacks ? "{$packUnit->unit_code} utuh yang dibagi ({$totalPacks}) lebih banyak dari yang ada ({$availablePacks})" : null;
    }

    /**
     * The whole packs the admin sent to one place, none for a product that has no sealed pack unit.
     */
    private function packsDecidedFor(StockOpnameItem $item, ?int $packs): int
    {
        return $item->product->sealedPackUnit() === null ? 0 : $packs ?? 0;
    }

    /**
     * The warehouses posting moves goods to, looked up once for the whole opname instead of once per item.
     *
     * @return PostingWarehouses
     */
    private function postingWarehouses(StockOpname $opname): array
    {
        return [
            'dead_stock' => $this->deadStockWarehouse(),
            'returns_origin_id' => $opname->reason_code?->isReturnsCheck() ? Warehouse::query()->findOrFail($opname->warehouse_id)->returns_for_warehouse_id : null,
        ];
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
     * How many of the counted parts were whole sealed packs (a count in the product's sealed pack unit), so a roll of wire
     * counted as 3 ROL and 100 MTR is 3 intact rolls and 100 loose metres. Null for a product without a sealed pack unit,
     * and for an item that was not counted.
     *
     * @param  list<CountPart>  $parts
     *
     * @throws StockOpnameException
     */
    private function toWholePacks(Product $product, array $parts): ?int
    {
        $packUnit = $product->sealedPackUnit();
        $filledParts = array_filter($parts, fn (array $part): bool => filled($part['quantity']));

        if ($packUnit === null || $filledParts === []) {
            return null;
        }

        $packs = BigDecimal::zero();

        foreach ($filledParts as $part) {
            if ($part['unit_code'] === $packUnit->unit_code) {
                $packs = $packs->plus($part['quantity']);
            }
        }

        if (! $packs->isEqualTo($packs->toScale(0, RoundingMode::Down))) {
            throw StockOpnameException::sealedPacksNotWhole($product->name, $packUnit->unit_code);
        }

        return $packs->toInt();
    }

    /**
     * The change in whole sealed packs that posting an item makes: the shelf ends up with the packs that were counted (or,
     * for a count of arrivals, gets them added). An item counted before its product had a sealed pack unit has no pack count,
     * so it keeps its packs as far as the new quantity still holds them.
     *
     * @param  int  $intactPacks  the locked number of whole packs on the shelf now
     * @param  BigDecimal  $newQuantity  the quantity the shelf holds once the variance is posted
     */
    private function packChangeOf(StockOpname $opname, StockOpnameItem $item, int $intactPacks, BigDecimal $newQuantity): int
    {
        $packUnit = $item->product->sealedPackUnit();

        if ($packUnit === null) {
            return 0;
        }

        $fittingPacks = $newQuantity->dividedBy($packUnit->conversion_factor, 0, RoundingMode::Down)->toInt();
        $targetPacks = match (true) {
            $item->counted_packs === null => $intactPacks,
            $opname->reason_code?->isArrivalCount() === true => $intactPacks + $item->counted_packs,
            default => $item->counted_packs,
        };

        return min($targetPacks, $fittingPacks) - $intactPacks;
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
     * The given products that an opname of the warehouse is counting right now (status Menghitung or Review).
     *
     * @param  list<int>  $productIds
     * @return list<int>
     */
    public function productIdsBeingCounted(int $warehouseId, array $productIds): array
    {
        return array_map('intval', StockOpnameItem::query()
            ->join('stock_opnames', 'stock_opnames.id', '=', 'stock_opname_items.stock_opname_id')
            ->where('stock_opnames.warehouse_id', $warehouseId)
            ->whereIn('stock_opnames.status', StockOpnameStatus::countingValues())
            ->whereNull('stock_opnames.deleted_at')
            ->whereIn('stock_opname_items.product_id', $productIds)
            ->distinct()
            ->pluck('stock_opname_items.product_id')
            ->all());
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
     * Products picked into a draft that another active opname of the warehouse holds by now (picked there first, or while
     * two requests raced) are taken out, so a product is never counted in two opnames at once.
     *
     * @return int how many products were taken out
     */
    private function dropProductsHeldElsewhere(StockOpname $opname): int
    {
        $heldElsewhereIds = $this->busyProductIds($opname, $opname->items()->pluck('product_id')->all());

        if ($heldElsewhereIds !== []) {
            $opname->items()->whereIn('product_id', $heldElsewhereIds)->delete();
        }

        return count($heldElsewhereIds);
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
     * @return array<int, int> whole sealed packs per product id; products without a stock row are absent
     */
    private function currentIntactPacks(StockOpname $opname, array $productIds): array
    {
        return DB::table('stocks')
            ->where('warehouse_id', $opname->warehouse_id)
            ->whereIn('product_id', $productIds)
            ->pluck('intact_packs', 'product_id')
            ->map(fn ($packs): int => (int) $packs)
            ->all();
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
