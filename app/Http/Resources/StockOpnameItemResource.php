<?php

namespace App\Http\Resources;

use App\Models\StockOpnameItem;
use App\Services\DamagedGoodsService;
use App\Services\StockOpnameReport;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One counted product. Expects `product.baseUnit`, `product.productUnits`, `reason` and the `system_quantity`,
 * `system_intact_packs`, `variance_quantity` and `product_cost_price` columns from {@see StockOpnameReport::paginateItems()}.
 *
 * @mixin StockOpnameItem
 */
class StockOpnameItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variance = $this->isCounted() ? BigDecimal::of($this->getAttribute('variance_quantity')) : null;
        $isReturnsCheck = $this->relationLoaded('stockOpname') && $this->stockOpname?->reason_code?->isReturnsCheck() === true;
        $isDamaged = $isReturnsCheck
            ? $this->isCounted() && BigDecimal::of((string) $this->counted_quantity)->isPositive()
            : $this->reason?->behavior->needsDamageDecision() ?? false;
        $isSealedPackProduct = $this->product->sealedPackUnit() !== null;

        return [
            'id' => $this->id,
            'product' => ['id' => $this->product->id, 'sku' => $this->product->sku, 'name' => $this->product->name],
            'base_unit' => $this->product->baseUnit->code,
            'units' => $this->product->productUnits->map(fn ($productUnit): array => [
                'code' => $productUnit->unit_code,
                'conversion_factor' => $productUnit->conversion_factor,
                'is_sealed_pack' => $productUnit->is_sealed_pack,
            ])->values()->all(),
            'system_quantity' => $this->getAttribute('system_quantity'),
            'system_intact_packs' => $isSealedPackProduct ? (int) $this->getAttribute('system_intact_packs') : null,
            'counted_quantity' => $this->counted_quantity,
            'counted_packs' => $this->counted_packs,
            'variance' => $variance === null ? null : (string) $variance,
            'variance_value' => $variance === null
                ? null
                : (string) $variance->multipliedBy($this->getAttribute('product_cost_price'))->toScale(2, RoundingMode::HalfUp),
            'reason_id' => $this->reason_id,
            'reason_name' => $this->reason?->name,
            'needs_damage_decision' => $isDamaged,
            'note' => $this->note,
            'is_reviewed' => $this->isReviewed(),
            'damage' => $isDamaged ? $this->damageDecision() : null,
            'remove_url' => route('tenant.stock-opnames.items.destroy', [
                'tenant' => tenant('code'),
                'stock_opname' => $this->stock_opname_id,
                'item' => $this->id,
            ]),
        ];
    }

    /**
     * Where the damaged units go, as decided so far (null for a part not decided yet), and what the admin needs to
     * decide it: whether the product can have a DG twin, the SKU that twin takes, the twin that already exists with the price
     * it has now, and the modal and selling price of the product itself (per base unit) to price the damaged units against.
     *
     * @return array<string, mixed>
     */
    private function damageDecision(): array
    {
        $damagedGoods = app(DamagedGoodsService::class);
        $baseUnit = $this->product->productUnits->firstWhere('is_base', true);

        return [
            'dead_stock_quantity' => $this->dead_stock_quantity,
            'damaged_goods_quantity' => $this->damaged_goods_quantity,
            'full_price_quantity' => $this->full_price_quantity,
            'damaged_goods_price' => $this->damaged_goods_price,
            'dead_stock_packs' => $this->dead_stock_packs,
            'damaged_goods_packs' => $this->damaged_goods_packs,
            'full_price_packs' => $this->full_price_packs,
            'existing' => $damagedGoods->existingOf($this->product),
            'damaged_goods_problem' => $damagedGoods->problemFor($this->product),
            'damaged_goods_sku' => $damagedGoods->skuFor($this->product),
            'original_cost_price' => $baseUnit?->cost_price,
            'original_selling_price' => $baseUnit?->selling_price,
        ];
    }
}
