<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database: one setting of the store as a key and a text value. Changes are audited. The central
 * database has its own for the whole application ({@see AppSetting}).
 */
#[Fillable(['key', 'value'])]
class StoreSetting extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'settings';
}
