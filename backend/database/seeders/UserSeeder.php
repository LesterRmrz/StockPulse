<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One account per role. The manager and staff accounts are the public demo
 * logins (is_demo = true); the admin account is for you.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('stockpulse.seed.password');

        $accounts = [
            ['name' => 'Admin', 'email' => 'admin@stockpulse.test', 'role' => Role::Admin, 'is_demo' => false],
            ['name' => 'Demo Manager', 'email' => 'manager@stockpulse.test', 'role' => Role::Manager, 'is_demo' => true],
            ['name' => 'Demo Staff', 'email' => 'staff@stockpulse.test', 'role' => Role::Staff, 'is_demo' => true],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                $account + ['password' => $password, 'email_verified_at' => now()],
            );
        }
    }
}
