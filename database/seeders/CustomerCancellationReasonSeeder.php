<?php

namespace Database\Seeders;

use App\Models\CustomerCancellationReason;
use Illuminate\Database\Seeder;

class CustomerCancellationReasonSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CustomerCancellationReason::defaults() as $code => $reason) {
            CustomerCancellationReason::query()->updateOrCreate(
                ['code' => $code],
                $reason,
            );
        }
    }
}
