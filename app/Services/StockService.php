<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Models\Stock;
use App\Models\StockMovement;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;

/**
 * The only writer of the stock ledger: every change to `stocks` goes together with a `stock_movements` row.
 * All quantities are in the product's base unit. Callers wrap it in their own transaction, because a stock
 * change is always part of a bigger document (an opname, a sale).
 */
class StockService
{
    /**
     * Locks the stock row (creating it at zero) and returns its quantity; the lock lasts until the
     * surrounding transaction ends, so the quantity cannot change before {@see self::record()}.
     */
    public function lockedQuantity(int $productId, int $warehouseId): string
    {
        Stock::query()->insertOrIgnore([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'updated_at' => now(),
        ]);

        return (string) Stock::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->value('quantity');
    }

    /**
     * Adds a signed quantity to the stock and writes the ledger row with the balance after it.
     *
     * @param  string  $quantity  signed, positive adds stock
     */
    public function record(
        int $productId,
        int $warehouseId,
        StockMovementType $type,
        string $quantity,
        ?Model $reference = null,
        ?int $userId = null,
        ?string $note = null,
    ): StockMovement {
        $balanceAfter = BigDecimal::of($this->lockedQuantity($productId, $warehouseId))->plus($quantity);

        Stock::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->update(['quantity' => (string) $balanceAfter, 'updated_at' => now()]);

        return StockMovement::query()->create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'moved_at' => now(),
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => (string) $balanceAfter,
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
