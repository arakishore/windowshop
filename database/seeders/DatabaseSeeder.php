<?php

namespace Database\Seeders;

use Database\Seeders\DemoData\DemoSeeder;
use Database\Seeders\MasterData\SystemFoundationSeeder;
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
        $this->call(SystemFoundationSeeder::class);
        $this->call(SuperAdminSeeder::class);
        $this->call(AdminSettingsSeeder::class);

        // Uncomment to seed demo data in development only.
        $this->call(DemoSeeder::class);
        $this->call(MerchantSettingsSeeder::class);
    }
}
