<?php

namespace App\Models;

use Database\Factories\StockOpnameItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lives in the store database. One product of an opname, all quantities in the base unit.
 * `counted_quantity` is null until counted: blank is not the same as zero.
 */
#[Fillable(['stock_opname_id', 'product_id', 'snapshot_quantity', 'counted_quantity', 'system_quantity_at_posting', 'variance', 'reason'])]
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
            'variance' => 'decimal:3',
        ];
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

    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }
}
