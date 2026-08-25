<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryCompletionService
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly OrderCompletionEligibilityService $completionEligibility,
    ) {
    }

    public function markDelivered(Order $order, User $actor, bool $paymentReceived): Order
    {
        return DB::transaction(function () use ($order, $actor, $paymentReceived): Order {
            $order = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanMarkDelivered($order, $paymentReceived);

            $order = $this->orderStatusService->transition(
                $order,
                OrderStatus::CODE_DELIVERED,
                $actor,
                'Order delivered to customer.',
                ['action' => 'merchant_mark_delivered'],
            );

            if ($this->requiresCodPaymentConfirmation($order)) {
                $order->forceFill([
                    'payment_status' => Order::PAYMENT_PAID,
                    'amount_paid' => $order->grand_total,
                    'change_amount' => 0,
                    'updated_by' => $actor->getKey(),
                ])->save();

                $order->statusHistories()->create([
                    'from_status' => OrderStatus::CODE_DELIVERED,
                    'to_status' => OrderStatus::CODE_DELIVERED,
                    'notes' => 'COD payment '.number_format((float) $order->grand_total, 2, '.', '').' received.',
                    'changed_by' => $actor->getKey(),
                    'metadata' => [
                        'action' => 'merchant_cod_payment_received',
                        'payment_method' => 'cash_on_delivery',
                        'payment_collected' => true,
                        'amount_collected' => (string) $order->grand_total,
                    ],
                    'created_at' => now(),
                ]);

                $order = $order->refresh();
            }

            if ($this->completionEligibility->canComplete($order)) {
                $order = $this->orderStatusService->transition(
                    $order,
                    Order::STATUS_COMPLETED,
                    $actor,
                    'Order completed successfully.',
                    [
                        'action' => 'merchant_complete_delivery',
                        'fulfilment_type' => Order::FULFILMENT_DELIVERY,
                        'payment_status' => $order->payment_status,
                    ],
                );
            }

            return $order;
        });
    }

    private function assertCanMarkDelivered(Order $order, bool $paymentReceived): void
    {
        if ($order->fulfilment_type !== Order::FULFILMENT_DELIVERY) {
            throw ValidationException::withMessages([
                'order_status' => 'Only delivery orders can be marked delivered with this action.',
            ]);
        }

        $this->orderStatusService->assertCanTransition($order, OrderStatus::CODE_DELIVERED);

        if ($this->requiresCodPaymentConfirmation($order) && ! $paymentReceived) {
            throw ValidationException::withMessages([
                'payment_received' => 'Confirm that COD payment was received.',
            ]);
        }
    }

    private function requiresCodPaymentConfirmation(Order $order): bool
    {
        return $order->payment_method === 'cash_on_delivery'
            && $order->payment_status !== Order::PAYMENT_PAID;
    }
}
