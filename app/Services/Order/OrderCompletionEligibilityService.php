<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderStatus;

class OrderCompletionEligibilityService
{
    public function canComplete(Order $order): bool
    {
        if ($order->fulfilment_type === Order::FULFILMENT_DELIVERY) {
            return $order->order_status === OrderStatus::CODE_DELIVERED
                && $this->paymentResolved($order);
        }

        return false;
    }

    private function paymentResolved(Order $order): bool
    {
        return $order->payment_status === Order::PAYMENT_PAID;
    }
}
