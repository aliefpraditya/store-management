<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\CashShift;
use App\Models\Register;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_uuid' => fake()->uuid(),
            'number' => 'INV/'.fake()->unique()->numerify('######'),
            'register_id' => Register::factory(),
            'cash_shift_id' => CashShift::factory(),
            'warehouse_id' => Warehouse::factory(),
            'sold_at' => now(),
            'business_date' => today(),
            'cashier_id' => 1,
            'cashier_name' => fake()->firstName(),
            'subtotal' => 100000,
            'total' => 100000,
            'paid_total' => 100000,
            'status' => SaleStatus::Completed,
        ];
    }

    public function voided(): static
    {
        return $this->state(fn (): array => ['status' => SaleStatus::Voided]);
    }
}
