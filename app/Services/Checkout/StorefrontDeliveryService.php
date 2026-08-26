<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\CustomerAddress;
use App\Models\Shop;
use App\Services\Admin\AdminSettingsService;
use App\Services\Merchant\ShopSettingsInitializer;
use App\Services\Merchant\ShopSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StorefrontDeliveryService
{
    public const SELECTED_FULFILLMENT_SESSION_KEY = 'storefront.checkout.selected_fulfillment';

    public const FULFILLMENT_DELIVERY = 'delivery';
    public const FULFILLMENT_PICKUP = 'pickup';
    public const LEGACY_FULFILLMENT_STANDARD = 'standard';

    public function __construct(
        private readonly ShopDeliveryQuoteService $deliveryQuotes,
        private readonly ShopSettingsService $shopSettings,
        private readonly ShopSettingsInitializer $shopSettingsInitializer,
        private readonly AdminSettingsService $adminSettings,
    ) {
    }

    /**
     * @param array<string, mixed> $cartData
     * @return array{options: array<int, array<string, mixed>>, selected: ?string, shipping_cents: int, shipping: string, total_cents: int, total: string}
     */
    public function resolve(Request $request, ?Cart $cart, array $cartData, ?CustomerAddress $selectedAddress): array
    {
        $groups = collect($cartData['shop_groups'] ?? []);
        $shops = $this->shopsById($cart);
        $postalCode = $selectedAddress?->postal_code;
        $options = [];

        $delivery = $this->deliveryOption($groups, $shops, $selectedAddress, $postalCode);
        if ($delivery !== null) {
            $options[] = $delivery;
        }

        $pickup = $this->pickupOption($groups, $shops);
        if ($pickup !== null) {
            $options[] = $pickup;
        }

        $selected = $this->selectedFulfillment($request, $options);
        foreach ($options as &$option) {
            $option['selected'] = $option['id'] === $selected;
        }
        unset($option);

        if ($selected !== null) {
            $request->session()->put(self::SELECTED_FULFILLMENT_SESSION_KEY, $selected);
        } else {
            $request->session()->forget(self::SELECTED_FULFILLMENT_SESSION_KEY);
        }

        $selectedOption = collect($options)->firstWhere('id', $selected);
        $shippingCents = (int) ($selectedOption['shipping_cents'] ?? 0);
        $subtotalCents = (int) ($cartData['subtotal_cents'] ?? 0);
        $totalCents = max(0, $subtotalCents + $shippingCents);

        return [
            'options' => $options,
            'selected' => $selected,
            'shipping_cents' => $shippingCents,
            'shipping' => $this->moneyFromCents($shippingCents),
            'total_cents' => $totalCents,
            'total' => $this->moneyFromCents($totalCents),
        ];
    }

    public function select(Request $request, string $fulfillment): array
    {
        if ($fulfillment === self::LEGACY_FULFILLMENT_STANDARD) {
            $fulfillment = self::FULFILLMENT_DELIVERY;
        }

        if (! in_array($fulfillment, [self::FULFILLMENT_DELIVERY, self::FULFILLMENT_PICKUP], true)) {
            $fulfillment = self::FULFILLMENT_DELIVERY;
        }

        $request->session()->put(self::SELECTED_FULFILLMENT_SESSION_KEY, $fulfillment);

        return ['selected' => $fulfillment];
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
     * @return array<string, mixed>|null
     */
    private function deliveryOption(Collection $groups, Collection $shops, ?CustomerAddress $selectedAddress, ?string $postalCode): ?array
    {
        if ($groups->isEmpty()) {
            return null;
        }

        $quotes = [];
        $allDeliveryDisabled = true;

        foreach ($groups as $group) {
            $shop = $shops->get((int) ($group['shop_id'] ?? 0));
            if (! $shop instanceof Shop) {
                continue;
            }

            $this->shopSettingsInitializer->initialize((int) $shop->getKey());
            if ((bool) $this->shopSettings->get((int) $shop->getKey(), 'fulfillment', 'delivery_enabled', true)) {
                $allDeliveryDisabled = false;
            }

            $subtotal = ((int) ($group['subtotal_cents'] ?? 0)) / 100;
            $quotes[] = [
                'shop' => $shop,
                'group' => $group,
                'quote' => $this->deliveryQuotes->quote($shop, $subtotal, $postalCode),
            ];
        }

        if ($quotes === [] || $allDeliveryDisabled) {
            return null;
        }

        $failed = collect($quotes)->first(fn (array $row): bool => ! (bool) $row['quote']['available']);
        $available = $failed === null;
        $shippingCents = $available
            ? (int) collect($quotes)->sum(fn (array $row): int => $this->moneyToCents($row['quote']['charge'] ?? 0))
            : 0;
        $estimate = $this->estimateText(collect($quotes)->pluck('quote')->all());

        $description = $selectedAddress instanceof CustomerAddress
            ? $this->deliveryAddressText($selectedAddress)
            : 'Select a delivery address to calculate availability.';

        return [
            'id' => self::FULFILLMENT_DELIVERY,
            'label' => 'Standard Delivery',
            'description' => $description,
            'description_lines' => [
                ['text' => $description, 'type' => 'summary'],
            ],
            'amount' => $available ? $this->chargeText($shippingCents) : 'Unavailable',
            'shipping_cents' => $shippingCents,
            'available' => $available,
            'selected' => false,
            'reason' => $available ? null : $this->deliveryFailureReason($failed),
            'estimate' => $available ? $estimate : null,
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $groups
     * @param Collection<int, Shop> $shops
     * @return array<string, mixed>|null
     */
    private function pickupOption(Collection $groups, Collection $shops): ?array
    {
        if ($groups->count() !== 1) {
            return null;
        }

        $group = $groups->first();
        $shop = $shops->get((int) ($group['shop_id'] ?? 0));
        if (! $shop instanceof Shop) {
            return null;
        }

        $shopId = (int) $shop->getKey();
        $this->shopSettingsInitializer->initialize($shopId);

        if (! (bool) $this->shopSettings->get($shopId, 'fulfillment', 'pickup_enabled', true)) {
            return null;
        }

        $instructions = trim((string) $this->shopSettings->get($shopId, 'fulfillment', 'pickup_instructions', ''));
        $address = collect([
            $shop->address_line_1,
            $shop->address_line_2,
            $shop->city?->name,
            $shop->pincode,
        ])->filter()->implode(', ');

        $descriptionLines = collect([
            ['text' => 'Collect from '.$shop->name, 'type' => 'shop'],
            ['text' => $address, 'type' => 'address'],
            ['text' => $instructions ?: 'Collect directly from the shop.', 'type' => 'instructions'],
        ])->filter(fn (array $line): bool => $line['text'] !== '')->values()->all();

        return [
            'id' => self::FULFILLMENT_PICKUP,
            'label' => 'Pickup from Shop',
            'description' => collect($descriptionLines)->pluck('text')->implode(' '),
            'description_lines' => $descriptionLines,
            'amount' => 'FREE',
            'shipping_cents' => 0,
            'available' => true,
            'selected' => false,
            'reason' => null,
            'estimate' => null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $options
     */
    private function selectedFulfillment(Request $request, array $options): ?string
    {
        $available = collect($options)
            ->filter(fn (array $option): bool => (bool) ($option['available'] ?? false))
            ->values();

        if ($available->isEmpty()) {
            return null;
        }

        $current = $request->session()->get(self::SELECTED_FULFILLMENT_SESSION_KEY);
        if ($current && $available->contains(fn (array $option): bool => $option['id'] === $current)) {
            return (string) $current;
        }

        return $available->firstWhere('id', self::FULFILLMENT_DELIVERY)['id']
            ?? $available->first()['id'];
    }

    private function deliveryAddressText(CustomerAddress $address): string
    {
        return collect([
            'Deliver to '.$address->label,
            $address->city?->name,
            $address->postal_code,
        ])->filter()->implode(', ');
    }

    /**
     * @param array<string, mixed>|null $failed
     */
    private function deliveryFailureReason(?array $failed): ?string
    {
        if ($failed === null) {
            return null;
        }

        $shopName = (string) ($failed['group']['shop_name'] ?? $failed['shop']->name ?? 'This shop');
        $reason = (string) ($failed['quote']['reason'] ?? 'Delivery is unavailable.');

        if (($failed['quote']['minimum_order_amount'] ?? null) !== null && str_contains($reason, 'Minimum delivery order')) {
            $minimumCents = $this->moneyToCents($failed['quote']['minimum_order_amount']);
            $reason = 'Minimum order of '.$this->moneyFromCents($minimumCents).' required for delivery.';
        }

        return $shopName.': '.$reason;
    }

    /**
     * @param array<int, array<string, mixed>> $quotes
     */
    private function estimateText(array $quotes): ?string
    {
        $mins = collect($quotes)->pluck('estimated_min_days')->filter(fn ($days): bool => $days !== null);
        $maxes = collect($quotes)->pluck('estimated_max_days')->filter(fn ($days): bool => $days !== null);

        if ($mins->isEmpty() && $maxes->isEmpty()) {
            return null;
        }

        $min = $mins->isNotEmpty() ? (int) $mins->min() : (int) $maxes->min();
        $max = $maxes->isNotEmpty() ? (int) $maxes->max() : $min;

        if ($max <= $min) {
            return 'Estimated delivery: '.$this->estimateDayText($min);
        }

        return 'Estimated delivery: '.$this->estimateDayText($min).' to '.$this->estimateDayText($max);
    }

    private function estimateDayText(int $days): string
    {
        if ($days <= 0) {
            return 'Same day';
        }

        return $days.' '.($days === 1 ? 'day' : 'days');
    }

    private function chargeText(int $shippingCents): string
    {
        return $shippingCents <= 0 ? 'FREE' : $this->moneyFromCents($shippingCents);
    }

    private function moneyToCents(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function moneyFromCents(int $cents): string
    {
        $currency = $this->adminSettings->currencyConfig();
        $amount = number_format(
            $cents / 100,
            (int) ($currency['decimal_places'] ?? 2),
            (string) ($currency['decimal_separator'] ?? '.'),
            (string) ($currency['thousands_separator'] ?? ','),
        );
        $symbol = (string) ($currency['symbol'] ?? 'INR ');

        return ($currency['symbol_position'] ?? 'before') === 'before'
            ? $symbol.$amount
            : $amount.' '.$symbol;
    }
}
