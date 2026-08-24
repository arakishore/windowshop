<?php

namespace Database\Seeders;

use App\Services\Merchant\ShopSettingsInitializer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopSettingsSeeder extends Seeder
{
    /**
     * Seed default settings for every shop.
     */
    public function run(): void
    {
        $initializer = app(ShopSettingsInitializer::class);

        DB::table('shops')
            ->orderBy('id')
            ->pluck('id')
            ->chunk(100)
            ->each(fn ($shopIds) => $initializer->initializeMany($shopIds));
    }
}
