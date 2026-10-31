<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductUnit>
 */
class ProductUnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'unit_code' => Unit::factory(),
            'conversion_factor' => 1,
            'is_base' => true,
            'barcode' => null,
            'sort_order' => 0,
            'is_sellable' => true,
            'cost_price' => 1000,
            'selling_price' => 15000,
            'is_default_sale' => true,
        ];
    }
}
