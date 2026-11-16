<?php

namespace App\Models;

use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. A person who delivers goods, chosen when a delivery note is shipped. Never deleted: one who
 * no longer delivers is switched off, so they are not offered any more.
 */
#[Fillable(['name', 'is_active'])]
class Driver extends Model implements AuditableContract
{
    /** @use HasFactory<DriverFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
