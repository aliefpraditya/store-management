<?php

namespace App\Services;

use App\Enums\CostChangeMethod;
use App\Enums\SellingChangeMethod;
use App\Exceptions\PriceChangeSkippedException;
use App\Models\ProductUnit;
use App\Models\Unit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * What a bulk price change does to one unit of a product. Money is worked out exactly and rounded to the 2 decimals
 * it is stored with. The one other rounding is the store's selling price step: a selling price worked out from a percent
 * or a margin is rounded up to a multiple of it (`is_price_rounded`), and the plan then carries the percent the price
 * really rose by, since the typed percent no longer holds.
 *
 * A unit is either left as it is (outside the change, or nothing to change on it), changed, or skipped with the reason
 * it cannot be changed safely. When both prices change, the modal is changed first and a margin rule uses the new one.
 * A changed unit that ends with its selling price under its modal is flagged (`is_below_cost`): it is allowed, but the
 * admin confirms it before applying, as on the item form.
 *
 * @phpstan-type PriceChange array{unit_code: string|null, cost_method: CostChangeMethod|null, cost_value: numeric-string|null, selling_method: SellingChangeMethod|null, selling_value: numeric-string|null}
 * @phpstan-type UnitPricePlan array{unit_id: int, code: string, is_base: bool, old_cost: numeric-string, new_cost: numeric-string, old_price: numeric-string, new_price: numeric-string, is_changed: bool, is_below_cost: bool, skip_reason: string|null, is_price_rounded: bool, price_change_percent: numeric-string|null}
 */
class PriceChangeCalculator
{
    private const MONEY_SCALE = 2;

    /** The most a `decimal(15,2)` price column holds; a result above it would fail the save instead of being skipped. */
    private const MAX_MONEY = '9999999999999.99';

    /**
     * @param  PriceChange  $change
     * @param  int  $sellingPriceStep  the store's rounding step in rupiah; a selling price worked out from a percent or a
     *                                 margin is rounded up to a multiple of it, 0 leaves it as it is
     * @return UnitPricePlan
     */
    public function calculate(ProductUnit $unit, array $change, int $sellingPriceStep = StoreSettingService::DEFAULT_SELLING_PRICE_STEP): array
    {
        $oldCost = BigDecimal::of((string) $unit->cost_price);
        $oldPrice = BigDecimal::of((string) $unit->selling_price);

        if ($change['unit_code'] !== null && Unit::normalizeCode($unit->unit_code) !== Unit::normalizeCode($change['unit_code'])) {
            return $this->plan($unit, $oldCost, $oldPrice, $oldCost, $oldPrice, null);
        }

        try {
            $newCost = $change['cost_method'] === null
                ? $oldCost
                : $this->changedCost($oldCost, $change['cost_method'], BigDecimal::of($change['cost_value']));
        } catch (PriceChangeSkippedException $exception) {
            return $this->plan($unit, $oldCost, $oldPrice, $oldCost, $oldPrice, $exception->getMessage());
        }

        try {
            [$newPrice, $isPriceRounded] = $change['selling_method'] === null || ! $unit->is_sellable
                ? [$oldPrice, false]
                : $this->changedPrice($oldCost, $oldPrice, $newCost, $change['selling_method'], BigDecimal::of($change['selling_value'] ?? '0'), $sellingPriceStep);
        } catch (PriceChangeSkippedException $exception) {
            $costNote = $change['cost_method'] === null ? '' : ' Modal satuan ini juga tidak diubah.';

            return $this->plan($unit, $oldCost, $oldPrice, $oldCost, $oldPrice, $exception->getMessage().$costNote);
        }

        return $this->plan($unit, $oldCost, $oldPrice, $newCost, $newPrice, null, $isPriceRounded);
    }

    /**
     * @throws PriceChangeSkippedException
     */
    private function changedCost(BigDecimal $oldCost, CostChangeMethod $method, BigDecimal $value): BigDecimal
    {
        $newCost = match ($method) {
            CostChangeMethod::Percent => $this->scaledByPercent($oldCost, $value),
            CostChangeMethod::Nominal => $oldCost->plus($value)->toScale(self::MONEY_SCALE, RoundingMode::HalfUp),
            CostChangeMethod::Set => $value->toScale(self::MONEY_SCALE, RoundingMode::HalfUp),
        };

        if ($newCost->isNegative()) {
            throw PriceChangeSkippedException::negativeCost();
        }

        if ($newCost->isGreaterThan(self::MAX_MONEY)) {
            throw PriceChangeSkippedException::costOverLimit();
        }

        return $newCost;
    }

    /**
     * A price worked out from a percent or a margin is rounded up to the step, unless the rule left it where it was:
     * a change of 0% must not move a price that is not on the step. A price a rule would make negative is skipped as
     * it is, before rounding could hide that.
     *
     * @return array{0: BigDecimal, 1: bool} the new price, and whether rounding up to the step moved it from what the rule gave
     *
     * @throws PriceChangeSkippedException
     */
    private function changedPrice(BigDecimal $oldCost, BigDecimal $oldPrice, BigDecimal $newCost, SellingChangeMethod $method, BigDecimal $value, int $sellingPriceStep): array
    {
        $unroundedPrice = match ($method) {
            SellingChangeMethod::Percent => $this->scaledByPercent($oldPrice, $value),
            SellingChangeMethod::Nominal => $oldPrice->plus($value)->toScale(self::MONEY_SCALE, RoundingMode::HalfUp),
            SellingChangeMethod::Set => $value->toScale(self::MONEY_SCALE, RoundingMode::HalfUp),
            SellingChangeMethod::Margin => $this->priceFromMargin($newCost, $value),
            SellingChangeMethod::KeepMargin => $this->priceKeepingMargin($oldCost, $oldPrice, $newCost),
        };

        if ($unroundedPrice->isNegative()) {
            throw PriceChangeSkippedException::negativePrice();
        }

        $newPrice = $method->isDerivedFromRule() && ! $unroundedPrice->isEqualTo($oldPrice)
            ? $this->roundedUpToStep($unroundedPrice, $oldPrice, $sellingPriceStep)
            : $unroundedPrice;

        if ($newPrice->isGreaterThan(self::MAX_MONEY)) {
            throw PriceChangeSkippedException::priceOverLimit();
        }

        return [$newPrice, ! $newPrice->isEqualTo($unroundedPrice)];
    }

    /**
     * Rounding never turns a price drop into a rise: when the rule lowers the price, the rounded price stops at the old one.
     */
    private function roundedUpToStep(BigDecimal $price, BigDecimal $oldPrice, int $step): BigDecimal
    {
        if ($step <= 0) {
            return $price;
        }

        $roundedPrice = $price->dividedBy($step, 0, RoundingMode::Ceiling)->multipliedBy($step)->toScale(self::MONEY_SCALE);

        return $price->isLessThan($oldPrice) && $roundedPrice->isGreaterThan($oldPrice) ? $oldPrice : $roundedPrice;
    }

    private function scaledByPercent(BigDecimal $amount, BigDecimal $percent): BigDecimal
    {
        return $amount->multipliedBy(BigDecimal::of(100)->plus($percent))->dividedBy(100, self::MONEY_SCALE, RoundingMode::HalfUp);
    }

    /**
     * Margin is a share of the selling price, never of the modal: price = modal / (1 − margin%).
     *
     * @throws PriceChangeSkippedException
     */
    private function priceFromMargin(BigDecimal $cost, BigDecimal $marginPercent): BigDecimal
    {
        if ($cost->isZero()) {
            throw PriceChangeSkippedException::marginNeedsCost();
        }

        return $cost->multipliedBy(100)->dividedBy(BigDecimal::of(100)->minus($marginPercent), self::MONEY_SCALE, RoundingMode::HalfUp);
    }

    /**
     * The margin before is (price − modal) / price, so the price that holds it for the new modal is
     * new modal × old price / old modal.
     *
     * @throws PriceChangeSkippedException
     */
    private function priceKeepingMargin(BigDecimal $oldCost, BigDecimal $oldPrice, BigDecimal $newCost): BigDecimal
    {
        if ($oldCost->isZero() || $oldPrice->isZero()) {
            throw PriceChangeSkippedException::currentMarginUnknown();
        }

        return $newCost->multipliedBy($oldPrice)->dividedBy($oldCost, self::MONEY_SCALE, RoundingMode::HalfUp);
    }

    /**
     * What the price really rose by, in percent to 2 decimals: the percent the admin typed no longer holds once the
     * price was rounded up. Null when there is no old price to measure from.
     */
    private function priceChangePercent(BigDecimal $oldPrice, BigDecimal $newPrice): ?string
    {
        return $oldPrice->isPositive()
            ? (string) $newPrice->minus($oldPrice)->multipliedBy(100)->dividedBy($oldPrice, self::MONEY_SCALE, RoundingMode::HalfUp)
            : null;
    }

    /**
     * @return UnitPricePlan
     */
    private function plan(ProductUnit $unit, BigDecimal $oldCost, BigDecimal $oldPrice, BigDecimal $newCost, BigDecimal $newPrice, ?string $skipReason, bool $isPriceRounded = false): array
    {
        $oldCostText = (string) $oldCost->toScale(self::MONEY_SCALE);
        $oldPriceText = (string) $oldPrice->toScale(self::MONEY_SCALE);
        $newCostText = (string) $newCost->toScale(self::MONEY_SCALE);
        $newPriceText = (string) $newPrice->toScale(self::MONEY_SCALE);
        $isChanged = $skipReason === null && ($oldCostText !== $newCostText || $oldPriceText !== $newPriceText);

        return [
            'unit_id' => $unit->id,
            'code' => $unit->unit_code,
            'is_base' => $unit->is_base,
            'old_cost' => $oldCostText,
            'new_cost' => $newCostText,
            'old_price' => $oldPriceText,
            'new_price' => $newPriceText,
            'is_changed' => $isChanged,
            'is_below_cost' => $isChanged && $unit->is_sellable && $newPrice->isLessThan($newCost),
            'skip_reason' => $skipReason,
            'is_price_rounded' => $isPriceRounded,
            'price_change_percent' => $isPriceRounded ? $this->priceChangePercent($oldPrice, $newPrice) : null,
        ];
    }
}
