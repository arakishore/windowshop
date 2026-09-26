<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Services\Admin\AdminSettingsService;
use App\Services\Checkout\StorefrontPaymentMethodService;

class OrderReceiptPresenter
{
    public function __construct(private readonly AdminSettingsService $adminSettings) {}

    /** @return array<string, mixed> */
    public function present(Order $order): array
    {
        $order->loadMissing(['shop.city', 'shop.state', 'shop.country', 'items.taxComponents', 'totals']);
        $currency = $this->adminSettings->currencyConfig();
        $itemDiscount = (float) $order->items->sum(fn ($item): float => (float) $item->line_discount);

        return [
            'number' => $order->order_number,
            'date' => app_datetime($order->created_at),
            'status' => str($order->order_status)->headline()->toString(),
            'payment_status' => str($order->payment_status)->headline()->toString(),
            'payment_method' => $this->paymentMethodLabel($order->payment_method),
            'fulfilment' => str($order->fulfilment_type)->headline()->toString(),
            'customer' => array_filter(['name' => $order->customer_name, 'mobile' => $order->customer_mobile, 'email' => $order->customer_email]),
            'address_title' => $order->fulfilment_type === Order::FULFILMENT_DELIVERY ? 'Delivery Address' : 'Pickup Address',
            'address' => $order->fulfilment_type === Order::FULFILMENT_DELIVERY ? $this->deliveryAddress($order) : $this->shopAddress($order),
            'billing_address' => $this->billingAddress($order),
            'items' => $order->items->map(fn ($item): array => [
                'name' => $item->product_name,
                'variant' => $item->variant_name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => $this->money($item->unit_price, $currency),
                'discount' => (float) $item->line_discount > 0 ? $this->money($item->line_discount, $currency) : null,
                'tax' => (float) $item->line_tax > 0 ? $this->money($item->line_tax, $currency) : null,
                'line_total' => $this->money($item->line_total, $currency),
                'promotion' => data_get($item->metadata, 'promotion.name'),
                'coupon' => data_get($item->metadata, 'promotion.coupon_code'),
                'free_gift' => (bool) data_get($item->metadata, 'promotion.details.generated_by_promotion', false),
            ])->all(),
            'totals' => array_values(array_filter([
                ['label' => 'Subtotal', 'value' => $this->money($order->subtotal, $currency), 'show' => true],
                ['label' => 'Item / Offer Discount', 'value' => '-'.$this->money($itemDiscount, $currency), 'show' => $itemDiscount > 0],
                ['label' => 'Order Discount', 'value' => '-'.$this->money($order->order_discount_amount, $currency), 'show' => (float) $order->order_discount_amount > 0],
                ['label' => 'Shipping / Delivery', 'value' => $this->money($order->shipping_total, $currency), 'show' => (float) $order->shipping_total > 0],
                ['label' => 'Tax', 'value' => $this->money($order->tax_total, $currency), 'show' => (float) $order->tax_total > 0],
                ['label' => 'Rounding Adjustment', 'value' => $this->money($order->rounding_adjustment, $currency), 'show' => (float) $order->rounding_adjustment !== 0.0],
            ], fn (array $row): bool => $row['show'])),
            'grand_total' => $this->money($order->grand_total, $currency),
            'payment_reference' => $order->payment_reference ?: $order->upi_txn,
        ];
    }

    private function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY => 'Cash on Delivery',
            StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP => 'Cash at Shop',
            StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI => 'Direct Merchant UPI',
            default => str($method)->headline()->toString(),
        };
    }

    private function deliveryAddress(Order $order): string
    {
        return collect([$order->shipping_recipient_name, $order->shipping_address_line_1, $order->shipping_address_line_2, $order->shipping_landmark, $order->shipping_city, $order->shipping_state, $order->shipping_country, $order->shipping_postal_code])->filter()->implode(', ');
    }

    private function billingAddress(Order $order): string
    {
        return collect([$order->billing_recipient_name, $order->billing_address_line_1, $order->billing_address_line_2, $order->billing_landmark, $order->billing_city, $order->billing_state, $order->billing_country, $order->billing_postal_code])->filter()->implode(', ');
    }

    private function shopAddress(Order $order): string
    {
        return collect([$order->shop?->name, $order->shop?->address_line_1, $order->shop?->address_line_2, $order->shop?->city?->name, $order->shop?->state?->name, $order->shop?->country?->name, $order->shop?->pincode])->filter()->implode(', ');
    }

    /** @param array<string, mixed> $currency */
    private function money(float|int|string $value, array $currency): string
    {
        $amount = number_format((float) $value, (int) ($currency['decimal_places'] ?? 2), (string) ($currency['decimal_separator'] ?? '.'), (string) ($currency['thousands_separator'] ?? ','));
        $symbol = (string) ($currency['symbol'] ?? 'INR ');

        return ($currency['symbol_position'] ?? 'before') === 'before' ? $symbol.$amount : $amount.' '.$symbol;
    }
}
