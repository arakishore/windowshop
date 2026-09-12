<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\User;
use App\Services\Promotion\Redemptions\CouponRedemptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    public function __construct(
        private readonly ?CouponRedemptionService $couponRedemptions = null,
    ) {
    }

    public function recordInitial(Order $order, string $status, ?User $actor = null, ?string $notes = null, ?array $metadata = null): void
    {
        $order->statusHistories()->create([
            'from_status' => null,
            'to_status' => $status,
            'notes' => $notes,
            'changed_by' => $actor?->getKey(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public function transition(Order $order, string $toStatus, ?User $actor = null, ?string $notes = null, ?array $metadata = null): Order
    {
        return $this->transitionUsing($order, $toStatus, $actor, $notes, $metadata, function () use ($order, $toStatus): void {
            $this->assertCanTransition($order, $toStatus);
        });
    }

    public function transitionForCustomerCancellation(Order $order, ?User $actor = null, ?string $notes = null, ?array $metadata = null): Order
    {
        return $this->transitionUsing($order, Order::STATUS_CANCELLED, $actor, $notes, $metadata, function () use ($order): void {
            $this->assertCanCustomerCancel($order);
        });
    }

    public function canCustomerCancel(Order $order): bool
    {
        $allowedStatuses = (array) config("order_workflow.customer_cancellation.allowed_order_statuses.{$order->fulfilment_type}", []);

        return in_array($order->order_status, $allowedStatuses, true);
    }

    public function assertCanCustomerCancel(Order $order): void
    {
        if (! $this->canCustomerCancel($order)) {
            throw ValidationException::withMessages([
                'order_status' => 'This order cannot be cancelled from the customer account.',
            ]);
        }
    }

    /**
     * @param callable(): void $assertAllowed
     */
    private function transitionUsing(Order $order, string $toStatus, ?User $actor, ?string $notes, ?array $metadata, callable $assertAllowed): Order
    {
        return DB::transaction(function () use ($order, $toStatus, $actor, $notes, $metadata, $assertAllowed): Order {
            $fromStatus = $order->order_status;
            $assertAllowed();

            $changes = [
                'order_status' => $toStatus,
                'updated_by' => $actor?->getKey(),
            ];

            if ($toStatus === Order::STATUS_COMPLETED) {
                $changes['completed_at'] = now();
            }

            if ($toStatus === Order::STATUS_CANCELLED) {
                $changes['cancelled_at'] = now();
            }

            $order->forceFill($changes)->save();

            if ($toStatus === Order::STATUS_CANCELLED) {
                ($this->couponRedemptions ?? app(CouponRedemptionService::class))->cancelForOrder($order);
            }

            $order->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'notes' => $notes,
                'changed_by' => $actor?->getKey(),
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            return $order->refresh();
        });
    }

    /**
     * @return array<int, string>
     */
    public function allowedNextStatuses(Order $order): array
    {
        $flow = $this->fulfillmentStatusFlow();

        return $flow[$order->fulfilment_type][$order->order_status] ?? [];
    }

    public function canTransition(Order $order, string $toStatus): bool
    {
        return in_array($toStatus, $this->allowedNextStatuses($order), true);
    }

    public function assertCanTransition(Order $order, string $toStatus): void
    {
        if (! $this->canTransition($order, $toStatus)) {
            throw ValidationException::withMessages([
                'order_status' => "Cannot change order status from {$order->order_status} to {$toStatus}.",
            ]);
        }
    }

    /**
     * @return array<string, array<string, array<int, string>>>
     */
    private function fulfillmentStatusFlow(): array
    {
        return (array) config('order_workflow.fulfillment_status_flow', []);
    }
}
