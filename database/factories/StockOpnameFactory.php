<?php

namespace Database\Factories;

use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Models\StockOpname;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOpname>
 */
class StockOpnameFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'OPN/TST/'.fake()->unique()->numerify('######'),
            'warehouse_id' => Warehouse::factory(),
            'opname_date' => now()->toDateString(),
            'scope' => StockOpnameScope::Selected,
            'category_id' => null,
            'status' => StockOpnameStatus::Draft,
            'snapshot_at' => null,
            'reason_code' => null,
            'created_by' => 1,
        ];
    }
}
