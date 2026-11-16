<?php

namespace Database\Factories;

use App\Models\Register;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Register>
 */
class RegisterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('K????')),
            'name' => 'Kasir '.fake()->unique()->numberBetween(1, 9999),
            'warehouse_id' => Warehouse::factory(),
            'last_sale_sequence' => 0,
            'is_active' => true,
        ];
    }
}
