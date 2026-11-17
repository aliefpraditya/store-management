<?php

namespace App\Services;

use App\Exceptions\BulkPriceException;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Changes the modal and the selling price of many items in one go. The admin picks the items on the item list (by ticking them or by
 * category and brand), looks at a preview, and applies. The apply works the numbers out again under the products' row
 * locks and refuses to save when they are not the numbers of the preview (its fingerprint), so what is saved is always
 * what the admin saw. Every unit is saved through its model, which is what keeps the price history in the audit trail,
 * and every changed product moves its save counter so an item form that was open before is refused instead of
 * silently overwriting the new prices.
 *
 * @phpstan-import-type PriceChange from PriceChangeCalculator
 * @phpstan-import-type UnitPricePlan from PriceChangeCalculator
 *
 * @phpstan-type ProductPlan array{id: int, sku: string, name: string, category: string, brand: string|null, units: list<UnitPricePlan>, is_changed: bool, has_skipped: bool}
 * @phpstan-type BulkPriceTotals array{products_selected: int, products_changed: int, units_changed: int, units_skipped: int, units_below_cost: int}
 */
class BulkPriceService
{
    /** Measured at about 15 ms per item with the audit trail on (200 items took 3 s), so this stays within a web request's time limit. */
    public const MAX_PRODUCTS = 500;

    private const PREVIEW_PRODUCT_LIMIT = 100;

    public function __construct(private readonly PriceChangeCalculator $calculator) {}

    /**
     * The units the picked items come in, with how many of the items have each, for choosing which unit a change is
     * limited to. The most common unit comes first.
     *
     * @param  list<int>  $productIds
     * @return list<array{code: string, products_count: int}>
     */
    public function unitsOf(array $productIds): array
    {
        return ProductUnit::query()
            ->whereIn('product_id', $productIds)
            ->selectRaw('unit_code, count(distinct product_id) as products_count')
            ->groupBy('unit_code')
            ->orderByDesc('products_count')
            ->orderBy('unit_code')
            ->get()
            ->map(fn (ProductUnit $productUnit): array => [
                'code' => $productUnit->unit_code,
                'products_count' => (int) $productUnit->getAttribute('products_count'),
            ])
            ->all();
    }

    /**
     * What the change would do to the picked items, without saving anything. Only items that change or are skipped are
     * listed, and only the first few of them; the totals count all. The fingerprint covers every picked item, listed
     * or not.
     *
     * @param  list<int>  $productIds
     * @param  PriceChange  $change
     * @return array{products: list<ProductPlan>, is_truncated: bool, totals: BulkPriceTotals, fingerprint: string}
     */
    public function preview(array $productIds, array $change): array
    {
        $plans = $this->plansFor($this->loadProducts($productIds, false), $change);
        $affected = $plans->filter(fn (array $plan): bool => $plan['is_changed'] || $plan['has_skipped']);

        return [
            'products' => $affected->take(self::PREVIEW_PRODUCT_LIMIT)->values()->all(),
            'is_truncated' => $affected->count() > self::PREVIEW_PRODUCT_LIMIT,
            'totals' => $this->totals($plans),
            'fingerprint' => $this->fingerprint($plans),
        ];
    }

    /**
     * @param  list<int>  $productIds
     * @param  PriceChange  $change
     * @param  string  $previewFingerprint  the fingerprint of the preview the admin looked at
     * @return BulkPriceTotals
     *
     * @throws BulkPriceException
     */
    public function apply(array $productIds, array $change, string $previewFingerprint): array
    {
        return DB::transaction(function () use ($productIds, $change, $previewFingerprint): array {
            $products = $this->loadProducts($productIds, true);
            $plans = $this->plansFor($products, $change);

            if (! hash_equals($previewFingerprint, $this->fingerprint($plans))) {
                throw BulkPriceException::previewOutdated();
            }

            $plansById = $plans->keyBy('id');

            foreach ($products as $product) {
                $this->save($product, $plansById[$product->id]);
            }

            return $this->totals($plans);
        });
    }

    /**
     * Identifies what the change does to every picked unit: it differs as soon as someone changes a price of a picked
     * item after the preview was made.
     *
     * @param  Collection<int, ProductPlan>  $plans
     */
    private function fingerprint(Collection $plans): string
    {
        return hash('sha256', $plans->map(fn (array $plan): array => ['id' => $plan['id'], 'units' => $plan['units']])->toJson());
    }

    /**
     * @param  list<int>  $productIds
     * @return Collection<int, Product>
     *
     * @throws BulkPriceException
     */
    private function loadProducts(array $productIds, bool $isLocked): Collection
    {
        $productIds = array_values(array_unique($productIds));

        $products = Product::query()
            ->with(['category', 'brand', 'productUnits'])
            ->whereIn('products.id', $productIds)
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->when($isLocked, fn (Builder $query) => $query->lockForUpdate())
            ->get();

        if ($products->count() !== count($productIds)) {
            throw BulkPriceException::productsMissing();
        }

        return $products;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  PriceChange  $change
     * @return Collection<int, ProductPlan>
     */
    private function plansFor(Collection $products, array $change): Collection
    {
        return $products->map(function (Product $product) use ($change): array {
            $unitPlans = $product->productUnits->map(fn (ProductUnit $unit): array => $this->calculator->calculate($unit, $change))->values()->all();

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->category->name,
                'brand' => $product->brand?->name,
                'units' => $unitPlans,
                'is_changed' => collect($unitPlans)->contains('is_changed', true),
                'has_skipped' => collect($unitPlans)->contains(fn (array $unitPlan): bool => $unitPlan['skip_reason'] !== null),
            ];
        })->values();
    }

    /**
     * @param  array<string, mixed>  $plan  {@see self::plansFor()}
     */
    private function save(Product $product, array $plan): void
    {
        if (! $plan['is_changed']) {
            return;
        }

        foreach ($plan['units'] as $unitPlan) {
            if (! $unitPlan['is_changed']) {
                continue;
            }

            $product->productUnits->firstWhere('id', $unitPlan['unit_id'])
                ->fill(['cost_price' => $unitPlan['new_cost'], 'selling_price' => $unitPlan['new_price']])
                ->save();

            if ($unitPlan['is_base']) {
                $product->cost_price = $unitPlan['new_cost'];
            }
        }

        $product->version++;

        if ($this->isOnlyVersionDirty($product)) {
            Product::withoutAuditing(fn (): bool => $product->save());

            return;
        }

        $product->save();
    }

    /**
     * Moving the save counter alone (the base unit was not touched) has nothing to audit: the counter is excluded
     * from the trail, so the entry would be empty.
     */
    private function isOnlyVersionDirty(Product $product): bool
    {
        return array_keys($product->getDirty()) === ['version'];
    }

    /**
     * @param  Collection<int, ProductPlan>  $plans
     * @return BulkPriceTotals
     */
    private function totals(Collection $plans): array
    {
        $unitPlans = $plans->pluck('units')->flatten(1);

        return [
            'products_selected' => $plans->count(),
            'products_changed' => $plans->where('is_changed', true)->count(),
            'units_changed' => $unitPlans->where('is_changed', true)->count(),
            'units_skipped' => $unitPlans->filter(fn (array $unitPlan): bool => $unitPlan['skip_reason'] !== null)->count(),
            'units_below_cost' => $unitPlans->where('is_below_cost', true)->count(),
        ];
    }
}
