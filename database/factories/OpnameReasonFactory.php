<?php

namespace Database\Factories;

use App\Enums\OpnameReasonBehavior;
use App\Models\OpnameReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpnameReason>
 */
class OpnameReasonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'behavior' => OpnameReasonBehavior::Plain,
            'is_system' => false,
            'is_active' => true,
        ];
    }
}
