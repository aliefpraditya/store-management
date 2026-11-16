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
 * @phpstan-type CartLine array{product_unit_id: int, quantity: numeric-string|float|int, unit_price?: numeric-string|null}
 * @phpstan-type PricedLine array{product: Product, product_id: int, product_name: string, base_unit_code: string, base_quantity: string, is_sealed_pack: bool, subtotal: BigDecimal, attributes: array<string, mixed>}
 */
class CartPricer
{
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

            $quantity = BigDecimal::of((string) $cartLine['quantity']);

            if ($productUnit->is_sealed_pack && ! $quantity->isEqualTo($quantity->toScale(0, RoundingMode::Down))) {
                throw SaleException::sealedPackNotWhole($product->name, $productUnit->unit_code);
            }

            $unitPrice = BigDecimal::of((string) ($cartLine['unit_price'] ?? $productUnit->selling_price))->toScale(2, RoundingMode::HalfUp);
            $baseQuantity = $quantity->multipliedBy($productUnit->conversion_factor)->toScale(3, RoundingMode::HalfUp);
            $subtotal = $quantity->multipliedBy($unitPrice)->toScale(2, RoundingMode::HalfUp);

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
     * @param  Collection<int, PricedLine>  $lines
     */
    public function totalOf(Collection $lines): BigDecimal
    {
        return $lines->reduce(fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['subtotal']), BigDecimal::zero());
    }
}
