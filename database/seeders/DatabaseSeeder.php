<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AuthRoleSeeder::class);
        $this->call(LocationMasterSeeder::class);
        $this->call(SystemFoundationSeeder::class);
        $this->call(SuperAdminSeeder::class);
        $this->call(MerchantDemoSeeder::class);
    }
}
