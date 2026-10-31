<?php

namespace App\Models;

use App\Enums\WarehouseType;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. Every store has a sellable and a dead-stock warehouse.
 */
#[Fillable(['code', 'name', 'type', 'is_default', 'is_active'])]
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
}
