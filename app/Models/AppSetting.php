<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Lives in the central database: one application-wide setting as a key and a text value. Changes are audited.
 */
#[Fillable(['key', 'value'])]
class AppSetting extends Model implements AuditableContract
{
    use Auditable, CentralConnection;
}
