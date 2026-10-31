<?php

namespace App\Models;

use Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lives in the store database. One row per product and warehouse (composite key), a cache of the
 * stock_movements ledger.
 */
class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use HasFactory;

    public const CREATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = null;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
