<?php

namespace Database\Factories;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft purchase without lines; its warehouse is the first one in the store.
 *
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_uuid' => fake()->uuid(),
            'number' => 'PB/TST/'.today()->format('ym').'/'.fake()->unique()->numerify('####'),
            'supplier_id' => Supplier::factory(),
            'supplier_document_number' => fake()->unique()->numerify('INV-#####'),
            'purchase_date' => today(),
            'expected_date' => null,
            'warehouse_id' => fn (): int => Warehouse::query()->orderBy('id')->value('id'),
            'status' => PurchaseStatus::Draft,
            'total_amount' => 0,
            'notes' => null,
            'created_by' => fake()->unique()->numberBetween(1000000, 9999999),
            'created_by_name' => fake()->firstName(),
        ];
    }

    public function ordered(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseStatus::Ordered,
            'confirmed_by' => fake()->numberBetween(1000000, 9999999),
            'confirmed_by_name' => fake()->firstName(),
            'confirmed_at' => now(),
        ]);
    }
}
