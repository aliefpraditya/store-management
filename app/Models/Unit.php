<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Lives in the store database. A unit is identified by its `code` (PCS, DUS, KG), which is what
 * `product_units.unit_code` and `products.base_unit_code` hold; the `name` (Pieces) is a plain description.
 * Every unit may have decimal quantities (up to 3 decimals).
 *
 * Not audited: the audit trail stores numeric model ids and a unit's key is text.
 */
#[Fillable(['code', 'name'])]
class Unit extends Model
{
    public const CODE_MAX_LENGTH = 20;

    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * One row per product that is sold in this unit (the base unit of a product counts too).
     *
     * @return HasMany<ProductUnit, $this>
     */
    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class, 'unit_code');
    }

    /**
     * A typed code the way codes are stored: upper case with single spaces.
     */
    public static function normalizeCode(string $code): string
    {
        return Str::upper(preg_replace('/\s+/', ' ', trim($code)));
    }
}
