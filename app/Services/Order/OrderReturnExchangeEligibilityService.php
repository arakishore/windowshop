<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use Illuminate\Support\Carbon;

class OrderReturnExchangeEligibilityService
{
    public const REASON_ELIGIBLE = 'eligible';
    public const REASON_REFUND_NOT_ALLOWED = 'refund_not_allowed';
    public const REASON_EXCHANGE_NOT_ALLOWED = 'exchange_not_allowed';
    public const REASON_WINDOW_NOT_STARTED = 'window_not_started';
    public const REASON_WINDOW_EXPIRED = 'window_expired';
    public const REASON_NO_REMAINING_QUANTITY = 'no_remaining_quantity';
    public const REASON_PICKUP_NOT_ALLOWED_CASH_AT_SHOP = 'pickup_not_allowed_cash_at_shop';
    public const REASON_PICKUP_NOT_ALLOWED_EXPIRED = 'pickup_not_allowed_expired';
    public const REASON_PICKUP_NOT_AVAILABLE = 'reverse_pickup_not_available';

    public function __construct(
        private readonly OrderRefundService $refundService,
        private readonly OrderExchangeService $exchangeService,
    ) {
    }

    /**
     * @return array{
     *     order: array<string, mixed>,
     *     items: array<int, array<string, mixed>>,
     *     return_method: array<string, mixed>
     * }
     */
    public function forOrder(Order $order, ?Carbon $now = null): array
    {
        $order->loadMissing(['items', 'statusHistories']);
        $now ??= now();

        $refundable = $this->refundService->refundableQuantities($order);
        $exchangeable = $this->exchangeService->exchangeableQuantities($order);
        $startsAt = $this->eligibilityStartsAt($order);

        $items = $order->items
            ->mapWithKeys(fn (OrderItem $item): array => [
                $item->getKey() => $this->forItem($order, $item, $refundable, $exchangeable, $startsAt, $now),
            ])
            ->all();

        return [
            'order' => [
                'eligibility_starts_at' => $startsAt,
                'eligibility_start_label' => $startsAt ? app_datetime($startsAt) : null,
            ],
            'items' => $items,
            'return_method' => $this->returnMethod($order, $items),
        ];
    }

    /**
     * @param array<int, array{quantity?: int|string|null}> $submitted
     */
    public function merchantOverrideRequiredForSelected(Order $order, string $type, array $submitted): bool
    {
        $eligibility = $this->forOrder($order);
        $items = $eligibility['items'];
        $key = $type === 'refund' ? 'refund' : 'exchange';

        foreach ($submitted as $itemId => $row) {
            if ((int) ($row['quantity'] ?? 0) < 1) {
                continue;
            }

            $facts = $items[(int) $itemId][$key] ?? null;

            if (! is_array($facts)) {
                continue;
            }

            if ($this->merchantPolicyOverrideRequired($facts)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, int> $refundable
     * @param array<int, int> $exchangeable
     * @return array<string, mixed>
     */
    private function forItem(Order $order, OrderItem $item, array $refundable, array $exchangeable, ?Carbon $startsAt, Carbon $now): array
    {
        return [
            'item_id' => $item->getKey(),
            'refund' => $this->policyFacts(
                $order,
                'refund',
                (bool) $item->refund_allowed,
                (int) $item->refund_window_days,
                (int) ($refundable[$item->getKey()] ?? 0),
                $startsAt,
                $now,
            ),
            'exchange' => $this->policyFacts(
                $order,
                'exchange',
                (bool) $item->exchange_allowed,
                (int) $item->exchange_window_days,
                (int) ($exchangeable[$item->getKey()] ?? 0),
                $startsAt,
                $now,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function policyFacts(Order $order, string $type, bool $allowed, int $windowDays, int $remainingQuantity, ?Carbon $startsAt, Carbon $now): array
    {
        $windowDays = $allowed ? max(0, $windowDays) : 0;
        $expiresAt = $allowed && $startsAt ? $startsAt->copy()->addDays($windowDays) : null;
        $windowExpired = $expiresAt ? $now->greaterThan($expiresAt) : false;
        $reasonCode = self::REASON_ELIGIBLE;
        $reason = ucfirst($type).' is eligible.';

        if (! $allowed) {
            $reasonCode = $type === 'refund' ? self::REASON_REFUND_NOT_ALLOWED : self::REASON_EXCHANGE_NOT_ALLOWED;
            $reason = $type === 'refund' ? 'Refund is not allowed for this item.' : 'Exchange is not allowed for this item.';
        } elseif (! $startsAt) {
            $reasonCode = self::REASON_WINDOW_NOT_STARTED;
            $reason = 'The return/exchange window has not started yet.';
        } elseif ($windowExpired) {
            $reasonCode = self::REASON_WINDOW_EXPIRED;
            $reason = ucfirst($type).' window expired '.$this->expiredByDays($expiresAt, $now).' day(s) ago.';
        } elseif ($remainingQuantity < 1) {
            $reasonCode = self::REASON_NO_REMAINING_QUANTITY;
            $reason = 'No remaining quantity is available for '.$type.'.';
        }

        return [
            'allowed_by_policy' => $allowed,
            'window_days' => $windowDays,
            'window_started' => $startsAt !== null,
            'window_start_at' => $startsAt,
            'window_start_label' => $startsAt ? app_datetime($startsAt) : null,
            'window_expires_at' => $expiresAt,
            'window_expires_label' => $expiresAt ? app_datetime($expiresAt) : null,
            'window_expired' => $windowExpired,
            'expired_by_days' => $windowExpired && $expiresAt ? $this->expiredByDays($expiresAt, $now) : 0,
            'remaining_quantity' => max(0, $remainingQuantity),
            'customer_eligible' => $reasonCode === self::REASON_ELIGIBLE,
            'reason_code' => $reasonCode,
            'reason' => $reason,
            'customer_message' => $this->customerMessage($order, $type, $reasonCode, $expiresAt),
            'merchant_status' => $this->merchantStatus($reasonCode),
            'merchant_message' => $this->merchantMessage($type, $reasonCode),
            'merchant_exception_available' => $this->merchantExceptionAvailable($reasonCode),
            'policy_label' => $this->policyLabel($type, $allowed, $windowDays),
        ];
    }

    private function eligibilityStartsAt(Order $order): ?Carbon
    {
        if ($order->fulfilment_type === Order::FULFILMENT_DELIVERY) {
            $delivered = $order->statusHistories
                ->where('to_status', OrderStatus::CODE_DELIVERED)
                ->sortBy('created_at')
                ->first();

            return $delivered?->created_at;
        }

        if ($order->fulfilment_type === Order::FULFILMENT_PICKUP) {
            $pickup = $order->statusHistories
                ->filter(fn ($history): bool => ($history->metadata['action'] ?? null) === 'merchant_complete_pickup')
                ->sortBy('created_at')
                ->first();

            if ($pickup) {
                return $pickup->created_at;
            }
        }

        if ($order->order_status === Order::STATUS_COMPLETED) {
            return $order->completed_at ?: $order->created_at;
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function returnMethod(Order $order, array $items): array
    {
        $hasCustomerEligiblePolicy = collect($items)->contains(
            fn (array $item): bool => (bool) ($item['refund']['customer_eligible'] ?? false)
                || (bool) ($item['exchange']['customer_eligible'] ?? false),
        );
        $hasExpiredPolicy = collect($items)->contains(
            fn (array $item): bool => (bool) ($item['refund']['window_expired'] ?? false)
                || (bool) ($item['exchange']['window_expired'] ?? false),
        );

        if ($order->payment_method === 'cash_at_shop') {
            return [
                'shop_visit_allowed' => true,
                'pickup_allowed' => false,
                'pickup_policy_eligible' => false,
                'pickup_reason' => self::REASON_PICKUP_NOT_ALLOWED_CASH_AT_SHOP,
                'customer_message' => $this->cashAtShopCustomerMessage($items),
                'merchant_message' => 'Shop Visit Only. Cash at Shop orders do not support pickup.',
            ];
        }

        if ($hasExpiredPolicy && ! $hasCustomerEligiblePolicy) {
            return [
                'shop_visit_allowed' => true,
                'pickup_allowed' => false,
                'pickup_policy_eligible' => false,
                'pickup_reason' => self::REASON_PICKUP_NOT_ALLOWED_EXPIRED,
                'customer_message' => 'Your online return/exchange window has expired. You may contact or visit the shop for assistance.',
                'merchant_message' => 'Pickup is not available because the customer self-service window expired.',
            ];
        }

        return [
            'shop_visit_allowed' => true,
            'pickup_allowed' => false,
            'pickup_policy_eligible' => $order->payment_method === 'cash_on_delivery' && $hasCustomerEligiblePolicy,
            'pickup_reason' => self::REASON_PICKUP_NOT_AVAILABLE,
            'customer_message' => $hasCustomerEligiblePolicy
                ? 'This order has item(s) within the customer return/exchange policy window.'
                : 'Contact or visit the shop for return or exchange assistance.',
            'merchant_message' => $hasCustomerEligiblePolicy
                ? 'Customer policy is active. Future pickup eligibility is available for compatible flows.'
                : 'Merchant may still process an exception where appropriate.',
        ];
    }

    private function customerMessage(Order $order, string $type, string $reasonCode, ?Carbon $expiresAt): string
    {
        return match ($reasonCode) {
            self::REASON_ELIGIBLE => ucfirst($type).' available until '.app_datetime($expiresAt).'.',
            self::REASON_REFUND_NOT_ALLOWED => 'Refund is not available for this item.',
            self::REASON_EXCHANGE_NOT_ALLOWED => 'Exchange is not available for this item.',
            self::REASON_WINDOW_NOT_STARTED => $order->fulfilment_type === Order::FULFILMENT_DELIVERY
                ? 'The return/exchange window will start after delivery.'
                : 'The return/exchange window will start after the order is collected.',
            self::REASON_WINDOW_EXPIRED => 'The online '.$type.' window has expired.',
            self::REASON_NO_REMAINING_QUANTITY => 'No remaining quantity is available for '.$type.'.',
            default => ucfirst($type).' is not currently available.',
        };
    }

    private function merchantStatus(string $reasonCode): string
    {
        return match ($reasonCode) {
            self::REASON_ELIGIBLE => 'Eligible',
            self::REASON_WINDOW_EXPIRED => 'Expired',
            self::REASON_WINDOW_NOT_STARTED => 'Not Started',
            self::REASON_NO_REMAINING_QUANTITY => 'No Remaining Quantity',
            default => 'Not Eligible',
        };
    }

    private function merchantMessage(string $type, string $reasonCode): string
    {
        $label = $type === 'refund' ? 'refund' : 'exchange';

        return match ($reasonCode) {
            self::REASON_ELIGIBLE => 'This item is within the purchased '.$label.' policy.',
            self::REASON_REFUND_NOT_ALLOWED => 'Shop policy does not allow a refund, but you may approve one as an exception.',
            self::REASON_EXCHANGE_NOT_ALLOWED => 'Shop policy does not allow an exchange, but you may approve one as an exception.',
            self::REASON_WINDOW_EXPIRED => ucfirst($label).' window has expired, but you may approve one as an exception.',
            self::REASON_WINDOW_NOT_STARTED => ucfirst($label).' window has not started yet.',
            self::REASON_NO_REMAINING_QUANTITY => 'No remaining quantity is available for '.$label.'.',
            default => ucfirst($label).' is not currently eligible.',
        };
    }

    private function merchantExceptionAvailable(string $reasonCode): bool
    {
        return in_array($reasonCode, [
            self::REASON_REFUND_NOT_ALLOWED,
            self::REASON_EXCHANGE_NOT_ALLOWED,
            self::REASON_WINDOW_EXPIRED,
        ], true);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function cashAtShopCustomerMessage(array $items): string
    {
        $refundEligible = collect($items)->contains(fn (array $item): bool => (bool) ($item['refund']['customer_eligible'] ?? false));
        $exchangeEligible = collect($items)->contains(fn (array $item): bool => (bool) ($item['exchange']['customer_eligible'] ?? false));

        if ($refundEligible && $exchangeEligible) {
            return 'Visit the shop for return or exchange handling.';
        }

        if ($refundEligible) {
            return 'Visit the shop for refund handling.';
        }

        if ($exchangeEligible) {
            return 'Visit the shop for exchange handling.';
        }

        return 'You may contact or visit the shop for assistance.';
    }

    /**
     * @param array<string, mixed> $facts
     */
    private function merchantPolicyOverrideRequired(array $facts): bool
    {
        return in_array($facts['reason_code'] ?? null, [
            self::REASON_REFUND_NOT_ALLOWED,
            self::REASON_EXCHANGE_NOT_ALLOWED,
            self::REASON_WINDOW_NOT_STARTED,
            self::REASON_WINDOW_EXPIRED,
        ], true);
    }

    private function policyLabel(string $type, bool $allowed, int $windowDays): string
    {
        if (! $allowed) {
            return $type === 'refund' ? 'No Refund' : 'No Exchange';
        }

        return ucfirst($type).' within '.$windowDays.' day'.($windowDays === 1 ? '' : 's');
    }

    private function expiredByDays(Carbon $expiresAt, Carbon $now): int
    {
        return max(1, (int) ceil(max(0, $now->getTimestamp() - $expiresAt->getTimestamp()) / 86400));
    }
}
