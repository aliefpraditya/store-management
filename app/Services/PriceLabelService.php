<?php

namespace App\Services;

use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Prepares price labels for the items picked on the item list. A price belongs to a unit an item is sold in (a roll of wire
 * costs more than a metre of it), so a label is made per sellable unit, not per item.
 *
 * @phpstan-type PickableUnit array{id: int, product_id: int, name: string, sku: string, unit: string, price: string, is_base: bool}
 * @phpstan-type Label array{name: string, unit: string, price: string, copies: int}
 */
class PriceLabelService
{
    public const MAX_PRODUCTS = 200;

    public const MAX_COPIES_PER_UNIT = 500;

    /** Every label is drawn on the sheet, so this keeps the print window light. */
    public const MAX_LABELS = 500;

    /**
     * The sellable units of the picked items, for the window where the admin sets how many labels each gets. The base unit is
     * what is printed by default; the window offers the other units of an item as extras.
     *
     * @param  list<int>  $productIds
     * @return list<PickableUnit>
     */
    public function pickableUnits(array $productIds): array
    {
        return $this->sellableUnits()
            ->whereIn('product_units.product_id', $productIds)
            ->get()
            ->map(fn (ProductUnit $productUnit): array => [
                'id' => $productUnit->id,
                'product_id' => $productUnit->product_id,
                'name' => $productUnit->product->name,
                'sku' => $productUnit->product->sku,
                'unit' => $productUnit->unit_code,
                'price' => $productUnit->selling_price,
                'is_base' => $productUnit->is_base,
            ])
            ->all();
    }

    /**
     * What to print: one entry per unit asked for, with how many labels of it.
     *
     * @param  array<int, int>  $copiesByProductUnitId  labels wanted, by product unit id
     * @return list<Label>
     */
    public function labels(array $copiesByProductUnitId): array
    {
        return $this->sellableUnits()
            ->whereIn('product_units.id', array_keys($copiesByProductUnitId))
            ->get()
            ->map(fn (ProductUnit $productUnit): array => [
                'name' => $productUnit->product->name,
                'unit' => $productUnit->unit_code,
                'price' => $productUnit->selling_price,
                'copies' => $copiesByProductUnitId[$productUnit->id],
            ])
            ->all();
    }

    /**
     * The day printed on the labels, so a shopper can tell how fresh the price is.
     */
    public function printedOn(): string
    {
        return Carbon::today()->toDateString();
    }

    /**
     * Sellable units in the order of the item list (by name), the units of one item in the order it is set up with.
     *
     * @return Builder<ProductUnit>
     */
    private function sellableUnits(): Builder
    {
        return ProductUnit::query()
            ->select('product_units.*')
            ->join('products', 'products.id', '=', 'product_units.product_id')
            ->whereNull('products.deleted_at')
            ->where('product_units.is_sellable', true)
            ->with('product:id,sku,name')
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->orderBy('product_units.sort_order');
    }
}
