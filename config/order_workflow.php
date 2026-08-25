<?php

use App\Models\Order;
use App\Models\OrderStatus;

return [
    'fulfillment_status_flow' => [
        Order::FULFILMENT_PICKUP => [
            Order::STATUS_PENDING => [
                Order::STATUS_CONFIRMED,
                Order::STATUS_CANCELLED,
            ],
            Order::STATUS_CONFIRMED => [
                Order::STATUS_PROCESSING,
                Order::STATUS_CANCELLED,
            ],
            Order::STATUS_PROCESSING => [
                Order::STATUS_READY_FOR_PICKUP,
                Order::STATUS_CANCELLED,
            ],
            Order::STATUS_READY_FOR_PICKUP => [
                Order::STATUS_COMPLETED,
            ],
            Order::STATUS_COMPLETED => [],
            Order::STATUS_CANCELLED => [],
        ],

        Order::FULFILMENT_DELIVERY => [
            Order::STATUS_PENDING => [
                Order::STATUS_CONFIRMED,
                Order::STATUS_CANCELLED,
            ],
            Order::STATUS_CONFIRMED => [
                Order::STATUS_PROCESSING,
                Order::STATUS_CANCELLED,
            ],
            Order::STATUS_PROCESSING => [
                OrderStatus::CODE_PACKED,
                Order::STATUS_CANCELLED,
            ],
            OrderStatus::CODE_PACKED => [
                OrderStatus::CODE_SHIPPED,
            ],
            OrderStatus::CODE_SHIPPED => [
                OrderStatus::CODE_OUT_FOR_DELIVERY,
            ],
            OrderStatus::CODE_OUT_FOR_DELIVERY => [
                OrderStatus::CODE_DELIVERED,
            ],
            OrderStatus::CODE_DELIVERED => [
                Order::STATUS_COMPLETED,
            ],
            Order::STATUS_COMPLETED => [],
            Order::STATUS_CANCELLED => [],
        ],
    ],

    'status_action_labels' => [
        Order::STATUS_CONFIRMED => 'Accept Order',
        Order::STATUS_PROCESSING => 'Start Processing',
        Order::STATUS_READY_FOR_PICKUP => 'Mark Ready for Pickup',
        OrderStatus::CODE_PACKED => 'Mark Packed',
        OrderStatus::CODE_SHIPPED => 'Mark Shipped',
        OrderStatus::CODE_OUT_FOR_DELIVERY => 'Mark Out for Delivery',
        OrderStatus::CODE_DELIVERED => 'Mark Delivered',
        Order::STATUS_COMPLETED => 'Complete Order',
        Order::STATUS_CANCELLED => 'Cancel Order',
    ],
];
