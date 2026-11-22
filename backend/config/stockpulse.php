<?php

/*
 * StockPulse-specific settings. Read with config('stockpulse.seed.history_days') etc.
 */
return [

    'seed' => [
        // How many days of past orders the demo seeder generates.
        'history_days' => (int) env('SEED_HISTORY_DAYS', 90),

        // Orders per day at the start and end of the history (sales grow over time).
        'orders_per_day_start' => (int) env('SEED_ORDERS_PER_DAY_START', 110),
        'orders_per_day_end' => (int) env('SEED_ORDERS_PER_DAY_END', 190),

        'customers' => (int) env('SEED_CUSTOMERS', 500),

        // Same seed = same demo data every time, which makes bugs reproducible.
        'random_seed' => (int) env('SEED_RANDOM_SEED', 2026),

        // Password for the seeded accounts. Change it before deploying anywhere public.
        'password' => env('SEED_USER_PASSWORD', 'password'),
    ],

];
