<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A line bought by the base unit (PCS), 10 at 5.000.
 *
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'product_id' => Product::factory(),
            'unit_code' => 'PCS',
            'conversion_factor' => 1,
            'quantity' => 10,
            'base_quantity' => 10,
            'unit_cost' => 5000,
            'base_unit_cost' => 5000,
            'subtotal' => 50000,
            'received_base_quantity' => 0,
            'notes' => null,
        ];
    }
}
