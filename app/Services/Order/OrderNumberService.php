<?php

namespace App\Services\Order;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderNumberService
{
    public function generate(?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = 'ORD-'.$date->format('Ymd').'-';

        return Cache::lock('order-number:'.$date->format('Ymd'), 10)->block(5, function () use ($prefix): string {
            return DB::transaction(function () use ($prefix): string {
                $latest = Order::withTrashed()
                    ->where('order_number', 'like', $prefix.'%')
                    ->orderByDesc('order_number')
                    ->lockForUpdate()
                    ->value('order_number');

                $next = $latest ? ((int) substr($latest, -6)) + 1 : 1;

                return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            });
        });
    }
}
