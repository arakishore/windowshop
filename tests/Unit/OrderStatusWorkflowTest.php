<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\Order\OrderStatusService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderStatusWorkflowTest extends TestCase
{
    public function test_pickup_workflow_allows_only_valid_next_statuses(): void
    {
        $service = new OrderStatusService();

        $pending = $this->order(Order::FULFILMENT_PICKUP, Order::STATUS_PENDING);
        $this->assertSame([Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED], $service->allowedNextStatuses($pending));
        $this->assertTrue($service->canTransition($pending, Order::STATUS_CONFIRMED));
        $this->assertTrue($service->canTransition($pending, Order::STATUS_CANCELLED));
        $this->assertFalse($service->canTransition($pending, OrderStatus::CODE_SHIPPED));

        $processing = $this->order(Order::FULFILMENT_PICKUP, Order::STATUS_PROCESSING);
        $this->assertSame([Order::STATUS_READY_FOR_PICKUP, Order::STATUS_CANCELLED], $service->allowedNextStatuses($processing));
        $this->assertTrue($service->canTransition($processing, Order::STATUS_READY_FOR_PICKUP));
        $this->assertTrue($service->canTransition($processing, Order::STATUS_CANCELLED));
        $this->assertFalse($service->canTransition($processing, OrderStatus::CODE_PACKED));

        $ready = $this->order(Order::FULFILMENT_PICKUP, Order::STATUS_READY_FOR_PICKUP);
        $this->assertSame([Order::STATUS_COMPLETED], $service->allowedNextStatuses($ready));
        $this->assertTrue($service->canTransition($ready, Order::STATUS_COMPLETED));
        $this->assertFalse($service->canTransition($ready, Order::STATUS_CANCELLED));
    }

    public function test_delivery_workflow_allows_only_valid_next_statuses(): void
    {
        $service = new OrderStatusService();

        $pending = $this->order(Order::FULFILMENT_DELIVERY, Order::STATUS_PENDING);
        $this->assertSame([Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED], $service->allowedNextStatuses($pending));
        $this->assertTrue($service->canTransition($pending, Order::STATUS_CONFIRMED));
        $this->assertTrue($service->canTransition($pending, Order::STATUS_CANCELLED));
        $this->assertFalse($service->canTransition($pending, OrderStatus::CODE_DELIVERED));

        $processing = $this->order(Order::FULFILMENT_DELIVERY, Order::STATUS_PROCESSING);
        $this->assertSame([OrderStatus::CODE_PACKED, Order::STATUS_CANCELLED], $service->allowedNextStatuses($processing));
        $this->assertTrue($service->canTransition($processing, OrderStatus::CODE_PACKED));
        $this->assertTrue($service->canTransition($processing, Order::STATUS_CANCELLED));
        $this->assertFalse($service->canTransition($processing, Order::STATUS_READY_FOR_PICKUP));

        $packed = $this->order(Order::FULFILMENT_DELIVERY, OrderStatus::CODE_PACKED);
        $this->assertSame([OrderStatus::CODE_SHIPPED], $service->allowedNextStatuses($packed));
        $this->assertTrue($service->canTransition($packed, OrderStatus::CODE_SHIPPED));
        $this->assertFalse($service->canTransition($packed, Order::STATUS_CANCELLED));

        $shipped = $this->order(Order::FULFILMENT_DELIVERY, OrderStatus::CODE_SHIPPED);
        $this->assertSame([OrderStatus::CODE_OUT_FOR_DELIVERY], $service->allowedNextStatuses($shipped));

        $outForDelivery = $this->order(Order::FULFILMENT_DELIVERY, OrderStatus::CODE_OUT_FOR_DELIVERY);
        $this->assertSame([OrderStatus::CODE_DELIVERED], $service->allowedNextStatuses($outForDelivery));

        $delivered = $this->order(Order::FULFILMENT_DELIVERY, OrderStatus::CODE_DELIVERED);
        $this->assertSame([Order::STATUS_COMPLETED], $service->allowedNextStatuses($delivered));
    }

    public function test_terminal_and_non_normal_statuses_have_no_generic_next_statuses(): void
    {
        $service = new OrderStatusService();

        foreach ([Order::STATUS_COMPLETED, Order::STATUS_CANCELLED, OrderStatus::CODE_RETURN_REQUESTED, OrderStatus::CODE_EXCHANGED, OrderStatus::CODE_PARTIALLY_CANCELLED, OrderStatus::CODE_FAILED] as $status) {
            $order = $this->order(Order::FULFILMENT_DELIVERY, $status);
            $this->assertSame([], $service->allowedNextStatuses($order));
            $this->assertFalse($service->canTransition($order, Order::STATUS_CONFIRMED));
        }
    }

    public function test_invalid_transition_assertion_throws_validation_exception(): void
    {
        $service = new OrderStatusService();
        $order = $this->order(Order::FULFILMENT_DELIVERY, Order::STATUS_PROCESSING);

        $this->expectException(ValidationException::class);

        $service->assertCanTransition($order, Order::STATUS_READY_FOR_PICKUP);
    }

    private function order(string $fulfillment, string $status): Order
    {
        return new Order([
            'fulfilment_type' => $fulfillment,
            'order_status' => $status,
        ]);
    }
}
