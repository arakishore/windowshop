<?php

namespace App\Services\Notification;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderTotal;
use App\Models\PaymentStatus;
use App\Services\DateTime\DateDisplayService;
use App\Services\System\SystemSettingService;
use Illuminate\Support\Str;

class OrderEmailPresenter
{
    public function __construct(
        private readonly DateDisplayService $dates,
        private readonly SystemSettingService $systemSettings,
    ) {}

    /** @return array<string, mixed> */
    public function present(Order $order, string $notificationKey, array $context = []): array
    {
        $order->loadMissing(['shop', 'items', 'totals']);
        $shopName = $order->shop?->name ?: 'Shop';

        return [
            ...$this->audience($order, $notificationKey, $shopName, $context),
            'order_number' => $order->order_number,
            'placed_at' => $this->dates->dateTime($order->created_at),
            'shop_name' => $shopName,
            'customer' => array_filter(['name' => $order->customer_name, 'email' => $order->customer_email, 'phone' => $order->customer_mobile], fn ($value): bool => trim((string) $value) !== ''),
            'fulfilment_label' => $this->fulfilmentLabel($order->fulfilment_type),
            'payment_method_label' => $this->paymentMethodLabel($order->payment_method),
            'payment_status_label' => $this->statusLabel(PaymentStatus::class, $order->payment_status),
            'order_status_label' => $this->statusLabel(OrderStatus::class, $order->order_status),
            'billing_address' => $this->address($order, 'billing'),
            'delivery_address' => $order->fulfilment_type === Order::FULFILMENT_DELIVERY ? $this->address($order, 'shipping') : [],
            'pickup_details' => $order->fulfilment_type === Order::FULFILMENT_PICKUP ? array_values(array_filter([$shopName, $order->shop?->address_line_1, $order->shop?->address_line_2])) : [],
            'items' => $order->items->map(function ($item) use ($order): array {
                $productName = trim((string) $item->product_name);

                return [
                    'name' => $productName !== '' ? $productName : 'Product',
                    'variant' => $this->displayVariant($productName, $item->variant_name),
                    'sku' => $item->sku,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => $this->money($order, $item->unit_price),
                    'line_total' => $this->money($order, $item->line_total),
                    'is_gift' => $this->isGift((array) $item->metadata),
                ];
            })->all(),
            'totals' => $order->totals->filter(fn (OrderTotal $total): bool => $total->code === OrderTotal::CODE_GRAND_TOTAL || abs((float) $total->amount) > 0.00001)
                ->map(fn (OrderTotal $total): array => ['code' => $total->code, 'title' => $total->code === OrderTotal::CODE_GRAND_TOTAL ? 'Order Total' : ($total->title ?: Str::headline($total->code)), 'amount' => $this->money($order, $total->amount), 'prominent' => $total->code === OrderTotal::CODE_GRAND_TOTAL])->values()->all(),
            'promotions' => $this->promotions($order),
        ];
    }

    /** @return array<string, mixed> */
    private function audience(Order $order, string $key, string $shopName, array $context): array
    {
        return match ($key) {
            'order.placed.customer' => [
                'audience' => 'customer', 'subject' => 'Order received — '.$order->order_number, 'heading' => 'Order received',
                'greeting' => 'Hi '.($order->customer_name ?: 'there').',',
                'intro' => "We've received your order placed with {$shopName}.\n\nThe shop will review your order and we'll keep you updated.",
                'cta_label' => 'VIEW YOUR ORDER', 'cta_url' => route('storefront.account.orders.show', $order),
                'follow_up' => "You can view your order details and track updates.\nWe'll notify you when your order status changes.",
            ],
            'order.new.merchant' => [
                'audience' => 'merchant', 'subject' => 'New order received — '.$order->order_number, 'heading' => 'New order received',
                'greeting' => 'Hello '.($context['merchant_name'] ?? 'Merchant').',',
                'intro' => "A new order has been placed with {$shopName}.\n\nPlease review and process the order.",
                'cta_label' => 'VIEW & PROCESS ORDER', 'cta_url' => route('merchant.orders.show', $order), 'follow_up' => null,
            ],
            default => [
                'audience' => 'admin', 'subject' => 'New order received — '.$order->order_number, 'heading' => 'New order received', 'greeting' => null,
                'intro' => 'A new order has been placed on '.$this->systemSettings->marketplaceName()." for {$shopName}.",
                'cta_label' => null, 'cta_url' => null, 'follow_up' => null,
            ],
        };
    }

    private function paymentMethodLabel(?string $method): string
    {
        return [Order::PAYMENT_METHOD_CASH => 'Cash', Order::PAYMENT_METHOD_CARD => 'Card', Order::PAYMENT_METHOD_UPI => 'UPI', Order::PAYMENT_METHOD_CREDIT => 'Credit', 'cash_on_delivery' => 'Cash on Delivery', 'cash_at_shop' => 'Cash at Shop', 'merchant_upi' => 'Direct Merchant UPI', 'online_payment' => 'Online Payment', Order::PAYMENT_METHOD_WALLET => 'Wallet', Order::PAYMENT_METHOD_OTHER => 'Other'][$method] ?? Str::headline((string) $method);
    }

    private function fulfilmentLabel(?string $type): string
    {
        return [Order::FULFILMENT_DELIVERY => 'Delivery', Order::FULFILMENT_PICKUP => 'Pickup', Order::FULFILMENT_COUNTER => 'Counter'][$type] ?? Str::headline((string) $type);
    }

    private function statusLabel(string $model, ?string $status): string
    {
        return $model::query()->active()->merchantVisible()->where('code', $status)->value('name') ?: Str::headline((string) $status);
    }

    /** @return array<int, string> */
    private function address(Order $order, string $prefix): array
    {
        return array_values(array_filter([
            $order->getAttribute("{$prefix}_recipient_name"),
            trim(implode(', ', array_filter([$order->getAttribute("{$prefix}_address_line_1"), $order->getAttribute("{$prefix}_address_line_2")]))),
            $order->getAttribute("{$prefix}_landmark"),
            trim(implode(', ', array_filter([$order->getAttribute("{$prefix}_city"), $order->getAttribute("{$prefix}_state")]))),
            trim(implode(' ', array_filter([$order->getAttribute("{$prefix}_country"), $order->getAttribute("{$prefix}_postal_code")]))),
            trim(implode(' ', array_filter([$order->getAttribute("{$prefix}_mobile_country_code"), $order->getAttribute("{$prefix}_mobile")]))),
        ], fn ($value): bool => trim((string) $value) !== ''));
    }

    private function money(Order $order, mixed $amount): string
    {
        $currency = $this->systemSettings->currencyConfig();
        $formatted = number_format((float) $amount, (int) $currency['decimal_places'], (string) $currency['decimal_separator'], (string) $currency['thousands_separator']);
        $configuredSymbol = (string) $currency['symbol'];
        if (($currency['currency'] ?? null) === 'INR' && str_contains($configuredSymbol, 'â')) {
            $configuredSymbol = "\u{20B9}";
        }
        $symbol = $order->currency_code === $currency['currency'] ? $configuredSymbol : (string) ($order->currency_code ?: $currency['currency']).' ';

        return $currency['symbol_position'] === 'after' && $order->currency_code === $currency['currency'] ? $formatted.$symbol : $symbol.$formatted;
    }

    private function isGift(array $metadata): bool
    {
        return data_get($metadata, 'promotion.details.role') === 'gift' || in_array('gift', (array) data_get($metadata, 'promotion.details.roles', []), true);
    }

    private function displayVariant(string $productName, mixed $variantName): ?string
    {
        $variantName = trim((string) $variantName);

        if ($variantName === '' || mb_strtolower($variantName) === mb_strtolower($productName)) {
            return null;
        }

        return $variantName;
    }

    private function promotions(Order $order): array
    {
        return $order->items->map(function ($item): ?array {
            $promotion = data_get($item->metadata, 'promotion');

            return is_array($promotion) && filled($promotion['name'] ?? null) ? ['name' => (string) $promotion['name'], 'coupon_code' => filled($promotion['coupon_code'] ?? null) ? (string) $promotion['coupon_code'] : null] : null;
        })->filter()->unique(fn (array $promotion): string => $promotion['name'].'|'.$promotion['coupon_code'])->values()->all();
    }
}
