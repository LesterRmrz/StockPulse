<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 2, 60);

        return [
            'sku' => 'SP-TST-'.fake()->unique()->numerify('####'),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'category_id' => Category::factory(),
            'default_supplier_id' => Supplier::factory(),
            'cost_price' => $cost,
            'sell_price' => round($cost * 1.8, 2),
            'is_active' => true,
        ];
    }
}
