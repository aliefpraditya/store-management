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
            'sort_order' => 0,
            'is_sellable' => true,
            'cost_price' => 1000,
            'selling_price' => 15000,
            'is_default_sale' => true,
            'is_sealed_pack' => false,
            'is_conversion_confirmed' => true,
        ];
    }

    /**
     * A unit brought over from the old system whose conversion nobody checked yet.
     */
    public function unconfirmed(): static
    {
        return $this->state(['is_base' => false, 'is_default_sale' => false, 'is_conversion_confirmed' => false]);
    }
}
