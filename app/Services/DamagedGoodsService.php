<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Makes the damaged-goods (DG) twin of a product: a product of its own, named "(DG) name", with the SKU of the original
 * plus "-DG", so damaged units can be sold at their own price without touching the original's. It keeps the original's
 * category, brand, units and conversions and its modal; the admin names the selling price of the base unit and the other
 * units follow it times their conversion. A product has one twin: it is made the first time and used again after that.
 */
class DamagedGoodsService
{
    private const SKU_SUFFIX = '-DG';

    private const NAME_PREFIX = '(DG) ';

    public function skuFor(Product $product): string
    {
        return trim($product->sku).self::SKU_SUFFIX;
    }

    /**
     * The twin made from this product earlier, if any.
     */
    public function twinOf(Product $product): ?Product
    {
        return Product::query()->withTrashed()->where('damaged_from_product_id', $product->id)->first();
    }

    /**
     * Why this product cannot get a twin, in words for the admin, or null when it can: a DG product has none of its own,
     * and the SKU the twin would take must be free (or already be this product's own twin).
     */
    public function problemFor(Product $product): ?string
    {
        if ($product->is_damaged_goods) {
            return 'sudah barang DG, jadi tidak bisa dibuatkan DG lagi.';
        }

        $holder = Product::query()->withTrashed()->where('sku', $this->skuFor($product))->first();

        if ($holder !== null && $holder->damaged_from_product_id !== $product->id) {
            return "SKU {$this->skuFor($product)} sudah dipakai barang lain.";
        }

        return null;
    }

    /**
     * The twin made earlier as the admin should see it before pricing more damaged units into it: its name, its SKU and
     * the selling price of its base unit. Null while the product has no twin.
     *
     * @return array{name: string, sku: string, price: string|null}|null
     */
    public function existingOf(Product $product): ?array
    {
        $twin = $this->twinOf($product);

        if ($twin === null) {
            return null;
        }

        return ['name' => $twin->name, 'sku' => $twin->sku, 'price' => $twin->productUnits()->where('is_base', true)->value('selling_price')];
    }

    /**
     * The twin of the product, made now when it does not exist yet, with the given selling price on its base unit. When
     * it exists and the price is a different one, its sellable units are repriced.
     *
     * @param  numeric-string  $baseUnitPrice
     */
    public function productFor(Product $product, string $baseUnitPrice): Product
    {
        $twin = $this->twinOf($product) ?? $this->makeTwin($product);

        foreach ($twin->productUnits as $twinUnit) {
            $price = $twinUnit->is_sellable ? $this->priceOfUnit($twinUnit, $baseUnitPrice) : '0.00';

            if (BigDecimal::of((string) $twinUnit->selling_price)->compareTo($price) !== 0) {
                $twinUnit->forceFill(['selling_price' => $price])->save();
            }
        }

        return $twin;
    }

    private function makeTwin(Product $product): Product
    {
        $product->loadMissing('productUnits');

        $twin = Product::query()->create([
            'sku' => $this->skuFor($product),
            'name' => self::NAME_PREFIX.$product->name,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'base_unit_code' => $product->base_unit_code,
            'cost_price' => $product->cost_price,
            'min_stock' => 0,
            'is_active' => true,
            'is_damaged_goods' => true,
            'damaged_from_product_id' => $product->id,
        ]);
        $twin->forceFill(['version' => 1])->save();

        foreach ($product->productUnits as $unit) {
            ProductUnit::query()->create([
                'product_id' => $twin->id,
                'unit_code' => $unit->unit_code,
                'conversion_factor' => $unit->conversion_factor,
                'is_base' => $unit->is_base,
                'barcode' => null,
                'sort_order' => $unit->sort_order,
                'is_sellable' => $unit->is_sellable,
                'cost_price' => $unit->cost_price,
                'selling_price' => 0,
                'is_default_sale' => $unit->is_default_sale,
            ]);
        }

        return $twin->load('productUnits');
    }

    /**
     * @param  numeric-string  $baseUnitPrice
     */
    private function priceOfUnit(ProductUnit $unit, string $baseUnitPrice): string
    {
        return (string) BigDecimal::of($baseUnitPrice)->multipliedBy((string) $unit->conversion_factor)->toScale(2, RoundingMode::HalfUp);
    }
}
