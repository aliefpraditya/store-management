<?php

namespace Database\Factories;

use App\Enums\CashShiftStatus;
use App\Enums\ShiftDifferenceStatus;
use App\Models\CashShift;
use App\Models\Register;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashShift>
 */
class CashShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_uuid' => fake()->uuid(),
            'register_id' => Register::factory(),
            'business_date' => today(),
            'cashier_id' => 1,
            'cashier_name' => fake()->firstName(),
            'opened_at' => now(),
            'opening_cash' => 500000,
            'status' => CashShiftStatus::Open,
            'difference_status' => ShiftDifferenceStatus::None,
        ];
    }

    public function closedWithPendingDifference(float $difference = -10000): static
    {
        return $this->state(fn (): array => [
            'status' => CashShiftStatus::Closed,
            'closed_at' => now(),
            'cash_difference' => $difference,
            'difference_status' => ShiftDifferenceStatus::PendingApproval,
        ]);
    }
}
