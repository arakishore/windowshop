<?php

namespace App\Services\Merchant;

use App\Models\MerchantProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MerchantDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function data(MerchantProfile $merchant, ?int $activeShopId): array
    {
        return [
            'stats' => [
                'total_shops' => $this->activeShopCount($merchant),
                'products' => $this->countForShop('products', $activeShopId),
                'pending_orders' => $this->countForShop('orders', $activeShopId, ['status' => 'pending']),
                'todays_orders' => $this->todaysOrders($activeShopId),
                'revenue_today' => $this->revenueToday($activeShopId),
                'out_of_stock' => $this->outOfStockProducts($activeShopId),
                'active_offers' => $this->countForShop('offers', $activeShopId, ['status' => 'active']),
                'customers' => $this->countForShop('customers', $activeShopId),
            ],
            'latest_orders' => $this->latestOrders($activeShopId),
            'recent_activities' => [
                ['type' => 'success', 'icon' => 'ph-check-circle', 'title' => 'Shop context ready', 'description' => 'Merchant modules will use the active shop automatically.'],
                ['type' => 'info', 'icon' => 'ph-storefront', 'title' => 'Active shop persisted', 'description' => 'The selected shop remains active until changed or logout.'],
                ['type' => 'warning', 'icon' => 'ph-warning-circle', 'title' => 'Catalog pending', 'description' => 'Product, order, inventory, and offer modules are not created yet.'],
            ],
        ];
    }

    private function activeShopCount(MerchantProfile $merchant): int
    {
        return $merchant->shops()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * @param array<string, string> $filters
     */
    private function countForShop(string $table, ?int $activeShopId, array $filters = []): int
    {
        if ($activeShopId === null || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'shop_id')) {
            return 0;
        }

        $query = DB::table($table)->where('shop_id', $activeShopId);

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        foreach ($filters as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $query->where($column, $value);
            }
        }

        return $query->count();
    }

    private function todaysOrders(?int $activeShopId): int
    {
        if ($activeShopId === null || ! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'shop_id')) {
            return 0;
        }

        $query = DB::table('orders')
            ->where('shop_id', $activeShopId);

        if (Schema::hasColumn('orders', 'created_at')) {
            $query->whereDate('created_at', today());
        }

        return $query->count();
    }

    private function revenueToday(?int $activeShopId): int
    {
        if ($activeShopId === null || ! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'shop_id')) {
            return 0;
        }

        foreach (['total_amount', 'grand_total', 'amount'] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                $query = DB::table('orders')->where('shop_id', $activeShopId);

                if (Schema::hasColumn('orders', 'created_at')) {
                    $query->whereDate('created_at', today());
                }

                return (int) $query->sum($column);
            }
        }

        return 0;
    }

    private function outOfStockProducts(?int $activeShopId): int
    {
        if ($activeShopId === null || ! Schema::hasTable('products') || ! Schema::hasColumn('products', 'shop_id')) {
            return 0;
        }

        foreach (['stock_quantity', 'stock', 'quantity'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                return DB::table('products')
                    ->where('shop_id', $activeShopId)
                    ->where($column, '<=', 0)
                    ->count();
            }
        }

        return 0;
    }

    /**
     * @return array<int, object>
     */
    private function latestOrders(?int $activeShopId): array
    {
        if ($activeShopId === null || ! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'shop_id')) {
            return [];
        }

        return DB::table('orders')
            ->where('shop_id', $activeShopId)
            ->when(Schema::hasColumn('orders', 'created_at'), fn ($query) => $query->orderByDesc('created_at'))
            ->limit(5)
            ->get()
            ->all();
    }
}
