<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->numerify('SKU#####'),
            'name' => fake()->unique()->words(3, true),
            'category_id' => Category::factory(),
            'brand_id' => null,
            'base_unit_code' => Unit::factory(),
            'cost_price' => 1000,
            'min_stock' => 0,
            'is_active' => true,
        ];
    }
}
