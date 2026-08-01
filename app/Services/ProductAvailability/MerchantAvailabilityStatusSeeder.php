<?php

namespace App\Services\ProductAvailability;

use App\Models\MerchantProfile;
use App\Models\ProductAvailabilityStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MerchantAvailabilityStatusSeeder
{
    public function seedDefaultsForMerchant(MerchantProfile $merchant): void
    {
        DB::transaction(function () use ($merchant): void {
            foreach (ProductAvailabilityStatus::defaults() as $code => $definition) {
                ProductAvailabilityStatus::query()->firstOrCreate(
                    [
                        'merchant_id' => $merchant->getKey(),
                        'code' => $code,
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        ...$definition,
                        'status' => ProductAvailabilityStatus::STATUS_ACTIVE,
                    ],
                );
            }
        });
    }

    public function defaultForMerchant(int $merchantId, string $code = ProductAvailabilityStatus::CODE_IN_STOCK): ?ProductAvailabilityStatus
    {
        return ProductAvailabilityStatus::query()
            ->where('merchant_id', $merchantId)
            ->where('code', $code)
            ->active()
            ->first();
    }
}
