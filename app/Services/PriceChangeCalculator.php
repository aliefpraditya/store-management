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
 * it is stored with, without any other rounding (the shop prices to the sen).
 *
 * A unit is either left as it is (outside the change, or nothing to change on it), changed, or skipped with the reason
 * it cannot be changed safely. When both prices change, the modal is changed first and a margin rule uses the new one.
 *
 * @phpstan-type PriceChange array{unit_code: string|null, cost_method: CostChangeMethod|null, cost_value: numeric-string|null, selling_method: SellingChangeMethod|null, selling_value: numeric-string|null}
 * @phpstan-type UnitPricePlan array{unit_id: int, code: string, is_base: bool, old_cost: numeric-string, new_cost: numeric-string, old_price: numeric-string, new_price: numeric-string, is_changed: bool, skip_reason: string|null}
 */
class PriceChangeCalculator
{
    private const MONEY_SCALE = 2;

    /**
     * @param  PriceChange  $change
     * @return UnitPricePlan
     */
    public function calculate(ProductUnit $unit, array $change): array
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
            $newPrice = $change['selling_method'] === null || ! $unit->is_sellable
                ? $oldPrice
                : $this->changedPrice($oldCost, $oldPrice, $newCost, $change['selling_method'], BigDecimal::of($change['selling_value'] ?? '0'));
        } catch (PriceChangeSkippedException $exception) {
            return $this->plan($unit, $oldCost, $oldPrice, $oldCost, $oldPrice, $exception->getMessage());
        }

        return $this->plan($unit, $oldCost, $oldPrice, $newCost, $newPrice, null);
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

        return $newCost;
    }

    /**
     * @throws PriceChangeSkippedException
     */
    private function changedPrice(BigDecimal $oldCost, BigDecimal $oldPrice, BigDecimal $newCost, SellingChangeMethod $method, BigDecimal $value): BigDecimal
    {
        $newPrice = match ($method) {
            SellingChangeMethod::Percent => $this->scaledByPercent($oldPrice, $value),
            SellingChangeMethod::Nominal => $oldPrice->plus($value)->toScale(self::MONEY_SCALE, RoundingMode::HalfUp),
            SellingChangeMethod::Set => $value->toScale(self::MONEY_SCALE, RoundingMode::HalfUp),
            SellingChangeMethod::Margin => $this->priceFromMargin($newCost, $value),
            SellingChangeMethod::KeepMargin => $this->priceKeepingMargin($oldCost, $oldPrice, $newCost),
        };

        if ($newPrice->isNegative()) {
            throw PriceChangeSkippedException::negativePrice();
        }

        return $newPrice;
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
     * @return UnitPricePlan
     */
    private function plan(ProductUnit $unit, BigDecimal $oldCost, BigDecimal $oldPrice, BigDecimal $newCost, BigDecimal $newPrice, ?string $skipReason): array
    {
        $oldCostText = (string) $oldCost->toScale(self::MONEY_SCALE);
        $oldPriceText = (string) $oldPrice->toScale(self::MONEY_SCALE);
        $newCostText = (string) $newCost->toScale(self::MONEY_SCALE);
        $newPriceText = (string) $newPrice->toScale(self::MONEY_SCALE);

        return [
            'unit_id' => $unit->id,
            'code' => $unit->unit_code,
            'is_base' => $unit->is_base,
            'old_cost' => $oldCostText,
            'new_cost' => $newCostText,
            'old_price' => $oldPriceText,
            'new_price' => $newPriceText,
            'is_changed' => $skipReason === null && ($oldCostText !== $newCostText || $oldPriceText !== $newPriceText),
            'skip_reason' => $skipReason,
        ];
    }
}
