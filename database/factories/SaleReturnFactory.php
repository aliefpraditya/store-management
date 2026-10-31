<?php

namespace Database\Factories;

use App\Enums\SaleReturnStatus;
use App\Models\CashShift;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturn>
 */
class SaleReturnFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'RET/'.fake()->unique()->numerify('######'),
            'sale_id' => Sale::factory(),
            'register_id' => Register::factory(),
            'cash_shift_id' => CashShift::factory(),
            'returned_at' => now(),
            'resolution' => 'refund',
            'refund_method' => 'cash',
            'credit_amount' => 50000,
            'refund_amount' => 50000,
            'status' => SaleReturnStatus::Pending,
            'requested_by' => 1,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => SaleReturnStatus::Completed]);
    }
}
