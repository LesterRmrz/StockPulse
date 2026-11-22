<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    private const CITIES = [
        'Manila', 'Quezon City', 'Makati', 'Pasig', 'Taguig', 'Cebu City',
        'Mandaue', 'Lapu-Lapu', 'Davao City', 'Cagayan de Oro', 'Iloilo City',
        'Bacolod', 'Baguio', 'General Santos', 'Zamboanga City',
    ];

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // example.com/org/net are reserved domains, so no real person gets mail.
            'email' => fake()->unique()->safeEmail(),
            'city' => fake()->randomElement(self::CITIES),
            'country' => 'Philippines',
        ];
    }
}
