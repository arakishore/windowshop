<?php

namespace Database\Seeders;

use App\Services\Merchant\MerchantSettingsInitializer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MerchantSettingsSeeder extends Seeder
{
    /**
     * Seed default settings for every merchant profile.
     */
    public function run(): void
    {
        $initializer = app(MerchantSettingsInitializer::class);

        DB::table('merchant_profiles')
            ->orderBy('id')
            ->pluck('id')
            ->chunk(100)
            ->each(fn ($merchantIds) => $initializer->initializeMany($merchantIds));
    }
}
