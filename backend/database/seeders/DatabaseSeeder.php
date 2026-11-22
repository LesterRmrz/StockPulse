<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Builds the full demo dataset:  php artisan migrate:fresh --seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Fixed random seed: every run makes the same random choices, so the demo data
        // is reproducible (dates are relative to "now", so they shift with the clock).
        // Faker and mt_rand() share PHP's Mersenne Twister generator.
        fake()->seed(config('stockpulse.seed.random_seed'));

        $this->call([
            UserSeeder::class,
            WarehouseSeeder::class,
            CatalogSeeder::class,
            CustomerSeeder::class,
            InventoryHistorySeeder::class,
        ]);
    }
}
