<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lives in the store database. One line of a customer order. The name, unit, conversion and price are copied when the
 * order is made: the price stays what the customer agreed to, even if the list price changes before they collect.
 */
#[Fillable(['customer_order_id', 'product_id', 'product_unit_id', 'product_name', 'unit_name', 'quantity', 'conversion_snapshot', 'is_sealed_pack', 'base_quantity', 'unit_price', 'subtotal'])]
class CustomerOrderItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'conversion_snapshot' => 'decimal:3',
            'is_sealed_pack' => 'boolean',
            'base_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * How much of each product is set aside for customers whose goods have arrived, in the base unit. A sale of
     * something else must not take these goods. Pass the order being collected as `$exceptOrderId` so it can take its own.
     *
     * @param  list<int>  $productIds
     * @return array<int, numeric-string> base quantity by product id, only for products that have something set aside
     */
    public static function reservedQuantities(int $warehouseId, array $productIds, ?int $exceptOrderId = null): array
    {
        if ($productIds === []) {
            return [];
        }

        return self::readyItemsQuery($warehouseId, $productIds, $exceptOrderId)
            ->selectRaw('product_id, sum(base_quantity) as reserved')
            ->pluck('reserved', 'product_id')
            ->map(fn (mixed $reserved): string => (string) $reserved)
            ->all();
    }

    /**
     * How many whole sealed packs of each product are set aside, for the lines of ready orders that ask for a pack unit
     * (a roll of wire). They are part of {@see self::reservedQuantities()} too; a sale of loose pieces may not open them.
     *
     * @param  list<int>  $productIds
     * @return array<int, int> whole packs by product id, only for products that have packs set aside
     */
    public static function reservedPacks(int $warehouseId, array $productIds, ?int $exceptOrderId = null): array
    {
        if ($productIds === []) {
            return [];
        }

        return self::readyItemsQuery($warehouseId, $productIds, $exceptOrderId)
            ->where('is_sealed_pack', true)
            ->selectRaw('product_id, sum(quantity) as reserved')
            ->pluck('reserved', 'product_id')
            ->map(fn (mixed $reserved): int => (int) $reserved)
            ->all();
    }

    /**
     * @param  list<int>  $productIds
     * @return Builder<self>
     */
    private static function readyItemsQuery(int $warehouseId, array $productIds, ?int $exceptOrderId): Builder
    {
        return self::query()
            ->whereHas('customerOrder', fn (Builder $orders) => $orders->where('status', 'ready')->where('warehouse_id', $warehouseId)->when($exceptOrderId, fn (Builder $query) => $query->whereKeyNot($exceptOrderId)))
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id');
    }

    /**
     * @return BelongsTo<CustomerOrder, $this>
     */
    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
