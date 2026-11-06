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
#[Fillable(['customer_order_id', 'product_id', 'product_unit_id', 'product_name', 'unit_name', 'quantity', 'conversion_snapshot', 'base_quantity', 'unit_price', 'subtotal'])]
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

        return self::query()
            ->whereHas('customerOrder', fn (Builder $orders) => $orders->where('status', 'ready')->where('warehouse_id', $warehouseId)->when($exceptOrderId, fn (Builder $query) => $query->whereKeyNot($exceptOrderId)))
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->selectRaw('product_id, sum(base_quantity) as reserved')
            ->pluck('reserved', 'product_id')
            ->map(fn (mixed $reserved): string => (string) $reserved)
            ->all();
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
