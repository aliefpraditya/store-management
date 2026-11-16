<?php

namespace Database\Factories;

use App\Enums\ExpenseSource;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An expense paid from a source other than the drawer, so it needs no shift.
 *
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_uuid' => fake()->uuid(),
            'number' => 'PGL/TST/'.today()->format('ym').'/'.fake()->unique()->numerify('####'),
            'business_date' => today(),
            'cash_shift_id' => null,
            'expense_category_id' => ExpenseCategory::factory(),
            'source' => ExpenseSource::Other,
            'description' => fake()->sentence(3),
            'paid_to' => fake()->company(),
            'amount' => 50000,
            'status' => ExpenseStatus::Recorded,
            'created_by' => fake()->unique()->numberBetween(1000000, 9999999),
            'created_by_name' => fake()->firstName(),
        ];
    }
}
