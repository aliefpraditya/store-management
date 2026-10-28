<?php

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database.
 */
#[Fillable(['name'])]
class Brand extends Model implements AuditableContract
{
    /** @use HasFactory<BrandFactory> */
    use Auditable, HasFactory;
}
