<?php

namespace App\Models;

use App\Enums\StockOpnameStatus;
use Database\Factories\StockOpnameItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lives in the store database. One product of an opname, all quantities in the base unit.
 * `counted_quantity` is null until counted: blank is not the same as zero. A variance gets a reason from the store's list and,
 * once an admin has looked at it, `reviewed_at` (`reviewed_by` is a central user id). For a damaged reason the admin splits
 * the damaged units into `dead_stock_quantity`, `damaged_goods_quantity` (sold on as a DG product at `damaged_goods_price`
 * per base unit) and `full_price_quantity` (fine after all, they stay on the shelf).
 */
#[Fillable(['stock_opname_id', 'product_id', 'snapshot_quantity', 'counted_quantity', 'system_quantity_at_posting', 'cost_price_at_posting', 'variance', 'reason_id', 'note', 'reviewed_at', 'reviewed_by', 'dead_stock_quantity', 'damaged_goods_quantity', 'full_price_quantity', 'damaged_goods_price'])]
class StockOpnameItem extends Model
{
    /** @use HasFactory<StockOpnameItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'system_quantity_at_posting' => 'decimal:3',
            'cost_price_at_posting' => 'decimal:2',
            'variance' => 'decimal:3',
            'reviewed_at' => 'datetime',
            'dead_stock_quantity' => 'decimal:3',
            'damaged_goods_quantity' => 'decimal:3',
            'full_price_quantity' => 'decimal:3',
            'damaged_goods_price' => 'decimal:2',
        ];
    }

    /**
     * The given products that an opname of the warehouse is counting right now (status Menghitung or Review).
     *
     * @param  list<int>  $productIds
     * @return list<int>
     */
    public static function productIdsBeingCounted(int $warehouseId, array $productIds): array
    {
        return array_map('intval', self::query()
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
     * @return BelongsTo<StockOpname, $this>
     */
    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<OpnameReason, $this>
     */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(OpnameReason::class, 'reason_id');
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }
}
