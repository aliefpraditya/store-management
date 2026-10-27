<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database.
 */
class Unit extends Model implements AuditableContract
{
    /** @use HasFactory<UnitFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['allows_decimal' => 'boolean'];
    }
}
