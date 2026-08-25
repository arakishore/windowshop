<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
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
            $this->assertCanTransition($order, $toStatus);

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
