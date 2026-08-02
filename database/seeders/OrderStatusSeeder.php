<?php

namespace Database\Seeders;

use App\Models\OrderStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (OrderStatus::systemDefaults() as $code => $definition) {
                OrderStatus::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'uuid' => (string) Str::uuid(),
                        ...$definition,
                        'is_system' => true,
                        'customer_visible' => true,
                        'merchant_visible' => true,
                        'status' => OrderStatus::STATUS_ACTIVE,
                    ],
                );
            }
        });
    }
}
