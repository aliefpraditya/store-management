<?php

namespace App\Models;

use App\Enums\WarehouseType;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. Every store has a sellable and a dead-stock warehouse, and for each sellable warehouse a
 * returns warehouse (`returns_for_warehouse_id`) where returned goods wait to be inspected.
 */
#[Fillable(['code', 'name', 'type', 'returns_for_warehouse_id', 'is_default', 'is_active'])]
class Warehouse extends Model implements AuditableContract
{
    /** @use HasFactory<WarehouseFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => WarehouseType::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The warehouse a returns warehouse sends its inspected goods back to; null for any other warehouse.
     *
     * @return BelongsTo<self, $this>
     */
    public function returnsForWarehouse(): BelongsTo
    {
        return $this->belongsTo(self::class, 'returns_for_warehouse_id');
    }

    /**
     * The returns warehouse that takes what comes back from the given sellable warehouse, or null when it has none.
     */
    public static function returnsFor(int $sellableWarehouseId): ?self
    {
        return self::query()->where('type', WarehouseType::Returns)->where('returns_for_warehouse_id', $sellableWarehouseId)->where('is_active', true)->first();
    }

    public function isReturns(): bool
    {
        return $this->type === WarehouseType::Returns;
    }
}
