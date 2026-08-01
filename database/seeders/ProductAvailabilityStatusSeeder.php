<?php

namespace Database\Seeders;

use App\Models\MerchantProfile;
use App\Services\ProductAvailability\MerchantAvailabilityStatusSeeder;
use Illuminate\Database\Seeder;

class ProductAvailabilityStatusSeeder extends Seeder
{
    public function run(): void
    {
        $seeder = app(MerchantAvailabilityStatusSeeder::class);

        MerchantProfile::query()
            ->eachById(fn (MerchantProfile $merchant): mixed => $seeder->seedDefaultsForMerchant($merchant));
    }
}
