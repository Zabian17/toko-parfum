<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ProductTierSeeder::class,
            SettingsSeeder::class,
            DemoSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
