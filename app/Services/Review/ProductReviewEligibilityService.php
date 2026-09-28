<?php

namespace App\Services\Review;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;

class ProductReviewEligibilityService
{
    public function eligible(Customer $customer, OrderItem $item): bool
    {
        $item->loadMissing('order');

        return $item->product_id !== null
            && $item->order instanceof Order
            && (int) $item->order->customer_id === (int) $customer->getKey()
            && $item->order->order_status === Order::STATUS_COMPLETED;
    }

    public function authorize(Customer $customer, OrderItem $item): void
    {
        abort_unless($this->eligible($customer, $item), 403);
    }
}
