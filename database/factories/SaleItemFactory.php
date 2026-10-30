<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'product_unit_id' => ProductUnit::factory(),
            'product_name' => fake()->words(2, true),
            'unit_name' => 'PCS',
            'quantity' => 1,
            'conversion_snapshot' => 1,
            'base_quantity' => 1,
            'unit_price' => 10000,
            'subtotal' => 10000,
        ];
    }
}
