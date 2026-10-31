<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Services\StockHistoryService;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lives in the store database. The stock ledger: one signed row (base unit) per change, never edited or
 * deleted. `reference_type` is a short name such as `stock_opname`, see {@see StockHistoryService}.
 */
#[Fillable(['product_id', 'warehouse_id', 'moved_at', 'type', 'quantity', 'balance_after', 'reference_type', 'reference_id', 'user_id', 'note'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
            'type' => StockMovementType::class,
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
