<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\Order\OrderStatusService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderStatusWorkflowTest extends TestCase
{
    public function test_pickup_workflow_matches_configured_v1_flow(): void
    {
        $service = new OrderStatusService();

        $this->assertAllowed($service, Order::FULFILMENT_PICKUP, Order::STATUS_PENDING, [
            Order::STATUS_CONFIRMED,
            Order::STATUS_CANCELLED,
        ]);
        $this->assertAllowed($service, Order::FULFILMENT_PICKUP, Order::STATUS_CONFIRMED, [
            Order::STATUS_PROCESSING,
            Order::STATUS_CANCELLED,
        ]);
        $this->assertAllowed($service, Order::FULFILMENT_PICKUP, Order::STATUS_PROCESSING, [
            Order::STATUS_READY_FOR_PICKUP,
            Order::STATUS_CANCELLED,
        ]);
        $this->assertAllowed($service, Order::FULFILMENT_PICKUP, Order::STATUS_READY_FOR_PICKUP, [
            Order::STATUS_CANCELLED,
            Order::STATUS_COMPLETED,
        ]);
        $this->assertTerminal($service, Order::FULFILMENT_PICKUP, Order::STATUS_COMPLETED);
        $this->assertTerminal($service, Order::FULFILMENT_PICKUP, Order::STATUS_CANCELLED);

        $processing = $this->order(Order::FULFILMENT_PICKUP, Order::STATUS_PROCESSING);
        $this->assertFalse($service->canTransition($processing, OrderStatus::CODE_PACKED));
        $this->assertFalse($service->canTransition($processing, OrderStatus::CODE_SHIPPED));
        $this->assertFalse($service->canTransition($processing, OrderStatus::CODE_OUT_FOR_DELIVERY));
    }

    public function test_delivery_workflow_matches_configured_v1_flow(): void
    {
        $service = new OrderStatusService();

        $this->assertAllowed($service, Order::FULFILMENT_DELIVERY, Order::STATUS_PENDING, [
            Order::STATUS_CONFIRMED,
            Order::STATUS_CANCELLED,
        ]);
        $this->assertAllowed($service, Order::FULFILMENT_DELIVERY, Order::STATUS_CONFIRMED, [
            Order::STATUS_PROCESSING,
            Order::STATUS_CANCELLED,
        ]);
        $this->assertAllowed($service, Order::FULFILMENT_DELIVERY, Order::STATUS_PROCESSING, [
            OrderStatus::CODE_PACKED,
            Order::STATUS_CANCELLED,
        ]);
        $this->assertAllowed($service, Order::FULFILMENT_DELIVERY, OrderStatus::CODE_PACKED, [
            OrderStatus::CODE_SHIPPED,
        ]);
        $this->assertAllowed($service, Order::FULFILMENT_DELIVERY, OrderStatus::CODE_SHIPPED, [
            OrderStatus::CODE_OUT_FOR_DELIVERY,
        ]);
        $this->assertAllowed($service, Order::FULFILMENT_DELIVERY, OrderStatus::CODE_OUT_FOR_DELIVERY, [
            OrderStatus::CODE_DELIVERED,
        ]);
        $this->assertAllowed($service, Order::FULFILMENT_DELIVERY, OrderStatus::CODE_DELIVERED, [
            Order::STATUS_COMPLETED,
        ]);
        $this->assertTerminal($service, Order::FULFILMENT_DELIVERY, Order::STATUS_COMPLETED);
        $this->assertTerminal($service, Order::FULFILMENT_DELIVERY, Order::STATUS_CANCELLED);

        $processing = $this->order(Order::FULFILMENT_DELIVERY, Order::STATUS_PROCESSING);
        $this->assertFalse($service->canTransition($processing, Order::STATUS_READY_FOR_PICKUP));
        $this->assertFalse($service->canTransition($processing, OrderStatus::CODE_SHIPPED));

        $packed = $this->order(Order::FULFILMENT_DELIVERY, OrderStatus::CODE_PACKED);
        $this->assertFalse($service->canTransition($packed, OrderStatus::CODE_OUT_FOR_DELIVERY));
        $this->assertFalse($service->canTransition($packed, OrderStatus::CODE_DELIVERED));
        $this->assertFalse($service->canTransition($packed, Order::STATUS_CANCELLED));

        $shipped = $this->order(Order::FULFILMENT_DELIVERY, OrderStatus::CODE_SHIPPED);
        $this->assertFalse($service->canTransition($shipped, OrderStatus::CODE_DELIVERED));
        $this->assertFalse($service->canTransition($shipped, Order::STATUS_CANCELLED));
    }

    public function test_terminal_and_non_normal_statuses_have_no_generic_next_statuses(): void
    {
        $service = new OrderStatusService();

        foreach ($this->specialStatuses() as $status) {
            foreach ([Order::FULFILMENT_PICKUP, Order::FULFILMENT_DELIVERY] as $fulfillment) {
                $order = $this->order($fulfillment, $status);
                $this->assertSame([], $service->allowedNextStatuses($order));
                $this->assertFalse($service->canTransition($order, Order::STATUS_CONFIRMED));
                $this->assertFalse($service->canTransition($order, Order::STATUS_PROCESSING));
                $this->assertFalse($service->canTransition($order, Order::STATUS_CANCELLED));
            }
        }
    }

    public function test_delivery_lifecycle_statuses_do_not_apply_to_pickup(): void
    {
        $service = new OrderStatusService();

        foreach ([OrderStatus::CODE_PACKED, OrderStatus::CODE_SHIPPED, OrderStatus::CODE_OUT_FOR_DELIVERY, OrderStatus::CODE_DELIVERED] as $status) {
            $order = $this->order(Order::FULFILMENT_PICKUP, $status);
            $this->assertSame([], $service->allowedNextStatuses($order));
            $this->assertFalse($service->canTransition($order, Order::STATUS_COMPLETED));
            $this->assertFalse($service->canTransition($order, Order::STATUS_CANCELLED));
        }
    }

    public function test_invalid_transition_assertion_throws_validation_exception(): void
    {
        $service = new OrderStatusService();
        $order = $this->order(Order::FULFILMENT_DELIVERY, Order::STATUS_PROCESSING);

        $this->expectException(ValidationException::class);

        $service->assertCanTransition($order, Order::STATUS_READY_FOR_PICKUP);
    }

    /**
     * @param array<int, string> $expected
     */
    private function assertAllowed(OrderStatusService $service, string $fulfillment, string $fromStatus, array $expected): void
    {
        $order = $this->order($fulfillment, $fromStatus);
        $this->assertSame($expected, $service->allowedNextStatuses($order));

        foreach ($expected as $status) {
            $this->assertTrue($service->canTransition($order, $status), "{$fulfillment} {$fromStatus} should transition to {$status}.");
        }
    }

    private function assertTerminal(OrderStatusService $service, string $fulfillment, string $status): void
    {
        $order = $this->order($fulfillment, $status);

        $this->assertSame([], $service->allowedNextStatuses($order));

        foreach ($this->normalStatuses() as $nextStatus) {
            $this->assertFalse($service->canTransition($order, $nextStatus), "{$fulfillment} {$status} should not transition to {$nextStatus}.");
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalStatuses(): array
    {
        return [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PROCESSING,
            OrderStatus::CODE_PACKED,
            Order::STATUS_READY_FOR_PICKUP,
            OrderStatus::CODE_SHIPPED,
            OrderStatus::CODE_OUT_FOR_DELIVERY,
            OrderStatus::CODE_DELIVERED,
            Order::STATUS_COMPLETED,
            Order::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function specialStatuses(): array
    {
        return [
            OrderStatus::CODE_PARTIALLY_CANCELLED,
            OrderStatus::CODE_RETURN_REQUESTED,
            OrderStatus::CODE_RETURN_APPROVED,
            OrderStatus::CODE_RETURN_REJECTED,
            OrderStatus::CODE_RETURN_IN_TRANSIT,
            OrderStatus::CODE_RETURN_RECEIVED,
            OrderStatus::CODE_PARTIALLY_RETURNED,
            OrderStatus::CODE_RETURNED,
            OrderStatus::CODE_EXCHANGE_REQUESTED,
            OrderStatus::CODE_EXCHANGE_APPROVED,
            OrderStatus::CODE_EXCHANGE_REJECTED,
            OrderStatus::CODE_PARTIALLY_EXCHANGED,
            OrderStatus::CODE_EXCHANGED,
            OrderStatus::CODE_FAILED,
        ];
    }

    private function order(string $fulfillment, string $status): Order
    {
        return new Order([
            'fulfilment_type' => $fulfillment,
            'order_status' => $status,
        ]);
    }
}
