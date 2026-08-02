<?php

namespace Database\Seeders;

use App\Models\PaymentStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentStatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (PaymentStatus::systemDefaults() as $code => $definition) {
                PaymentStatus::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'uuid' => (string) Str::uuid(),
                        ...$definition,
                        'category_description' => PaymentStatus::categoryDescriptions()[$definition['category']] ?? null,
                        'is_system' => true,
                        'merchant_visible' => true,
                        'status' => PaymentStatus::STATUS_ACTIVE,
                    ],
                );
            }
        });
    }
}
