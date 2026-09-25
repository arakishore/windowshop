<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ShopSetting;
use App\Services\Checkout\StorefrontPaymentMethodService;
use App\Services\Merchant\ShopSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExpireUncollectedPickupsService
{
    private const ENABLED_WINDOWS = [12, 24, 48, 72];

    public function __construct(
        private readonly ShopSettingsService $shopSettings,
        private readonly OrderInventoryService $inventory,
        private readonly OrderStatusService $statuses,
    ) {}

    /** @return array{candidates: int, expired: int, skipped: int} */
    public function run(): array
    {
        $enabledShopIds = ShopSetting::query()
            ->where('group', 'payment')
            ->where('setting_key', 'cash_at_shop_pickup_expiry_hours')
            ->whereIn('setting_value', ['12', '24', '48', '72'])
            ->pluck('shop_id');

        $counts = ['candidates' => 0, 'expired' => 0, 'skipped' => 0];
        if ($enabledShopIds->isEmpty()) {
            return $counts;
        }

        $firstReadyForPickup = OrderStatusHistory::query()
            ->selectRaw('order_id, MIN(created_at) as ready_for_pickup_at')
            ->where('to_status', Order::STATUS_READY_FOR_PICKUP)
            ->groupBy('order_id');

        Order::query()
            ->select('orders.id')
            ->joinSub($firstReadyForPickup, 'first_ready_for_pickup', fn ($join) => $join->on('first_ready_for_pickup.order_id', '=', 'orders.id'))
            ->whereIn('orders.shop_id', $enabledShopIds)
            ->where('orders.created_source', Order::SOURCE_STOREFRONT)
            ->where('orders.payment_method', StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP)
            ->where('orders.fulfilment_type', Order::FULFILMENT_PICKUP)
            ->where('orders.order_status', Order::STATUS_READY_FOR_PICKUP)
            ->where('first_ready_for_pickup.ready_for_pickup_at', '<=', now()->subHours(12))
            ->orderBy('orders.id')
            ->chunkById(100, function ($orders) use (&$counts): void {
                foreach ($orders as $order) {
                    $counts['candidates']++;
                    if ($this->expire((int) $order->getKey())) {
                        $counts['expired']++;
                    } else {
                        $counts['skipped']++;
                    }
                }
            }, 'orders.id', 'id');

        return $counts;
    }

    public function expire(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId): bool {
            $order = Order::query()->lockForUpdate()->find($orderId);
            if (! $order instanceof Order || ! $this->isEligible($order)) {
                return false;
            }

            $expiryHours = $this->expiryHours((int) $order->shop_id);
            if ($expiryHours === 0) {
                return false;
            }

            $readyForPickupAt = OrderStatusHistory::query()
                ->where('order_id', $order->getKey())
                ->where('to_status', Order::STATUS_READY_FOR_PICKUP)
                ->min('created_at');

            if ($readyForPickupAt === null) {
                return false;
            }

            $readyForPickupAt = Carbon::parse($readyForPickupAt);
            if (now()->lt($readyForPickupAt->copy()->addHours($expiryHours))) {
                return false;
            }

            $restored = $this->inventory->restoreForCancellation($order);
            $this->statuses->transition(
                $order,
                Order::STATUS_CANCELLED,
                null,
                "The customer did not collect the order within the {$expiryHours}-hour pickup collection window. The order was automatically cancelled.",
                [
                    'action' => OrderStatusHistory::ACTION_PICKUP_COLLECTION_EXPIRED,
                    'automatic' => true,
                    'expiry_hours' => $expiryHours,
                    'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP,
                    'ready_for_pickup_at' => $readyForPickupAt->toISOString(),
                    'expired_at' => now()->toISOString(),
                    'stock_restored' => $restored,
                ],
            );

            return true;
        });
    }

    private function isEligible(Order $order): bool
    {
        return $order->created_source === Order::SOURCE_STOREFRONT
            && $order->payment_method === StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP
            && $order->fulfilment_type === Order::FULFILMENT_PICKUP
            && $order->order_status === Order::STATUS_READY_FOR_PICKUP;
    }

    private function expiryHours(int $shopId): int
    {
        $rawValue = ShopSetting::query()
            ->where('shop_id', $shopId)
            ->where('group', 'payment')
            ->where('setting_key', 'cash_at_shop_pickup_expiry_hours')
            ->value('setting_value');

        if (! in_array($rawValue, ['12', '24', '48', '72'], true)) {
            return 0;
        }

        $value = $this->shopSettings->get($shopId, 'payment', 'cash_at_shop_pickup_expiry_hours', 0);

        return in_array((int) $value, self::ENABLED_WINDOWS, true) ? (int) $value : 0;
    }
}
