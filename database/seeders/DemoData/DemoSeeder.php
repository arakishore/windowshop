<?php

namespace Database\Seeders\DemoData;

use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Run all WindowShop demo seeders.
     */
    public function run(): void
    {
        $this->call(DemoMerchantSeeder::class);
        $this->call(DemoShopSeeder::class);
        $this->call(DemoProductSeeder::class);
        $this->call(DemoCustomerSeeder::class);
        $this->call(DemoOrderSeeder::class);
    }
}
