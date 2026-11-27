<?php

namespace App\Services;

use App\Exceptions\SaleException;
use App\Models\Product;
use App\Models\ProductUnit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

/**
 * Works out what a cart costs from the database: one entry per line with the price, cost and base quantity taken from
 * the product unit, never from the cashier's screen. Shared by receipts and customer orders so both price the same way.
 *
 * A line may carry a `unit_price` of its own, which only the server sets: a customer order keeps the price agreed when it
 * was made, and the receipt made when the goods are collected uses that price.
 *
 * A line sold in a decimal quantity (0.37 kg) is rounded up to the store's selling price step, so the amount on the
 * receipt, the order and every report is the one the customer pays. A whole quantity is never rounded: its price is what
 * the item form says. The screen works the same out in `useLineAmounts.js`; keep the two in step.
 *
 * @phpstan-type CartLine array{product_unit_id: int, quantity: numeric-string|float|int, unit_price?: numeric-string|null}
 * @phpstan-type PricedLine array{product: Product, product_id: int, product_name: string, base_unit_code: string, base_quantity: string, is_sealed_pack: bool, subtotal: BigDecimal, attributes: array<string, mixed>}
 */
class CartPricer
{
    public function __construct(private readonly StoreSettingService $settings) {}

    /**
     * @param  list<CartLine>  $items
     * @return Collection<int, PricedLine>
     *
     * @throws SaleException
     */
    public function price(array $items): Collection
    {
        $productUnitsById = ProductUnit::query()
            ->with(['product.productUnits', 'unit'])
            ->whereIn('id', array_column($items, 'product_unit_id'))
            ->get()
            ->keyBy('id');

        return collect($items)->map(function (array $cartLine) use ($productUnitsById): array {
            $productUnit = $productUnitsById->get($cartLine['product_unit_id']);
            $product = $productUnit?->product;

            if ($productUnit === null || $product === null) {
                throw SaleException::lineNotFound();
            }

            if ($product->trashed() || ! $product->is_active) {
                throw SaleException::productUnavailable($product->name);
            }

            if (! $productUnit->is_sellable) {
                throw SaleException::notSellable($product->name);
            }

            if (! $productUnit->is_conversion_confirmed) {
                throw SaleException::conversionNotConfirmed($product->name, $productUnit->unit_code);
            }

            $quantity = BigDecimal::of((string) $cartLine['quantity']);

            if ($productUnit->is_sealed_pack && ! $quantity->isEqualTo($quantity->toScale(0, RoundingMode::Down))) {
                throw SaleException::sealedPackNotWhole($product->name, $productUnit->unit_code);
            }

            $unitPrice = BigDecimal::of((string) ($cartLine['unit_price'] ?? $productUnit->selling_price))->toScale(2, RoundingMode::HalfUp);
            $baseQuantity = $quantity->multipliedBy($productUnit->conversion_factor)->toScale(3, RoundingMode::HalfUp);
            $subtotal = $this->subtotalOf($quantity, $unitPrice);

            return [
                'product' => $product,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'base_unit_code' => $product->base_unit_code,
                'base_quantity' => (string) $baseQuantity,
                'is_sealed_pack' => $productUnit->is_sealed_pack,
                'subtotal' => $subtotal,
                'attributes' => [
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnit->id,
                    'product_name' => $product->name,
                    'unit_name' => $productUnit->unit_code,
                    'quantity' => (string) $quantity,
                    'conversion_snapshot' => $productUnit->conversion_factor,
                    'is_sealed_pack' => $productUnit->is_sealed_pack,
                    'base_quantity' => (string) $baseQuantity,
                    'unit_price' => (string) $unitPrice,
                    'cost_total' => (string) $quantity->multipliedBy($productUnit->cost_price)->toScale(2, RoundingMode::HalfUp),
                    'is_cost_estimated' => false,
                    'is_manual_price' => ! $unitPrice->isEqualTo(BigDecimal::of((string) $productUnit->selling_price)),
                    'subtotal' => (string) $subtotal,
                ],
            ];
        });
    }

    /**
     * What a line costs: quantity times price to the sen, and, when the quantity is not a whole number, rounded up to a
     * multiple of the store's selling price step (no step, no rounding).
     */
    private function subtotalOf(BigDecimal $quantity, BigDecimal $unitPrice): BigDecimal
    {
        $subtotal = $quantity->multipliedBy($unitPrice)->toScale(2, RoundingMode::HalfUp);
        $step = $this->settings->sellingPriceStep();
        $isWholeQuantity = $quantity->isEqualTo($quantity->toScale(0, RoundingMode::Down));

        if ($step <= 0 || $isWholeQuantity) {
            return $subtotal;
        }

        return $subtotal->dividedBy($step, 0, RoundingMode::Ceiling)->multipliedBy($step)->toScale(2);
    }

    /**
     * @param  Collection<int, PricedLine>  $lines
     */
    public function totalOf(Collection $lines): BigDecimal
    {
        return $lines->reduce(fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['subtotal']), BigDecimal::zero());
    }
}
