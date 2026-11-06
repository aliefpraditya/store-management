<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of the stock ledger: every change to `stocks` goes together with a `stock_movements` row.
 * All quantities are in the product's base unit; a product with a sealed pack unit also moves whole packs. Callers wrap it in their own transaction, because a stock
 * change is always part of a bigger document (an opname, a sale).
 */
class StockService
{
    /**
     * How often a transaction that locks stock rows is run when it loses a deadlock. A retry is safe because the
     * whole document rolls back first.
     */
    public const DEADLOCK_ATTEMPTS = 3;

    /**
     * Locks the stock row (creating it at zero) and returns its quantity; the lock lasts until the
     * surrounding transaction ends, so the quantity cannot change before {@see self::record()}.
     *
     * The row is locked first and only inserted when missing: an `insertOrIgnore` on an existing row takes a
     * shared lock, and two registers selling the same product would then deadlock upgrading it. Two writers
     * creating the same missing row can still deadlock on each other's gap lock, hence {@see self::DEADLOCK_ATTEMPTS}.
     */
    public function lockedQuantity(int $productId, int $warehouseId): string
    {
        $lockedQuantity = $this->lockStockRow($productId, $warehouseId);

        if ($lockedQuantity === null) {
            Stock::query()->insertOrIgnore([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => 0,
                'updated_at' => now(),
            ]);

            $lockedQuantity = $this->lockStockRow($productId, $warehouseId);
        }

        return (string) $lockedQuantity;
    }

    /**
     * {@see self::lockedQuantity()} for many products of one warehouse at once: one locking query for the rows that
     * exist, one insert for those that do not, and a second lock for the new ones. The rows are locked in product
     * order, the same order a sale locks them in, so the two do not deadlock on existing rows.
     *
     * @param  list<int>  $productIds
     * @return array<int, string> locked quantity by product id
     */
    public function lockedQuantities(array $productIds, int $warehouseId): array
    {
        return array_map(fn (array $stock): string => $stock['quantity'], $this->lockedStocks($productIds, $warehouseId));
    }

    /**
     * {@see self::lockedQuantities()} with the number of whole sealed packs of each row, read in the same locking query: a
     * second read would be a plain one, which can show an older version of the row than the lock does.
     *
     * @param  list<int>  $productIds
     * @return array<int, array{quantity: string, intact_packs: int}> by product id
     */
    public function lockedStocks(array $productIds, int $warehouseId): array
    {
        $lockedStocks = $this->lockStockRows($productIds, $warehouseId);
        $missingProductIds = array_values(array_diff($productIds, array_keys($lockedStocks)));

        if ($missingProductIds !== []) {
            Stock::query()->insertOrIgnore(array_map(fn (int $productId): array => [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => 0,
                'updated_at' => now(),
            ], $missingProductIds));

            $lockedStocks += $this->lockStockRows($missingProductIds, $warehouseId);
        }

        return $lockedStocks;
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, array{quantity: string, intact_packs: int}>
     */
    private function lockStockRows(array $productIds, int $warehouseId): array
    {
        return Stock::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $productIds)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get(['product_id', 'quantity', 'intact_packs'])
            ->mapWithKeys(fn (Stock $stock): array => [$stock->product_id => ['quantity' => (string) $stock->quantity, 'intact_packs' => (int) $stock->intact_packs]])
            ->all();
    }

    private function lockStockRow(int $productId, int $warehouseId): mixed
    {
        return Stock::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->value('quantity');
    }

    /**
     * Starts counting whole packs for a product whose sealed pack unit was just set, changed or removed: each stock row is
     * given as many packs as fit in its quantity (a guess that the next opname corrects), or none when the product no
     * longer has a sealed pack unit. Not a stock movement: the quantity does not change.
     */
    public function resetIntactPacks(Product $product): void
    {
        $packConversion = $product->sealedPackUnit()?->conversion_factor;

        Stock::query()->where('product_id', $product->id)->update([
            'intact_packs' => $packConversion === null ? 0 : DB::raw('floor(quantity / '.BigDecimal::of($packConversion).')'),
        ]);
    }

    /**
     * Adds a signed quantity to the stock and writes the ledger row with the balance after it.
     *
     * @param  string  $quantity  signed, positive adds stock
     * @param  int  $packChange  signed change in whole sealed packs, for a product that has a sealed pack unit; the column
     *                           is unsigned, so a change that would take the count below zero fails the write
     * @param  string|null  $lockedQuantity  the quantity when the caller already holds the row lock (see
     *                                       {@see self::lockedQuantities()}), so it is not locked and read again
     */
    public function record(
        int $productId,
        int $warehouseId,
        StockMovementType $type,
        string $quantity,
        ?Model $reference = null,
        ?int $userId = null,
        ?string $note = null,
        ?string $lockedQuantity = null,
        int $packChange = 0,
    ): StockMovement {
        $balanceAfter = BigDecimal::of($lockedQuantity ?? $this->lockedQuantity($productId, $warehouseId))->plus($quantity);

        $stockRow = Stock::query()->where('product_id', $productId)->where('warehouse_id', $warehouseId);

        $stockRow->update([
            'quantity' => (string) $balanceAfter,
            'intact_packs' => DB::raw('intact_packs + '.$packChange),
            'updated_at' => now(),
        ]);

        $packsAfter = (int) $stockRow->value('intact_packs');

        return StockMovement::query()->create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'moved_at' => now(),
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => (string) $balanceAfter,
            'pack_change' => $packChange,
            'packs_after' => $packsAfter,
            'reference_type' => $reference === null ? null : $this->referenceTypeOf($reference)->value,
            'reference_id' => $reference?->getKey(),
            'user_id' => $userId,
            'note' => $note,
        ]);
    }

    private function referenceTypeOf(Model $reference): StockReferenceType
    {
        foreach (StockReferenceType::cases() as $type) {
            if ($reference instanceof ($type->modelClass())) {
                return $type;
            }
        }

        throw new \InvalidArgumentException('Unsupported stock reference: '.$reference::class);
    }
}
