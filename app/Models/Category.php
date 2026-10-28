<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. Categories are flat in Phase 1.
 */
#[Fillable(['name'])]
class Category extends Model implements AuditableContract
{
    /** @use HasFactory<CategoryFactory> */
    use Auditable, HasFactory;
}
