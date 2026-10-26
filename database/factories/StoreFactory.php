<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();
        $code = Str::snake(Str::slug($name, '_'));

        return [
            'code' => $code,
            'receipt_prefix' => Str::upper(Str::substr($code, 0, 3)),
            'name' => $name,
            'address' => fake()->address(),
            'phone' => fake()->numerify('08##########'),
            'db_name' => 'store_'.$code,
            'db_username' => null,
            'db_password' => null,
            'db_host' => null,
            'status' => 'active',
        ];
    }

    public function provisioning(): static
    {
        return $this->state(fn (): array => ['status' => 'provisioning']);
    }
}
