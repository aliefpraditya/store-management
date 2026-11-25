<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft shipment without notas or containers.
 *
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_uuid' => fake()->uuid(),
            'number' => 'PG/TST/'.today()->format('ym').'/'.fake()->unique()->numerify('####'),
            'carrier_id' => Carrier::factory(),
            'vessel_name' => null,
            'departure_date' => null,
            'status' => ShipmentStatus::Draft,
            'notes' => null,
            'created_by' => fake()->unique()->numberBetween(1000000, 9999999),
            'created_by_name' => fake()->firstName(),
        ];
    }
}
