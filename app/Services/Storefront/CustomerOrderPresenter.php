<?php

namespace App\Services\Storefront;

use App\Models\Order;
use App\Models\OrderComment;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Services\Admin\AdminSettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerOrderPresenter
{
    private const FALLBACK_IMAGE = 'assets/storefront/images/no-image-icon.png';

    public function __construct(
        private readonly AdminSettingsService $settings,
        private readonly StorefrontUrlService $urls,
    ) {
    }

    public function money(float|int|string|null $value): string
    {
        $currency = $this->settings->currencyConfig();
        $amount = number_format(
            (float) ($value ?? 0),
            (int) ($currency['decimal_places'] ?? 2),
            (string) ($currency['decimal_separator'] ?? '.'),
            (string) ($currency['thousands_separator'] ?? ','),
        );
        $symbol = (string) ($currency['symbol'] ?? 'INR ');

        return ($currency['symbol_position'] ?? 'before') === 'before'
            ? $symbol.$amount
            : $amount.' '.$symbol;
    }

    /**
     * @return array<string, string>
     */
    public function paymentMethods(): array
    {
        return [
            Order::PAYMENT_METHOD_CASH => 'Cash',
            Order::PAYMENT_METHOD_CARD => 'Card',
            Order::PAYMENT_METHOD_UPI => 'UPI',
            Order::PAYMENT_METHOD_CREDIT => 'Credit',
            'cash_on_delivery' => 'Cash on Delivery',
            'cash_at_shop' => 'Cash at Shop',
            'merchant_upi' => 'UPI',
            'online_payment' => 'Online Payment',
            Order::PAYMENT_METHOD_WALLET => 'Wallet',
            Order::PAYMENT_METHOD_OTHER => 'Other',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function fulfilmentTypes(): array
    {
        return [
            Order::FULFILMENT_DELIVERY => 'Delivery',
            Order::FULFILMENT_PICKUP => 'Pickup',
            Order::FULFILMENT_COUNTER => 'Counter',
        ];
    }

    public function statusLabel(?string $code): string
    {
        $status = $this->orderStatuses()[$code ?? ''] ?? null;

        return $status instanceof OrderStatus
            ? ($status->customer_label ?: $status->name)
            : Str::headline((string) ($code ?: 'unknown'));
    }

    public function statusClass(?string $code): string
    {
        $status = $this->orderStatuses()[$code ?? ''] ?? null;

        return $this->badgeClass($status?->badge_type);
    }

    public function paymentStatusLabel(?string $code): string
    {
        $status = $this->paymentStatuses()[$code ?? ''] ?? null;

        return $status instanceof PaymentStatus
            ? $status->name
            : Str::headline((string) ($code ?: 'unknown'));
    }

    public function paymentStatusClass(?string $code): string
    {
        $status = $this->paymentStatuses()[$code ?? ''] ?? null;

        return $this->badgeClass($status?->badge_type);
    }

    public function label(array $map, ?string $code): string
    {
        return $code ? ($map[$code] ?? Str::headline($code)) : '-';
    }

    /**
     * @return array<int, array{code: string, label: string, state: string, timestamp: string|null}>
     */
    public function progress(Order $order): array
    {
        $steps = $this->progressSteps($order);
        $codes = array_keys($steps);
        $currentIndex = array_search($order->order_status, $codes, true);
        $historyByStatus = $order->statusHistories
            ->sortBy('created_at')
            ->filter(fn ($history): bool => in_array($history->to_status, $codes, true))
            ->reject(fn ($history): bool => ($history->metadata['action'] ?? null) === 'merchant_cod_payment_received')
            ->keyBy('to_status');

        return collect($steps)
            ->map(function (string $label, string $code) use ($codes, $currentIndex, $historyByStatus): array {
                $stepIndex = array_search($code, $codes, true);
                $state = 'future';

                if ($currentIndex !== false) {
                    if ($stepIndex < $currentIndex || ($stepIndex === $currentIndex && $code === Order::STATUS_COMPLETED)) {
                        $state = 'complete';
                    } elseif ($stepIndex === $currentIndex) {
                        $state = 'current';
                    }
                }

                $history = $historyByStatus->get($code);

                return [
                    'code' => $code,
                    'label' => $label,
                    'state' => $state,
                    'timestamp' => $history ? app_datetime($history->created_at) : null,
                ];
            })
            ->values()
            ->all();
    }

    public function imageUrl(OrderItem $item): string
    {
        $path = $item->product_image ?: $item->product?->primaryImage?->thumbnail_path ?: $item->product?->primaryImage?->image_path;

        if ($path && Str::startsWith($path, ['http://', 'https://', 'assets/'])) {
            return $path;
        }

        if ($path && Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        return asset(self::FALLBACK_IMAGE);
    }

    public function productUrl(OrderItem $item): ?string
    {
        return $item->product?->slug
            ? $this->urls->product($item->product)
            : null;
    }

    /**
     * @return array<int, string>
     */
    public function shippingLines(Order $order): array
    {
        return $this->addressLines([
            $order->shipping_recipient_name,
            trim((string) $order->shipping_mobile_country_code.' '.(string) $order->shipping_mobile),
            $order->shipping_address_line_1,
            $order->shipping_address_line_2,
            $order->shipping_landmark,
            $this->compactLocation([$order->shipping_city, $order->shipping_state, $order->shipping_country, $order->shipping_postal_code]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function billingLines(Order $order): array
    {
        return $this->addressLines([
            $order->billing_recipient_name,
            trim((string) $order->billing_mobile_country_code.' '.(string) $order->billing_mobile),
            $order->billing_address_line_1,
            $order->billing_address_line_2,
            $order->billing_landmark,
            $this->compactLocation([$order->billing_city, $order->billing_state, $order->billing_country, $order->billing_postal_code]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function pickupLines(Order $order): array
    {
        $shop = $order->shop;
        $lines = $this->addressLines([
            $shop?->name,
            $shop?->address_line_1,
            $shop?->address_line_2,
            $shop?->landmark,
            $this->compactLocation([$shop?->city?->name, $shop?->state?->name, $shop?->country?->name, $shop?->pincode]),
        ]);
        $instructions = trim((string) ($shop?->setting('fulfillment', 'pickup_instructions', '') ?? ''));

        if ($instructions !== '') {
            $lines[] = $instructions;
        }

        return $lines;
    }

    public function billingSameAsShipping(Order $order): bool
    {
        $shippingLines = $this->shippingLines($order);
        $billingLines = $this->billingLines($order);

        return $shippingLines !== [] && $shippingLines === $billingLines;
    }

    /**
     * @return Collection<int, OrderComment>
     */
    public function customerVisibleComments(Order $order): Collection
    {
        return $order->comments
            ->where('visibility', OrderComment::VISIBILITY_CUSTOMER)
            ->sortBy('created_at')
            ->values();
    }

    /**
     * @return Collection<int, array{type: string, tone: string, timestamp: \Illuminate\Support\Carbon|null, display_time: string, title: string, description: string|null}>
     */
    public function activity(Order $order): Collection
    {
        $statusItems = $order->statusHistories
            ->sortBy('created_at')
            ->map(fn ($history): array => [
                'type' => 'status',
                'tone' => $this->activityStatusTone($history->to_status),
                'timestamp' => $history->created_at,
                'display_time' => app_datetime($history->created_at),
                'title' => $this->activityStatusLabel($history->to_status),
                'description' => $this->activityStatusDescription($history),
            ]);

        $commentItems = $this->customerVisibleComments($order)
            ->map(fn (OrderComment $comment): array => [
                'type' => 'comment',
                'tone' => 'message',
                'timestamp' => $comment->created_at,
                'display_time' => app_datetime($comment->created_at),
                'title' => 'Message from '.($order->shop?->name ?? 'the shop'),
                'description' => $comment->comment,
            ]);

        return $statusItems
            ->merge($commentItems)
            ->sortBy(fn (array $activity): string => optional($activity['timestamp'])->format('Y-m-d H:i:s.u') ?? '')
            ->values();
    }

    public function quantityLabel(int $quantity): string
    {
        return $quantity.' '.Str::plural('Item', $quantity);
    }

    public function balance(Order $order): float
    {
        return max(0, (float) $order->grand_total - (float) $order->amount_paid);
    }

    /**
     * @return array<string, OrderStatus>
     */
    private function orderStatuses(): array
    {
        static $statuses = null;

        if ($statuses === null) {
            $statuses = OrderStatus::query()
                ->active()
                ->customerVisible()
                ->ordered()
                ->get()
                ->keyBy('code')
                ->all();
        }

        return $statuses;
    }

    private function activityStatusLabel(?string $code): string
    {
        return $code === Order::STATUS_PENDING
            ? 'Order Placed'
            : $this->statusLabel($code);
    }

    private function activityStatusDescription($history): ?string
    {
        $code = $history->to_status;
        $status = $this->orderStatuses()[$code ?? ''] ?? null;
        $description = null;

        if ($status instanceof OrderStatus && filled($status->customer_description)) {
            $description = $status->customer_description;
        }

        $description ??= match ($code) {
            Order::STATUS_PENDING => 'Your order has been placed.',
            Order::STATUS_CONFIRMED => 'Your order has been confirmed by the shop.',
            Order::STATUS_PROCESSING => 'Your order is being prepared.',
            Order::STATUS_READY_FOR_PICKUP => 'Your order is ready for pickup.',
            Order::STATUS_COMPLETED => 'Your order has been completed.',
            Order::STATUS_CANCELLED => 'Your order has been cancelled.',
            default => null,
        };

        if ($code === Order::STATUS_CANCELLED
            && ($history->metadata['action'] ?? null) === 'customer_cancel'
            && ($history->metadata['customer_safe_reason'] ?? false)
            && filled($history->metadata['reason_name'] ?? null)) {
            $reason = (string) $history->metadata['reason_name'];
            $description = trim((string) $description).' Reason: '.$reason.'.';
        }

        return $description;
    }

    private function activityStatusTone(?string $code): string
    {
        return match ($code) {
            Order::STATUS_COMPLETED,
            OrderStatus::CODE_DELIVERED => 'success',
            Order::STATUS_CANCELLED,
            OrderStatus::CODE_FAILED,
            OrderStatus::CODE_RETURN_REJECTED,
            OrderStatus::CODE_EXCHANGE_REJECTED => 'danger',
            default => 'neutral',
        };
    }

    /**
     * @return array<string, PaymentStatus>
     */
    private function paymentStatuses(): array
    {
        static $statuses = null;

        if ($statuses === null) {
            $statuses = PaymentStatus::query()
                ->active()
                ->ordered()
                ->get()
                ->keyBy('code')
                ->all();
        }

        return $statuses;
    }

    /**
     * @return array<string, string>
     */
    private function progressSteps(Order $order): array
    {
        if ($order->fulfilment_type === Order::FULFILMENT_DELIVERY) {
            return [
                Order::STATUS_PENDING => 'Order Placed',
                Order::STATUS_CONFIRMED => 'Confirmed',
                Order::STATUS_PROCESSING => 'Processing',
                OrderStatus::CODE_PACKED => 'Packed',
                OrderStatus::CODE_SHIPPED => 'Shipped',
                OrderStatus::CODE_OUT_FOR_DELIVERY => 'Out for Delivery',
                OrderStatus::CODE_DELIVERED => 'Delivered',
                Order::STATUS_COMPLETED => 'Completed',
            ];
        }

        return [
            Order::STATUS_PENDING => 'Order Placed',
            Order::STATUS_CONFIRMED => 'Confirmed',
            Order::STATUS_PROCESSING => 'Processing',
            Order::STATUS_READY_FOR_PICKUP => 'Ready for Pickup',
            Order::STATUS_COMPLETED => 'Completed',
        ];
    }

    private function badgeClass(?string $type): string
    {
        return match ($type) {
            OrderStatus::BADGE_SUCCESS, PaymentStatus::BADGE_SUCCESS => 'account-status-success',
            OrderStatus::BADGE_DANGER, PaymentStatus::BADGE_DANGER => 'account-status-danger',
            OrderStatus::BADGE_WARNING, PaymentStatus::BADGE_WARNING => 'account-status-warning',
            OrderStatus::BADGE_INFO, PaymentStatus::BADGE_INFO => 'account-status-info',
            OrderStatus::BADGE_PRIMARY, PaymentStatus::BADGE_PRIMARY => 'account-status-primary',
            default => 'account-status-secondary',
        };
    }

    /**
     * @param array<int, mixed> $parts
     * @return array<int, string>
     */
    private function addressLines(array $parts): array
    {
        return collect($parts)
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();
    }

    /**
     * @param array<int, mixed> $parts
     */
    private function compactLocation(array $parts): string
    {
        return collect($parts)
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->implode(', ');
    }
}
