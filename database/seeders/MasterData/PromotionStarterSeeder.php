<?php

namespace Database\Seeders\MasterData;

use App\Services\Promotion\ShopPromotionStarterService;
use Illuminate\Database\Seeder;

class PromotionStarterSeeder extends Seeder
{
    public function run(): void
    {
        app(ShopPromotionStarterService::class)->createMissingSystemStartersForAllShops();
    }
}
