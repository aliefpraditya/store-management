<?php

namespace Database\Factories;

use App\Enums\WarehouseType;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('W????')),
            'name' => fake()->unique()->words(2, true),
            'type' => WarehouseType::Sellable,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function deadStock(): static
    {
        return $this->state(fn (): array => ['type' => WarehouseType::DeadStock]);
    }
}
