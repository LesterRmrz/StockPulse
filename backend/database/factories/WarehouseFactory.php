<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        $city = fake()->city();

        return [
            'code' => strtoupper(fake()->unique()->lexify('???')).'-01',
            'name' => $city.' Warehouse',
            'city' => $city,
            'address' => fake()->streetAddress(),
            'is_active' => true,
        ];
    }
}
