<?php

namespace Database\Factories;

use App\Models\Carrier;
use App\Models\CarrierContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarrierContact>
 */
class CarrierContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carrier_id' => Carrier::factory(),
            'name' => fake()->name(),
            'phone' => fake()->numerify('08##########'),
        ];
    }
}
