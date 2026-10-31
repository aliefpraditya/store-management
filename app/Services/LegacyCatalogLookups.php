<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * The old POS tables the legacy product import reads once and looks items up in, so the per-item code does not
 * reach into an untyped array.
 */
final readonly class LegacyCatalogLookups
{
    /**
     * @param  Collection<int, string|null>  $unitNames  old unit name by old unit id
     * @param  Collection<int, string|null>  $brandNames  old brand name by old brand id
     * @param  Collection<int, string|null>  $categoryNames  old category (`jenis`) name by old id
     * @param  Collection<string, Collection<int, object>>  $priceRows  active price rows by old item id
     */
    public function __construct(
        public Collection $unitNames,
        public Collection $brandNames,
        public Collection $categoryNames,
        public Collection $priceRows,
    ) {}
}
