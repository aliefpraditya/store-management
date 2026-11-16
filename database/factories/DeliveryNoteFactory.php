<?php

namespace Database\Factories;

use App\Enums\DeliveryNoteStatus;
use App\Models\DeliveryNote;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryNote>
 */
class DeliveryNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'SJ/'.fake()->unique()->numerify('######'),
            'sale_id' => Sale::factory(),
            'business_date' => today(),
            'status' => DeliveryNoteStatus::Draft,
            'recipient_name' => fake()->name(),
            'created_by' => 1,
            'created_by_name' => fake()->firstName(),
        ];
    }

    public function withStatus(DeliveryNoteStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
