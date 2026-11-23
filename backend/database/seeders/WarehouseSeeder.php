<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            ['code' => 'MNL-01', 'name' => 'Manila Fulfillment Center', 'city' => 'Manila'],
            ['code' => 'CEB-01', 'name' => 'Cebu Distribution Hub', 'city' => 'Cebu'],
            ['code' => 'DVO-01', 'name' => 'Davao Warehouse', 'city' => 'Davao'],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::updateOrCreate(['code' => $warehouse['code']], $warehouse);
        }
    }
}
