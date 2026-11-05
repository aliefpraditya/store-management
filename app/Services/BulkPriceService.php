<?php

namespace App\Services;

use App\Enums\ProductStatusFilter;
use App\Exceptions\BulkPriceException;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Changes the modal and the selling price of many items in one go. The admin picks the items (by ticking them or by
 * category and brand), looks at a preview, and applies. The apply works the numbers out again under the products' row
 * locks, so what is saved is never what a stale preview showed. Every unit is saved through its model, which is what
 * keeps the price history in the audit trail, and every changed product moves its save counter so an item form that
 * was open before is refused instead of silently overwriting the new prices.
 *
 * @phpstan-import-type PriceChange from PriceChangeCalculator
 * @phpstan-import-type UnitPricePlan from PriceChangeCalculator
 *
 * @phpstan-type ProductPlan array{id: int, sku: string, name: string, category: string, brand: string|null, units: list<UnitPricePlan>, is_changed: bool, has_skipped: bool}
 * @phpstan-type BulkPriceTotals array{products_selected: int, products_changed: int, units_changed: int, units_skipped: int}
 */
class BulkPriceService
{
    public const MAX_PRODUCTS = 2000;

    private const PREVIEW_PRODUCT_LIMIT = 100;

    public function __construct(private readonly PriceChangeCalculator $calculator) {}

    /**
     * The items the admin can pick from, narrowed like the item list plus a brand.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateCandidates(string $search, ?int $categoryId, ?int $brandId, ?ProductStatusFilter $status, int $perPage): LengthAwarePaginator
    {
        return $this->candidates($search, $categoryId, $brandId, $status)
            ->with(['category', 'brand', 'productUnits'])
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Every item the same narrowing matches, up to {@see self::MAX_PRODUCTS}, for "select all".
     *
     * @return list<int>
     */
    public function matchingIds(string $search, ?int $categoryId, ?int $brandId, ?ProductStatusFilter $status): array
    {
        return $this->candidates($search, $categoryId, $brandId, $status)->limit(self::MAX_PRODUCTS)->pluck('products.id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * What the change would do to the picked items, without saving anything. Only items that change or are skipped are
     * listed, and only the first few of them; the totals count all.
     *
     * @param  list<int>  $productIds
     * @param  PriceChange  $change
     * @return array{products: list<ProductPlan>, is_truncated: bool, totals: BulkPriceTotals}
     */
    public function preview(array $productIds, array $change): array
    {
        $plans = $this->plansFor($this->loadProducts($productIds, false), $change);
        $affected = $plans->filter(fn (array $plan): bool => $plan['is_changed'] || $plan['has_skipped']);

        return [
            'products' => $affected->take(self::PREVIEW_PRODUCT_LIMIT)->values()->all(),
            'is_truncated' => $affected->count() > self::PREVIEW_PRODUCT_LIMIT,
            'totals' => $this->totals($plans),
        ];
    }

    /**
     * @param  list<int>  $productIds
     * @param  PriceChange  $change
     * @return BulkPriceTotals
     *
     * @throws BulkPriceException
     */
    public function apply(array $productIds, array $change): array
    {
        return DB::transaction(function () use ($productIds, $change): array {
            $products = $this->loadProducts($productIds, true);
            $plans = $this->plansFor($products, $change);

            foreach ($products->keyBy('id') as $productId => $product) {
                $this->save($product, $plans->firstWhere('id', $productId));
            }

            return $this->totals($plans);
        });
    }

    /**
     * @return Builder<Product>
     */
    private function candidates(string $search, ?int $categoryId, ?int $brandId, ?ProductStatusFilter $status): Builder
    {
        return Product::query()
            ->search($search)
            ->when($categoryId, fn (Builder $query, int $categoryId) => $query->where('products.category_id', $categoryId))
            ->when($brandId, fn (Builder $query, int $brandId) => $query->where('products.brand_id', $brandId))
            ->when($status, fn (Builder $query, ProductStatusFilter $status) => $query->where('products.is_active', $status->isActive()))
            ->orderBy('products.name')
            ->orderBy('products.id');
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

        $product->forceFill(['version' => $product->version + 1])->save();
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
        ];
    }
}
