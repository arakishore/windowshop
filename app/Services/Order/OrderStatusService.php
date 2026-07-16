<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $allowedTransitions = [
        Order::STATUS_PENDING => [Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, Order::STATUS_COMPLETED, Order::STATUS_CANCELLED],
        Order::STATUS_CONFIRMED => [Order::STATUS_PROCESSING, Order::STATUS_READY_FOR_PICKUP, Order::STATUS_COMPLETED, Order::STATUS_CANCELLED],
        Order::STATUS_PROCESSING => [Order::STATUS_READY_FOR_PICKUP, Order::STATUS_COMPLETED, Order::STATUS_CANCELLED],
        Order::STATUS_READY_FOR_PICKUP => [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED],
        Order::STATUS_COMPLETED => [Order::STATUS_CANCELLED],
        Order::STATUS_CANCELLED => [],
    ];

    public function recordInitial(Order $order, string $status, ?User $actor = null, ?string $notes = null, ?array $metadata = null): void
    {
        $order->statusHistories()->create([
            'from_status' => null,
            'to_status' => $status,
            'notes' => $notes,
            'changed_by' => $actor?->getKey(),
            'metadata' => $metadata,
        ]);
    }

    public function transition(Order $order, string $toStatus, ?User $actor = null, ?string $notes = null, ?array $metadata = null): Order
    {
        return DB::transaction(function () use ($order, $toStatus, $actor, $notes, $metadata): Order {
            $fromStatus = $order->order_status;
            $this->assertTransitionAllowed($fromStatus, $toStatus);

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
            $order->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'notes' => $notes,
                'changed_by' => $actor?->getKey(),
                'metadata' => $metadata,
            ]);

            return $order->refresh();
        });
    }

    private function assertTransitionAllowed(string $fromStatus, string $toStatus): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        if (! in_array($toStatus, $this->allowedTransitions[$fromStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'order_status' => "Cannot change order status from {$fromStatus} to {$toStatus}.",
            ]);
        }
    }
}
