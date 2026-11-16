<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lives in the store database. One line of a receipt. The name, unit, conversion and price are copied at the
 * time of sale, so later edits to the product do not change old receipts. `base_quantity` is what left the stock.
 * `is_sealed_pack` says the line was sold in the product's sealed pack unit, so a void or return puts whole packs back.
 */
#[Fillable(['sale_id', 'product_id', 'product_unit_id', 'product_name', 'unit_name', 'quantity', 'conversion_snapshot', 'is_sealed_pack', 'base_quantity', 'unit_price', 'cost_total', 'is_cost_estimated', 'is_manual_price', 'subtotal'])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'conversion_snapshot' => 'decimal:3',
            'is_sealed_pack' => 'boolean',
            'base_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'cost_total' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'is_cost_estimated' => 'boolean',
            'is_manual_price' => 'boolean',
        ];
    }

    /**
     * How many whole sealed packs `$quantity` of this line's unit holds: what goes back into the intact packs when that much
     * is voided or returned. Zero for a line not sold in a sealed pack unit; anything beyond whole packs comes back loose.
     *
     * The line only counts as a pack while its unit is still the product's sealed pack unit with the same conversion: after
     * the pack unit was moved, released or re-converted, the stock row no longer counts packs of that size, and adding them
     * would make `intact_packs x conversion` exceed the quantity. Load `productUnit` first to avoid one query per line.
     */
    public function wholePacksIn(string $quantity): int
    {
        if (! $this->is_sealed_pack || ! $this->isStillSealedPackUnit()) {
            return 0;
        }

        return BigDecimal::of($quantity)->toScale(0, RoundingMode::Down)->toInt();
    }

    private function isStillSealedPackUnit(): bool
    {
        return $this->productUnit?->is_sealed_pack === true
            && BigDecimal::of((string) $this->productUnit->conversion_factor)->isEqualTo((string) $this->conversion_snapshot);
    }

    /**
     * @return BelongsTo<ProductUnit, $this>
     */
    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
