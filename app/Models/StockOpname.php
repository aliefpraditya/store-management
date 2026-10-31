<?php

namespace App\Models;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Services\StockOpnameReport;
use Brick\Math\BigDecimal;
use Database\Factories\StockOpnameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. A count of physical stock in one warehouse; posting it writes the variances
 * to the stock ledger. A cancelled opname is soft deleted. `created_by` and `approved_by` are central user ids (no foreign key across databases).
 */
#[Fillable(['number', 'warehouse_id', 'opname_date', 'scope', 'category_id', 'brand_id', 'status', 'snapshot_at', 'reason_code', 'created_by', 'approved_by', 'posted_at'])]
class StockOpname extends Model implements AuditableContract
{
    /** @use HasFactory<StockOpnameFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'opname_date' => 'date',
            'scope' => StockOpnameScope::class,
            'status' => StockOpnameStatus::class,
            'reason_code' => StockOpnameReason::class,
            'snapshot_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * Counted minus the system quantity, or just the counted quantity when the opname counts arrivals. The review
     * screen computes the same rule in SQL ({@see StockOpnameReport}); a test keeps the two equal.
     *
     * @param  numeric-string  $countedQuantity
     * @param  numeric-string  $systemQuantity
     */
    public function varianceOf(string $countedQuantity, string $systemQuantity): BigDecimal
    {
        return $this->reason_code?->countsArrivals()
            ? BigDecimal::of($countedQuantity)
            : BigDecimal::of($countedQuantity)->minus($systemQuantity);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<StockOpnameItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
