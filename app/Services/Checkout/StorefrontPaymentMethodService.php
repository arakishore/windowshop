<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\Shop;
use App\Services\Admin\AdminSettingsService;
use App\Services\Merchant\ShopSettingsInitializer;
use App\Services\Merchant\ShopSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StorefrontPaymentMethodService
{
    public const SELECTED_PAYMENT_SESSION_KEY = 'storefront.checkout.selected_payment_method';

    public const PAYMENT_CASH_ON_DELIVERY = 'cash_on_delivery';
    public const PAYMENT_CASH_AT_SHOP = 'cash_at_shop';

    public function __construct(
        private readonly ShopSettingsService $shopSettings,
        private readonly ShopSettingsInitializer $shopSettingsInitializer,
        private readonly AdminSettingsService $adminSettings,
    ) {
    }

    /**
     * @param array<string, mixed> $cartData
     * @return array{methods: array<int, array<string, mixed>>, selected: ?string, message: ?string}
     */
    public function resolve(Request $request, ?Cart $cart, array $cartData, ?string $fulfillment): array
    {
        $groups = collect($cartData['shop_groups'] ?? []);
        $shops = $this->shopsById($cart);
        $methods = match ($fulfillment) {
            StorefrontDeliveryService::FULFILLMENT_DELIVERY => $this->deliveryMethods($groups, $shops),
            StorefrontDeliveryService::FULFILLMENT_PICKUP => $this->pickupMethods($groups, $shops),
            default => [],
        };

        $selected = $this->selectedPayment($request, $methods);
        foreach ($methods as &$method) {
            $method['selected'] = $method['id'] === $selected;
        }
        unset($method);

        if ($selected !== null) {
            $request->session()->put(self::SELECTED_PAYMENT_SESSION_KEY, $selected);
        } else {
            $request->session()->forget(self::SELECTED_PAYMENT_SESSION_KEY);
        }

        return [
            'methods' => $methods,
            'selected' => $selected,
            'message' => $this->unavailableMessage($fulfillment),
        ];
    }

    public function isAvailable(Request $request, ?Cart $cart, array $cartData, ?string $fulfillment, string $paymentMethod): bool
    {
        $resolved = $this->resolve($request, $cart, $cartData, $fulfillment);

        return collect($resolved['methods'])->contains(
            fn (array $method): bool => $method['id'] === $paymentMethod && (bool) ($method['available'] ?? false),
        );
    }

    /**
     * @return Collection<int, Shop>
     */
    private function shopsById(?Cart $cart): Collection
    {
        return $cart?->items
            ->map(fn ($item): ?Shop => $item->shop)
            ->filter()
            ->unique(fn (Shop $shop): int => (int) $shop->getKey())
            ->keyBy(fn (Shop $shop): int => (int) $shop->getKey()) ?? collect();
    }

    /**
     * @param Collection<int, array<string, mixed>> $groups
     * @param Collection<int, Shop> $shops
     * @return array<int, array<string, mixed>>
     */
    private function deliveryMethods(Collection $groups, Collection $shops): array
    {
        if ($groups->isEmpty()) {
            return [];
        }

        $failedReason = null;

        foreach ($groups as $group) {
            $shop = $shops->get((int) ($group['shop_id'] ?? 0));
            if (! $shop instanceof Shop) {
                continue;
            }

            $shopId = (int) $shop->getKey();
            $this->shopSettingsInitializer->initialize($shopId);

            if (! (bool) $this->shopSettings->get($shopId, 'payment', 'cod_enabled', false)) {
                return [];
            }

            $subtotal = ((int) ($group['subtotal_cents'] ?? 0)) / 100;
            $reason = $this->codAmountFailureReason($shopId, $subtotal);

            if ($reason !== null && $failedReason === null) {
                $failedReason = $reason;
            }
        }

        return [[
            'id' => self::PAYMENT_CASH_ON_DELIVERY,
            'label' => 'Cash on Delivery',
            'description' => 'Pay when your order is delivered.',
            'available' => $failedReason === null,
            'selected' => false,
            'reason' => $failedReason,
        ]];
    }

    /**
     * @param Collection<int, array<string, mixed>> $groups
     * @param Collection<int, Shop> $shops
     * @return array<int, array<string, mixed>>
     */
    private function pickupMethods(Collection $groups, Collection $shops): array
    {
        if ($groups->count() !== 1) {
            return [];
        }

        $group = $groups->first();
        $shop = $shops->get((int) ($group['shop_id'] ?? 0));
        if (! $shop instanceof Shop) {
            return [];
        }

        $shopId = (int) $shop->getKey();
        $this->shopSettingsInitializer->initialize($shopId);

        if (! (bool) $this->shopSettings->get($shopId, 'payment', 'cash_at_shop_enabled', true)) {
            return [];
        }

        return [[
            'id' => self::PAYMENT_CASH_AT_SHOP,
            'label' => 'Cash at Shop',
            'description' => 'Pay when you collect your order.',
            'available' => true,
            'selected' => false,
            'reason' => null,
        ]];
    }

    private function codAmountFailureReason(int $shopId, float $subtotal): ?string
    {
        $minimum = $this->nullablePositiveMoney($this->shopSettings->get($shopId, 'payment', 'cod_min_order_amount'));
        if ($minimum !== null && $subtotal < $minimum) {
            return 'Minimum order of '.$this->money($minimum).' required for COD.';
        }

        $maximum = $this->nullablePositiveMoney($this->shopSettings->get($shopId, 'payment', 'cod_max_order_amount'));
        if ($maximum !== null && $subtotal > $maximum) {
            return 'COD is available only up to '.$this->money($maximum).'.';
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $methods
     */
    private function selectedPayment(Request $request, array $methods): ?string
    {
        $available = collect($methods)
            ->filter(fn (array $method): bool => (bool) ($method['available'] ?? false))
            ->values();

        if ($available->isEmpty()) {
            return null;
        }

        $current = $request->session()->get(self::SELECTED_PAYMENT_SESSION_KEY);
        if ($current && $available->contains(fn (array $method): bool => $method['id'] === $current)) {
            return (string) $current;
        }

        return $available->count() === 1 ? (string) $available->first()['id'] : null;
    }

    private function unavailableMessage(?string $fulfillment): ?string
    {
        return match ($fulfillment) {
            StorefrontDeliveryService::FULFILLMENT_DELIVERY => 'No payment method is currently available for this delivery option.',
            StorefrontDeliveryService::FULFILLMENT_PICKUP => 'No payment method is currently available for this pickup option.',
            default => 'Select a delivery or pickup option to choose a payment method.',
        };
    }

    private function nullablePositiveMoney(mixed $value): ?float
    {
        if ($value === null || $value === '' || (float) $value <= 0) {
            return null;
        }

        return (float) $value;
    }

    private function money(float|int|string $amount): string
    {
        $currency = $this->adminSettings->currencyConfig();
        $formatted = number_format(
            (float) $amount,
            (int) ($currency['decimal_places'] ?? 2),
            (string) ($currency['decimal_separator'] ?? '.'),
            (string) ($currency['thousands_separator'] ?? ','),
        );
        $symbol = (string) ($currency['symbol'] ?? 'INR ');

        return ($currency['symbol_position'] ?? 'before') === 'before'
            ? $symbol.$formatted
            : $formatted.' '.$symbol;
    }
}
