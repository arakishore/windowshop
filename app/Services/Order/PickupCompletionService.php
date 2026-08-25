<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PickupCompletionService
{
    public function __construct(private readonly OrderStatusService $orderStatusService)
    {
    }

    public function complete(Order $order, User $actor, bool $paymentReceived): Order
    {
        return DB::transaction(function () use ($order, $actor, $paymentReceived): Order {
            $order = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPickupCanComplete($order, $paymentReceived);

            $paymentCollected = false;

            if ($order->payment_status !== Order::PAYMENT_PAID) {
                $order->forceFill([
                    'payment_status' => Order::PAYMENT_PAID,
                    'amount_paid' => $order->grand_total,
                    'change_amount' => 0,
                    'updated_by' => $actor->getKey(),
                ])->save();
                $paymentCollected = true;
            }

            return $this->orderStatusService->transition(
                $order,
                Order::STATUS_COMPLETED,
                $actor,
                'Customer collected the order from the shop.',
                [
                    'action' => 'merchant_complete_pickup',
                    'fulfilment_type' => Order::FULFILMENT_PICKUP,
                    'payment_method' => $order->payment_method,
                    'payment_collected' => $paymentCollected,
                ],
            );
        });
    }

    private function assertPickupCanComplete(Order $order, bool $paymentReceived): void
    {
        if ($order->fulfilment_type !== Order::FULFILMENT_PICKUP) {
            throw ValidationException::withMessages([
                'order_status' => 'Only pickup orders can be completed with this action.',
            ]);
        }

        $this->orderStatusService->assertCanTransition($order, Order::STATUS_COMPLETED);

        if ($order->payment_status === Order::PAYMENT_PAID) {
            return;
        }

        if ($order->payment_method !== 'cash_at_shop') {
            throw ValidationException::withMessages([
                'payment_status' => 'Payment must be paid before completing pickup.',
            ]);
        }

        if (! $paymentReceived) {
            throw ValidationException::withMessages([
                'payment_received' => 'Confirm that Cash at Shop payment was received.',
            ]);
        }
    }
}
