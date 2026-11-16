<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        return $this->problemGiven($product, Product::query()->withTrashed()->where('sku', $this->skuFor($product))->first());
    }

    /**
     * @param  Product|null  $skuHolder  the product (even a deleted one) that holds the SKU the twin would take
     */
    private function problemGiven(Product $product, ?Product $skuHolder): ?string
    {
        if ($product->is_damaged_goods) {
            return 'sudah barang DG, jadi tidak bisa dibuatkan DG lagi.';
        }

        if ($skuHolder !== null && $skuHolder->damaged_from_product_id !== $product->id) {
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
        return $this->existingGiven($this->twinOf($product));
    }

    /**
     * @return array{name: string, sku: string, price: string|null}|null
     */
    private function existingGiven(?Product $twin): ?array
    {
        if ($twin === null) {
            return null;
        }

        $twin->loadMissing('productUnits');

        return ['name' => $twin->name, 'sku' => $twin->sku, 'price' => $twin->productUnits->firstWhere('is_base', true)?->selling_price];
    }

    /**
     * {@see self::existingOf()} and {@see self::problemFor()} for many products in two queries, for a screen that lists
     * a page of items instead of asking per item.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, array{existing: array{name: string, sku: string, price: string|null}|null, problem: string|null}> by product id
     */
    public function decisionContextFor(Collection $products): array
    {
        $twinsByOriginalId = Product::query()->withTrashed()->with('productUnits')->whereIn('damaged_from_product_id', $products->pluck('id'))->get()->keyBy('damaged_from_product_id');
        // The database compares SKUs without regard to case, so the lookup does too.
        $holdersBySku = Product::query()->withTrashed()->whereIn('sku', $products->map(fn (Product $product): string => $this->skuFor($product)))->get()->keyBy(fn (Product $holder): string => Str::lower($holder->sku));

        return $products->mapWithKeys(fn (Product $product): array => [$product->id => [
            'existing' => $this->existingGiven($twinsByOriginalId->get($product->id)),
            'problem' => $this->problemGiven($product, $holdersBySku->get(Str::lower($this->skuFor($product)))),
        ]])->all();
    }

    /**
     * The twin of the product, made now when it does not exist yet, with the given selling price on its base unit. When
     * it exists and the price is a different one, its sellable units are repriced. The twin's modal follows the
     * original's every time units go in: R1 prices stock at the last purchase price (no layers per batch), so a later
     * batch does not come in at the modal the original had when the twin was made.
     *
     * @param  numeric-string  $baseUnitPrice
     */
    public function productFor(Product $product, string $baseUnitPrice): Product
    {
        $twin = $this->twinOf($product) ?? $this->makeTwin($product);

        $this->matchSealedPackUnit($product, $twin);
        $this->matchCostPrice($product, $twin);

        foreach ($twin->productUnits as $twinUnit) {
            $price = $twinUnit->is_sellable ? $this->priceOfUnit($twinUnit, $baseUnitPrice) : '0.00';

            if (BigDecimal::of((string) $twinUnit->selling_price)->compareTo($price) !== 0) {
                $twinUnit->forceFill(['selling_price' => $price])->save();
            }
        }

        return $twin;
    }

    /**
     * A roll sent to the DG product has to stay a roll there, so its roll unit is the original's. A twin made before rolls were
     * tracked is brought in line; its stock already there stays loose, because nothing says it was whole.
     */
    private function matchSealedPackUnit(Product $product, Product $twin): void
    {
        $product->loadMissing('productUnits');
        $isSealedPackByUnitCode = $product->productUnits->pluck('is_sealed_pack', 'unit_code');

        foreach ($twin->productUnits as $twinUnit) {
            $isSealedPack = (bool) ($isSealedPackByUnitCode[$twinUnit->unit_code] ?? false);

            if ($twinUnit->is_sealed_pack !== $isSealedPack) {
                $twinUnit->forceFill(['is_sealed_pack' => $isSealedPack])->save();
            }
        }
    }

    /**
     * The twin's modal, on the product and on every unit, is the original's.
     */
    private function matchCostPrice(Product $product, Product $twin): void
    {
        $product->loadMissing('productUnits');
        $costByUnitCode = $product->productUnits->pluck('cost_price', 'unit_code');

        if (BigDecimal::of((string) $twin->cost_price)->compareTo((string) $product->cost_price) !== 0) {
            $twin->forceFill(['cost_price' => $product->cost_price])->save();
        }

        foreach ($twin->productUnits as $twinUnit) {
            $cost = $costByUnitCode[$twinUnit->unit_code] ?? null;

            if ($cost !== null && BigDecimal::of((string) $twinUnit->cost_price)->compareTo((string) $cost) !== 0) {
                $twinUnit->forceFill(['cost_price' => $cost])->save();
            }
        }
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
                'sort_order' => $unit->sort_order,
                'is_sellable' => $unit->is_sellable,
                'cost_price' => $unit->cost_price,
                'selling_price' => 0,
                'is_default_sale' => $unit->is_default_sale,
                'is_sealed_pack' => $unit->is_sealed_pack,
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
