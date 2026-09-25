<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ShopSetting;
use App\Services\Checkout\StorefrontPaymentMethodService;
use App\Services\Merchant\ShopSettingsService;
use Illuminate\Support\Facades\DB;

class ExpireUnverifiedUpiOrdersService
{
    private const ELIGIBLE_STATUSES = [Order::STATUS_PENDING];

    private const UNRESOLVED_PAYMENT_STATUSES = [Order::PAYMENT_PENDING, Order::PAYMENT_UNPAID];

    public function __construct(
        private readonly ShopSettingsService $shopSettings,
        private readonly OrderInventoryService $inventory,
        private readonly OrderStatusService $statuses,
    ) {}

    /** @return array{candidates: int, expired: int, skipped: int} */
    public function run(): array
    {
        $enabledShops = ShopSetting::query()
            ->where('group', 'payment')
            ->where('setting_key', 'merchant_upi_expiry_minutes')
            ->whereIn('setting_value', ['5', '10', '15', '30'])
            ->pluck('setting_value', 'shop_id')
            ->map(fn ($value): int => (int) $value);

        $counts = ['candidates' => 0, 'expired' => 0, 'skipped' => 0];
        if ($enabledShops->isEmpty()) {
            return $counts;
        }

        Order::query()
            ->select('id')
            ->whereIn('shop_id', $enabledShops->keys())
            ->where('created_source', Order::SOURCE_STOREFRONT)
            ->where('payment_method', StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI)
            ->whereIn('payment_status', self::UNRESOLVED_PAYMENT_STATUSES)
            ->whereIn('order_status', self::ELIGIBLE_STATUSES)
            ->where('created_at', '<=', now()->subMinutes(5))
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$counts): void {
                foreach ($orders as $order) {
                    $counts['candidates']++;
                    if ($this->expire((int) $order->getKey())) {
                        $counts['expired']++;
                    } else {
                        $counts['skipped']++;
                    }
                }
            });

        return $counts;
    }

    public function expire(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId): bool {
            $order = Order::query()->lockForUpdate()->find($orderId);
            if (! $order instanceof Order || ! $this->isEligible($order)) {
                return false;
            }

            $expiryMinutes = $this->expiryMinutes((int) $order->shop_id);
            if ($expiryMinutes === 0 || now()->lt($order->created_at->copy()->addMinutes($expiryMinutes))) {
                return false;
            }

            $restored = $this->inventory->restoreForCancellation($order);
            $this->statuses->transition(
                $order,
                Order::STATUS_CANCELLED,
                null,
                "Direct UPI payment was not verified within {$expiryMinutes} minutes. The order was automatically cancelled.",
                [
                    'action' => OrderStatusHistory::ACTION_UPI_PAYMENT_EXPIRED,
                    'automatic' => true,
                    'expiry_minutes' => $expiryMinutes,
                    'payment_method' => StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI,
                    'stock_restored' => $restored,
                ],
            );

            return true;
        });
    }

    private function isEligible(Order $order): bool
    {
        return $order->created_source === Order::SOURCE_STOREFRONT
            && $order->payment_method === StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI
            && in_array($order->payment_status, self::UNRESOLVED_PAYMENT_STATUSES, true)
            && in_array($order->order_status, self::ELIGIBLE_STATUSES, true);
    }

    private function expiryMinutes(int $shopId): int
    {
        $value = $this->shopSettings->get($shopId, 'payment', 'merchant_upi_expiry_minutes', 0);

        return in_array((int) $value, [5, 10, 15, 30], true) ? (int) $value : 0;
    }
}
