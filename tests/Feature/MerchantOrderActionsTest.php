<?php

namespace Tests\Feature;

use App\Models\MerchantCancellationReason;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\User;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MerchantOrderActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation(
                'utf8mb4_unicode_ci',
                fn (string $left, string $right): int => strcmp($left, $right),
            );
        }
    }

    public function test_pending_storefront_pickup_order_can_be_accepted(): void
    {
        [$user, $merchantId, $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'created_source' => Order::SOURCE_STOREFRONT,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
        ]);
        $this->statusHistory($order, null, Order::STATUS_PENDING, 'Order placed');

        $response = $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.accept', $order));

        $response
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHas('success', 'Order accepted successfully.');

        $order->refresh();
        $this->assertSame($merchantId, (int) $order->merchant_id);
        $this->assertSame(Order::STATUS_CONFIRMED, $order->order_status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => Order::STATUS_PENDING,
            'to_status' => Order::STATUS_CONFIRMED,
            'changed_by' => $user->getKey(),
            'notes' => 'Order accepted by merchant.',
        ]);
    }

    public function test_initial_status_history_uses_explicit_laravel_timestamp(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId);
        $frozen = Carbon::parse('2026-08-25 13:10:00', 'UTC');

        Carbon::setTestNow($frozen);
        try {
            app(OrderStatusService::class)->recordInitial($order, Order::STATUS_PENDING, $user, 'Order placed');
        } finally {
            Carbon::setTestNow();
        }

        $history = DB::table('order_status_histories')->where('order_id', $order->getKey())->first();

        $this->assertSame('2026-08-25 13:10:00', Carbon::parse($history->created_at)->format('Y-m-d H:i:s'));
    }

    public function test_transition_status_history_uses_explicit_laravel_timestamp(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId);
        $frozen = Carbon::parse('2026-08-25 13:12:00', 'UTC');

        Carbon::setTestNow($frozen);
        try {
            $this
                ->actingAs($user)
                ->withSession(['active_shop_id' => $shopId])
                ->post(route('merchant.orders.accept', $order))
                ->assertRedirect(route('merchant.orders.show', $order));
        } finally {
            Carbon::setTestNow();
        }

        $history = DB::table('order_status_histories')
            ->where('order_id', $order->getKey())
            ->where('to_status', Order::STATUS_CONFIRMED)
            ->first();

        $this->assertSame('2026-08-25 13:12:00', Carbon::parse($history->created_at)->format('Y-m-d H:i:s'));
    }

    public function test_delivery_completion_status_histories_use_explicit_laravel_timestamp(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => OrderStatus::CODE_OUT_FOR_DELIVERY,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'grand_total' => 1998,
            'amount_paid' => 0,
        ]);
        $frozen = Carbon::parse('2026-08-25 13:20:00', 'UTC');

        Carbon::setTestNow($frozen);
        try {
            $this
                ->actingAs($user)
                ->withSession(['active_shop_id' => $shopId])
                ->post(route('merchant.orders.deliver', $order), [
                    'payment_received' => '1',
                ])
                ->assertRedirect(route('merchant.orders.show', $order));
        } finally {
            Carbon::setTestNow();
        }

        $histories = DB::table('order_status_histories')
            ->where('order_id', $order->getKey())
            ->pluck('created_at')
            ->map(fn ($createdAt): string => Carbon::parse($createdAt)->format('Y-m-d H:i:s'))
            ->all();

        $this->assertNotEmpty($histories);
        $this->assertSame(['2026-08-25 13:20:00'], array_values(array_unique($histories)));
    }

    public function test_pending_customer_app_order_can_be_accepted(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'created_source' => Order::SOURCE_CUSTOMER_APP,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.accept', $order))
            ->assertRedirect(route('merchant.orders.show', $order));

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->order_status);
    }

    public function test_accepting_an_already_confirmed_order_is_rejected_without_duplicate_history(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_CONFIRMED,
        ]);
        $this->statusHistory($order, null, Order::STATUS_CONFIRMED, 'Order already accepted');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.accept', $order))
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('order_status');

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->order_status);
        $this->assertSame(1, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_confirmed_storefront_pickup_order_can_start_processing_without_stock_or_payment_changes(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_CONFIRMED,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'payment_method' => 'cash_at_shop',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'amount_paid' => 0,
        ]);
        $variantId = $this->variantForShop($shopId, 8);
        $this->orderItem($order, $variantId, 2);
        $this->statusHistory($order, null, Order::STATUS_CONFIRMED, 'Order accepted');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.processing', $order))
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHas('success', 'Order processing started successfully.');

        $order->refresh();
        $this->assertSame(Order::STATUS_PROCESSING, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);
        $this->assertSame('0.00', $order->amount_paid);
        $this->assertSame(8, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => Order::STATUS_CONFIRMED,
            'to_status' => Order::STATUS_PROCESSING,
            'changed_by' => $user->getKey(),
            'notes' => 'Order processing started.',
        ]);
    }

    public function test_confirmed_storefront_delivery_order_can_start_processing(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_CONFIRMED,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => PaymentStatus::CODE_PENDING,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.processing', $order))
            ->assertRedirect(route('merchant.orders.show', $order));

        $order->refresh();
        $this->assertSame(Order::STATUS_PROCESSING, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);
    }

    public function test_confirmed_customer_app_order_can_start_processing(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'created_source' => Order::SOURCE_CUSTOMER_APP,
            'order_status' => Order::STATUS_CONFIRMED,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.processing', $order))
            ->assertRedirect(route('merchant.orders.show', $order));

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->order_status);
    }

    public function test_pending_order_cannot_start_processing_directly(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.processing', $order))
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('order_status');

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->order_status);
        $this->assertSame(0, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_start_processing_repeat_request_is_rejected_without_duplicate_history(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_PROCESSING,
        ]);
        $this->statusHistory($order, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, 'Order processing started.');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.processing', $order))
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('order_status');

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->order_status);
        $this->assertSame(1, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_processing_pickup_order_can_be_marked_ready_without_stock_or_payment_changes(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_PROCESSING,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'payment_method' => 'cash_at_shop',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'amount_paid' => 0,
        ]);
        $variantId = $this->variantForShop($shopId, 8);
        $this->orderItem($order, $variantId, 2);
        $this->statusHistory($order, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, 'Order processing started.');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.ready-for-pickup', $order))
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHas('success', 'Order marked ready for pickup successfully.');

        $order->refresh();
        $this->assertSame(Order::STATUS_READY_FOR_PICKUP, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);
        $this->assertSame('0.00', $order->amount_paid);
        $this->assertSame(8, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => Order::STATUS_PROCESSING,
            'to_status' => Order::STATUS_READY_FOR_PICKUP,
            'changed_by' => $user->getKey(),
            'notes' => 'Order is ready for customer pickup.',
        ]);
    }

    public function test_processing_delivery_order_can_be_marked_packed_without_stock_or_payment_changes(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_PROCESSING,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'amount_paid' => 0,
        ]);
        $variantId = $this->variantForShop($shopId, 8);
        $this->orderItem($order, $variantId, 2);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.packed', $order))
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHas('success', 'Order marked packed successfully.');

        $order->refresh();
        $this->assertSame('packed', $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);
        $this->assertSame('0.00', $order->amount_paid);
        $this->assertSame(8, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => Order::STATUS_PROCESSING,
            'to_status' => 'packed',
            'changed_by' => $user->getKey(),
            'notes' => 'Order packed and ready for delivery.',
        ]);
    }

    public function test_delivery_order_can_advance_from_packed_to_completed_with_cod_payment_confirmation(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => OrderStatus::CODE_PACKED,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'amount_paid' => 0,
        ]);
        $variantId = $this->variantForShop($shopId, 8);
        $this->orderItem($order, $variantId, 2);
        $this->statusHistory($order, Order::STATUS_PROCESSING, OrderStatus::CODE_PACKED, 'Order packed and ready for delivery.');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.ship', $order))
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHas('success', 'Order marked shipped successfully.');

        $order->refresh();
        $this->assertSame(OrderStatus::CODE_SHIPPED, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);
        $this->assertSame('0.00', $order->amount_paid);
        $this->assertSame(8, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => OrderStatus::CODE_PACKED,
            'to_status' => OrderStatus::CODE_SHIPPED,
            'changed_by' => $user->getKey(),
            'notes' => 'Order handed over for delivery.',
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.out-for-delivery', $order))
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHas('success', 'Order marked out for delivery successfully.');

        $order->refresh();
        $this->assertSame(OrderStatus::CODE_OUT_FOR_DELIVERY, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);
        $this->assertSame('0.00', $order->amount_paid);
        $this->assertSame(8, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => OrderStatus::CODE_SHIPPED,
            'to_status' => OrderStatus::CODE_OUT_FOR_DELIVERY,
            'changed_by' => $user->getKey(),
            'notes' => 'Order is out for delivery.',
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.deliver', $order), [
                'payment_received' => '1',
            ])
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHas('success', 'Order delivered and completed successfully.');

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PAID, $order->payment_status);
        $this->assertSame('1998.00', $order->amount_paid);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(8, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => OrderStatus::CODE_OUT_FOR_DELIVERY,
            'to_status' => OrderStatus::CODE_DELIVERED,
            'changed_by' => $user->getKey(),
            'notes' => 'Order delivered to customer.',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => OrderStatus::CODE_DELIVERED,
            'to_status' => OrderStatus::CODE_DELIVERED,
            'changed_by' => $user->getKey(),
            'notes' => 'COD payment 1998.00 received.',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => OrderStatus::CODE_DELIVERED,
            'to_status' => Order::STATUS_COMPLETED,
            'changed_by' => $user->getKey(),
            'notes' => 'Order completed successfully.',
        ]);
    }

    public function test_cod_delivery_completion_requires_payment_confirmation(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => OrderStatus::CODE_OUT_FOR_DELIVERY,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'amount_paid' => 0,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.deliver', $order))
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('payment_received');

        $order->refresh();
        $this->assertSame(OrderStatus::CODE_OUT_FOR_DELIVERY, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);
        $this->assertSame('0.00', $order->amount_paid);
        $this->assertNull($order->completed_at);
        $this->assertSame(0, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_already_paid_delivery_completion_marks_delivered_then_completed_without_payment_mutation(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => OrderStatus::CODE_OUT_FOR_DELIVERY,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'online_payment',
            'payment_status' => PaymentStatus::CODE_PAID,
            'amount_paid' => 1998,
            'grand_total' => 1998,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.deliver', $order))
            ->assertRedirect(route('merchant.orders.show', $order));

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PAID, $order->payment_status);
        $this->assertSame('1998.00', $order->amount_paid);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(2, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
        $this->assertDatabaseMissing('order_status_histories', [
            'order_id' => $order->getKey(),
            'notes' => 'COD payment 1998.00 received.',
        ]);
    }

    public function test_delivery_completion_repeat_request_is_rejected_without_duplicate_payment_or_history(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_COMPLETED,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => PaymentStatus::CODE_PAID,
            'amount_paid' => 1998,
            'grand_total' => 1998,
        ]);
        $this->statusHistory($order, OrderStatus::CODE_OUT_FOR_DELIVERY, OrderStatus::CODE_DELIVERED, 'Order delivered to customer.');
        $this->statusHistory($order, OrderStatus::CODE_DELIVERED, OrderStatus::CODE_DELIVERED, 'COD payment 1998.00 received.');
        $this->statusHistory($order, OrderStatus::CODE_DELIVERED, Order::STATUS_COMPLETED, 'Order completed successfully.');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.deliver', $order), [
                'payment_received' => '1',
            ])
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('order_status');

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PAID, $order->payment_status);
        $this->assertSame('1998.00', $order->amount_paid);
        $this->assertSame(3, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_delivery_workflow_rejects_invalid_status_skips(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $processing = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_PROCESSING,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
        ]);
        $packedForOut = $this->operationalOrder($shopId, [
            'order_status' => OrderStatus::CODE_PACKED,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
        ]);
        $shippedForDeliver = $this->operationalOrder($shopId, [
            'order_status' => OrderStatus::CODE_SHIPPED,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
        ]);
        $packedForDeliver = $this->operationalOrder($shopId, [
            'order_status' => OrderStatus::CODE_PACKED,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $processing))
            ->post(route('merchant.orders.ship', $processing))
            ->assertRedirect(route('merchant.orders.show', $processing))
            ->assertSessionHasErrors('order_status');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $packedForOut))
            ->post(route('merchant.orders.out-for-delivery', $packedForOut))
            ->assertRedirect(route('merchant.orders.show', $packedForOut))
            ->assertSessionHasErrors('order_status');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $shippedForDeliver))
            ->post(route('merchant.orders.deliver', $shippedForDeliver))
            ->assertRedirect(route('merchant.orders.show', $shippedForDeliver))
            ->assertSessionHasErrors('order_status');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $packedForDeliver))
            ->post(route('merchant.orders.deliver', $packedForDeliver))
            ->assertRedirect(route('merchant.orders.show', $packedForDeliver))
            ->assertSessionHasErrors('order_status');

        $this->assertSame(Order::STATUS_PROCESSING, $processing->fresh()->order_status);
        $this->assertSame(OrderStatus::CODE_PACKED, $packedForOut->fresh()->order_status);
        $this->assertSame(OrderStatus::CODE_SHIPPED, $shippedForDeliver->fresh()->order_status);
        $this->assertSame(OrderStatus::CODE_PACKED, $packedForDeliver->fresh()->order_status);
        $this->assertSame(0, DB::table('order_status_histories')->count());
    }

    public function test_customer_app_processing_orders_support_fulfillment_specific_actions(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $pickup = $this->operationalOrder($shopId, [
            'created_source' => Order::SOURCE_CUSTOMER_APP,
            'order_status' => Order::STATUS_PROCESSING,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
        ]);
        $delivery = $this->operationalOrder($shopId, [
            'created_source' => Order::SOURCE_CUSTOMER_APP,
            'order_status' => Order::STATUS_PROCESSING,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.ready-for-pickup', $pickup))
            ->assertRedirect(route('merchant.orders.show', $pickup));

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.packed', $delivery))
            ->assertRedirect(route('merchant.orders.show', $delivery));

        $this->assertSame(Order::STATUS_READY_FOR_PICKUP, $pickup->fresh()->order_status);
        $delivery->refresh();
        $this->assertSame('packed', $delivery->order_status);
        $this->assertSame(PaymentStatus::CODE_PENDING, $delivery->payment_status);
        $this->assertSame('0.00', $delivery->amount_paid);
    }

    public function test_customer_app_delivery_cod_can_complete_with_payment_confirmation(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'created_source' => Order::SOURCE_CUSTOMER_APP,
            'order_status' => OrderStatus::CODE_OUT_FOR_DELIVERY,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'amount_paid' => 0,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.deliver', $order), [
                'payment_received' => '1',
            ])
            ->assertRedirect(route('merchant.orders.show', $order));

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PAID, $order->payment_status);
        $this->assertSame('1998.00', $order->amount_paid);
    }

    public function test_processing_pickup_order_cannot_be_marked_packed(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_PROCESSING,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.packed', $order))
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('order_status');

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->order_status);
        $this->assertSame(0, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_pickup_order_cannot_use_delivery_status_actions(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $packed = $this->operationalOrder($shopId, [
            'order_status' => OrderStatus::CODE_PACKED,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
        ]);
        $shipped = $this->operationalOrder($shopId, [
            'order_status' => OrderStatus::CODE_SHIPPED,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
        ]);
        $outForDelivery = $this->operationalOrder($shopId, [
            'order_status' => OrderStatus::CODE_OUT_FOR_DELIVERY,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $packed))
            ->post(route('merchant.orders.ship', $packed))
            ->assertRedirect(route('merchant.orders.show', $packed))
            ->assertSessionHasErrors('order_status');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $shipped))
            ->post(route('merchant.orders.out-for-delivery', $shipped))
            ->assertRedirect(route('merchant.orders.show', $shipped))
            ->assertSessionHasErrors('order_status');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $outForDelivery))
            ->post(route('merchant.orders.deliver', $outForDelivery))
            ->assertRedirect(route('merchant.orders.show', $outForDelivery))
            ->assertSessionHasErrors('order_status');

        $this->assertSame(OrderStatus::CODE_PACKED, $packed->fresh()->order_status);
        $this->assertSame(OrderStatus::CODE_SHIPPED, $shipped->fresh()->order_status);
        $this->assertSame(OrderStatus::CODE_OUT_FOR_DELIVERY, $outForDelivery->fresh()->order_status);
        $this->assertSame(0, DB::table('order_status_histories')->count());
    }

    public function test_processing_delivery_order_cannot_be_marked_ready_for_pickup(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_PROCESSING,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.ready-for-pickup', $order))
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('order_status');

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->order_status);
        $this->assertSame(0, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_fulfillment_action_repeat_requests_are_rejected_without_duplicate_history(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $pickup = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_READY_FOR_PICKUP,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
        ]);
        $packed = $this->operationalOrder($shopId, [
            'order_status' => 'packed',
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
        ]);
        $this->statusHistory($pickup, Order::STATUS_PROCESSING, Order::STATUS_READY_FOR_PICKUP, 'Order is ready for customer pickup.');
        $this->statusHistory($packed, Order::STATUS_PROCESSING, 'packed', 'Order packed and ready for delivery.');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $pickup))
            ->post(route('merchant.orders.ready-for-pickup', $pickup))
            ->assertRedirect(route('merchant.orders.show', $pickup))
            ->assertSessionHasErrors('order_status');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $packed))
            ->post(route('merchant.orders.packed', $packed))
            ->assertRedirect(route('merchant.orders.show', $packed))
            ->assertSessionHasErrors('order_status');

        $this->assertSame(1, DB::table('order_status_histories')->where('order_id', $pickup->getKey())->count());
        $this->assertSame(1, DB::table('order_status_histories')->where('order_id', $packed->getKey())->count());
    }

    public function test_cash_at_shop_pickup_can_complete_with_payment_confirmation(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_READY_FOR_PICKUP,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'payment_method' => 'cash_at_shop',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'amount_paid' => 0,
            'grand_total' => 1998,
        ]);
        $variantId = $this->variantForShop($shopId, 8);
        $this->orderItem($order, $variantId, 2);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.complete-pickup', $order), [
                'payment_received' => '1',
            ])
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHas('success', 'Pickup completed successfully.');

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PAID, $order->payment_status);
        $this->assertSame('1998.00', $order->amount_paid);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(8, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => Order::STATUS_READY_FOR_PICKUP,
            'to_status' => Order::STATUS_COMPLETED,
            'changed_by' => $user->getKey(),
            'notes' => 'Customer collected the order from the shop.',
        ]);
    }

    public function test_cash_at_shop_pickup_completion_requires_payment_confirmation(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_READY_FOR_PICKUP,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'payment_method' => 'cash_at_shop',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'amount_paid' => 0,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.complete-pickup', $order))
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('payment_received');

        $order->refresh();
        $this->assertSame(Order::STATUS_READY_FOR_PICKUP, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);
        $this->assertSame('0.00', $order->amount_paid);
        $this->assertSame(0, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_already_paid_pickup_can_complete_without_collecting_payment_again(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_READY_FOR_PICKUP,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'payment_method' => 'online_payment',
            'payment_status' => PaymentStatus::CODE_PAID,
            'amount_paid' => 1998,
            'grand_total' => 1998,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.complete-pickup', $order))
            ->assertRedirect(route('merchant.orders.show', $order));

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PAID, $order->payment_status);
        $this->assertSame('1998.00', $order->amount_paid);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(1, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_delivery_order_cannot_use_pickup_completion(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => 'packed',
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => PaymentStatus::CODE_PENDING,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.complete-pickup', $order), [
                'payment_received' => '1',
            ])
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('order_status');

        $this->assertSame('packed', $order->fresh()->order_status);
        $this->assertSame(0, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_wrong_pickup_status_cannot_complete_pickup(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_PROCESSING,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'payment_method' => 'cash_at_shop',
            'payment_status' => PaymentStatus::CODE_PENDING,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.complete-pickup', $order), [
                'payment_received' => '1',
            ])
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('order_status');

        $order->refresh();
        $this->assertSame(Order::STATUS_PROCESSING, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);
        $this->assertSame('0.00', $order->amount_paid);
        $this->assertSame(0, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_pickup_completion_repeat_request_is_rejected_without_duplicate_payment_or_history(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_COMPLETED,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'payment_method' => 'cash_at_shop',
            'payment_status' => PaymentStatus::CODE_PAID,
            'amount_paid' => 1998,
            'grand_total' => 1998,
        ]);
        $this->statusHistory($order, Order::STATUS_READY_FOR_PICKUP, Order::STATUS_COMPLETED, 'Customer collected the order from the shop.');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.complete-pickup', $order), [
                'payment_received' => '1',
            ])
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('order_status');

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PAID, $order->payment_status);
        $this->assertSame('1998.00', $order->amount_paid);
        $this->assertSame(1, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_customer_app_pickup_can_complete(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'created_source' => Order::SOURCE_CUSTOMER_APP,
            'order_status' => Order::STATUS_READY_FOR_PICKUP,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'payment_method' => 'cash_at_shop',
            'payment_status' => PaymentStatus::CODE_PENDING,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.complete-pickup', $order), [
                'payment_received' => '1',
            ])
            ->assertRedirect(route('merchant.orders.show', $order));

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->order_status);
    }

    public function test_pending_order_can_be_cancelled_with_merchant_reason(): void
    {
        [$user, $merchantId, $shopId] = $this->merchantShopFixture();
        $reason = $this->cancellationReason($merchantId, [
            'code' => 'out_of_stock',
            'name' => 'Product Out of Stock',
        ]);
        $order = $this->operationalOrder($shopId);
        $variantId = $this->variantForShop($shopId, 8);
        $this->orderItem($order, $variantId, 2);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.cancel', $order), [
                'cancellation_reason_id' => $reason->getKey(),
                'cancellation_note' => 'Supplier confirmed no replacement stock.',
            ])
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHas('success', 'Order cancelled successfully.');

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->order_status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);
        $this->assertSame('0.00', $order->amount_paid);
        $this->assertSame(10, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => Order::STATUS_PENDING,
            'to_status' => Order::STATUS_CANCELLED,
            'changed_by' => $user->getKey(),
            'notes' => 'Cancelled by merchant. Reason: Product Out of Stock. Note: Supplier confirmed no replacement stock.',
        ]);
    }

    public function test_confirmed_order_can_still_be_cancelled_with_existing_behavior(): void
    {
        [$user, $merchantId, $shopId] = $this->merchantShopFixture();
        $reason = $this->cancellationReason($merchantId, [
            'name' => 'Customer Requested Cancellation',
        ]);
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_CONFIRMED,
        ]);
        $variantId = $this->variantForShop($shopId, 8);
        $this->orderItem($order, $variantId, 2);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.cancel', $order), [
                'cancellation_reason_id' => $reason->getKey(),
            ])
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHas('success', 'Order cancelled successfully.');

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->order_status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(10, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => Order::STATUS_CONFIRMED,
            'to_status' => Order::STATUS_CANCELLED,
        ]);
    }

    public function test_ready_for_pickup_order_can_be_cancelled_and_restores_inventory(): void
    {
        [$user, $merchantId, $shopId] = $this->merchantShopFixture();
        $reason = $this->cancellationReason($merchantId, [
            'code' => 'customer_not_collecting',
            'name' => 'Customer Not Collecting',
        ]);
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_READY_FOR_PICKUP,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
        ]);
        $variantId = $this->variantForShop($shopId, 8);
        $this->orderItem($order, $variantId, 2);
        $this->statusHistory($order, Order::STATUS_PROCESSING, Order::STATUS_READY_FOR_PICKUP, 'Ready');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.cancel', $order), [
                'cancellation_reason_id' => $reason->getKey(),
                'cancellation_note' => 'Customer asked us to cancel before pickup.',
            ])
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHas('success', 'Order cancelled successfully.');

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->order_status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(10, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => Order::STATUS_READY_FOR_PICKUP,
            'to_status' => Order::STATUS_CANCELLED,
            'changed_by' => $user->getKey(),
            'notes' => 'Cancelled by merchant. Reason: Customer Not Collecting. Note: Customer asked us to cancel before pickup.',
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $order))
            ->assertOk()
            ->assertSee('Order Activity')
            ->assertSee('Cancelled')
            ->assertSee('Cancelled by merchant. Reason: Customer Not Collecting. Note: Customer asked us to cancel before pickup.')
            ->assertDontSee('Complete Pickup');
    }

    public function test_completed_pickup_order_cannot_be_cancelled(): void
    {
        [$user, $merchantId, $shopId] = $this->merchantShopFixture();
        $reason = $this->cancellationReason($merchantId);
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_COMPLETED,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'payment_method' => 'cash_at_shop',
            'payment_status' => PaymentStatus::CODE_PAID,
            'amount_paid' => 1998,
        ]);
        $variantId = $this->variantForShop($shopId, 8);
        $this->orderItem($order, $variantId, 2);
        $this->statusHistory($order, Order::STATUS_READY_FOR_PICKUP, Order::STATUS_COMPLETED, 'Customer collected the order from the shop.');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.cancel', $order), [
                'cancellation_reason_id' => $reason->getKey(),
            ])
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('order_status');

        $order->refresh();
        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertNull($order->cancelled_at);
        $this->assertSame(PaymentStatus::CODE_PAID, $order->payment_status);
        $this->assertSame('1998.00', $order->amount_paid);
        $this->assertSame(8, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
        $this->assertSame(1, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_packed_delivery_order_cannot_be_cancelled_or_restore_stock_through_simple_cancellation(): void
    {
        [$user, $merchantId, $shopId] = $this->merchantShopFixture();
        $reason = $this->cancellationReason($merchantId);
        $order = $this->operationalOrder($shopId, [
            'order_status' => 'packed',
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'amount_paid' => 0,
        ]);
        $variantId = $this->variantForShop($shopId, 8);
        $this->orderItem($order, $variantId, 2);
        $this->statusHistory($order, Order::STATUS_PROCESSING, 'packed', 'Order packed and ready for delivery.');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.cancel', $order), [
                'cancellation_reason_id' => $reason->getKey(),
            ])
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('order_status');

        $order->refresh();
        $this->assertSame('packed', $order->order_status);
        $this->assertNull($order->cancelled_at);
        $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);
        $this->assertSame('0.00', $order->amount_paid);
        $this->assertSame(8, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
        $this->assertSame(1, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_late_delivery_statuses_cannot_be_cancelled_or_restore_stock_through_simple_cancellation(): void
    {
        [$user, $merchantId, $shopId] = $this->merchantShopFixture();
        $reason = $this->cancellationReason($merchantId);

        foreach ([OrderStatus::CODE_SHIPPED, OrderStatus::CODE_OUT_FOR_DELIVERY, OrderStatus::CODE_DELIVERED] as $status) {
            $order = $this->operationalOrder($shopId, [
                'order_status' => $status,
                'fulfilment_type' => Order::FULFILMENT_DELIVERY,
                'payment_method' => 'cash_on_delivery',
                'payment_status' => PaymentStatus::CODE_PENDING,
                'amount_paid' => 0,
            ]);
            $variantId = $this->variantForShop($shopId, 8);
            $this->orderItem($order, $variantId, 2);
            $this->statusHistory($order, null, $status, 'Existing status.');

            $this
                ->actingAs($user)
                ->withSession(['active_shop_id' => $shopId])
                ->from(route('merchant.orders.show', $order))
                ->post(route('merchant.orders.cancel', $order), [
                    'cancellation_reason_id' => $reason->getKey(),
                ])
                ->assertRedirect(route('merchant.orders.show', $order))
                ->assertSessionHasErrors('order_status');

            $order->refresh();
            $this->assertSame($status, $order->order_status);
            $this->assertNull($order->cancelled_at);
            $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);
            $this->assertSame('0.00', $order->amount_paid);
            $this->assertSame(8, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
            $this->assertSame(1, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
        }
    }

    public function test_order_actions_are_limited_to_active_shop(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $otherShopId = $this->shopForMerchant((int) DB::table('shops')->where('id', $shopId)->value('merchant_id'), 'Other Shop');
        $otherOrder = $this->operationalOrder($otherShopId);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.accept', $otherOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.processing', $otherOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.ready-for-pickup', $otherOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.packed', $otherOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.ship', $otherOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.out-for-delivery', $otherOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.deliver', $otherOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.complete-pickup', $otherOrder), [
                'payment_received' => '1',
            ])
            ->assertNotFound();
    }

    public function test_pos_order_cannot_use_merchant_operational_actions(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $posOrder = $this->operationalOrder($shopId, [
            'created_source' => Order::SOURCE_POS,
            'order_status' => Order::STATUS_COMPLETED,
            'fulfilment_type' => Order::FULFILMENT_COUNTER,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'payment_status' => PaymentStatus::CODE_PAID,
            'amount_paid' => 1998,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.accept', $posOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.processing', $posOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.ready-for-pickup', $posOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.packed', $posOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.ship', $posOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.out-for-delivery', $posOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.deliver', $posOrder))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.complete-pickup', $posOrder), [
                'payment_received' => '1',
            ])
            ->assertNotFound();
    }

    public function test_order_detail_shows_only_current_workflow_actions(): void
    {
        [$user, $merchantId, $shopId] = $this->merchantShopFixture();
        $this->cancellationReason($merchantId);
        $pending = $this->operationalOrder($shopId);
        $confirmed = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-CONFIRMED-DETAIL',
            'order_status' => Order::STATUS_CONFIRMED,
        ]);
        $processing = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-PROCESSING-DETAIL',
            'order_status' => Order::STATUS_PROCESSING,
        ]);
        $deliveryProcessing = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-DELIVERY-PROCESSING',
            'order_status' => Order::STATUS_PROCESSING,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
        ]);
        $ready = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-READY-DETAIL',
            'order_status' => Order::STATUS_READY_FOR_PICKUP,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
        ]);
        $packed = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-PACKED-DETAIL',
            'order_status' => OrderStatus::CODE_PACKED,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
        ]);
        $shipped = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-SHIPPED-DETAIL',
            'order_status' => OrderStatus::CODE_SHIPPED,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
        ]);
        $outForDelivery = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-OUT-DETAIL',
            'order_status' => OrderStatus::CODE_OUT_FOR_DELIVERY,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => PaymentStatus::CODE_PENDING,
        ]);
        $delivered = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-DELIVERED-DETAIL',
            'order_status' => OrderStatus::CODE_DELIVERED,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => PaymentStatus::CODE_PENDING,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $pending))
            ->assertOk()
            ->assertSee('Accept Order')
            ->assertSee('Cancel Order')
            ->assertDontSee('Start Processing');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $confirmed))
            ->assertOk()
            ->assertDontSee('Accept Order')
            ->assertSee('Cancel Order')
            ->assertSee('Start Processing')
            ->assertDontSee('Mark Ready for Pickup')
            ->assertDontSee('Mark Packed');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $processing))
            ->assertOk()
            ->assertDontSee('Accept Order')
            ->assertSee('Cancel Order')
            ->assertDontSee('Start Processing')
            ->assertSee('Mark Ready for Pickup')
            ->assertDontSee('Mark Packed');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $deliveryProcessing))
            ->assertOk()
            ->assertDontSee('Accept Order')
            ->assertSee('Cancel Order')
            ->assertDontSee('Start Processing')
            ->assertDontSee('Mark Ready for Pickup')
            ->assertSee('Mark Packed');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $ready))
            ->assertOk()
            ->assertSee('Cancel Order')
            ->assertSee('Complete Pickup')
            ->assertDontSee('Mark Ready for Pickup')
            ->assertDontSee('Mark Packed');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $packed))
            ->assertOk()
            ->assertDontSee('Cancel Order')
            ->assertDontSee('Complete Pickup')
            ->assertDontSee('Complete Order')
            ->assertDontSee('Mark Ready for Pickup')
            ->assertDontSee('Mark Packed')
            ->assertSee('Mark Shipped')
            ->assertSee('Out for Delivery')
            ->assertSee('Delivered')
            ->assertSee('Completed');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $shipped))
            ->assertOk()
            ->assertDontSee('Cancel Order')
            ->assertDontSee('Mark Shipped')
            ->assertSee('Mark Out for Delivery')
            ->assertDontSee('Mark Delivered')
            ->assertDontSee('Complete Order');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $outForDelivery))
            ->assertOk()
            ->assertDontSee('Cancel Order')
            ->assertDontSee('Mark Out for Delivery')
            ->assertSee('Mark Delivered')
            ->assertSee('Mark Order as Delivered?')
            ->assertSee('Amount Due')
            ->assertSee('I confirm the COD payment has been received.')
            ->assertDontSee('COD payment will remain pending')
            ->assertDontSee('Complete Order');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $delivered))
            ->assertOk()
            ->assertDontSee('Cancel Order')
            ->assertDontSee('Mark Delivered')
            ->assertDontSee('Complete Order')
            ->assertSee('Cash on Delivery')
            ->assertSee('Pending');
    }

    public function test_merchant_only_comment_is_created_without_notification_intent_and_no_workflow_changes(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => PaymentStatus::CODE_PENDING,
            'amount_paid' => 0,
        ]);
        $variantId = $this->variantForShop($shopId, 8);
        $this->orderItem($order, $variantId, 2);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.comments.store', $order), [
                'comment' => '  Customer asked for gift packing.  ',
                'visibility' => OrderComment::VISIBILITY_MERCHANT_ONLY,
                'notify_customer' => '1',
                'notify_email' => '1',
                'notify_sms' => '1',
                'notify_whatsapp' => '1',
            ])
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHas('success', 'Comment added successfully.');

        $this->assertDatabaseHas('order_comments', [
            'order_id' => $order->getKey(),
            'author_type' => OrderComment::AUTHOR_MERCHANT,
            'comment' => 'Customer asked for gift packing.',
            'visibility' => OrderComment::VISIBILITY_MERCHANT_ONLY,
            'notify_customer' => 0,
            'notify_email' => 0,
            'notify_sms' => 0,
            'notify_whatsapp' => 0,
            'created_by' => $user->getKey(),
        ]);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->order_status);
        $this->assertSame(PaymentStatus::CODE_PENDING, $order->fresh()->payment_status);
        $this->assertSame('0.00', $order->fresh()->amount_paid);
        $this->assertSame(8, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
        $this->assertSame(0, DB::table('order_status_histories')->where('order_id', $order->getKey())->count());
    }

    public function test_customer_visible_comment_can_be_saved_without_notification(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.comments.store', $order), [
                'comment' => 'Customer can see this note.',
                'visibility' => OrderComment::VISIBILITY_CUSTOMER,
                'notify_customer' => '0',
            ])
            ->assertRedirect(route('merchant.orders.show', $order));

        $this->assertDatabaseHas('order_comments', [
            'order_id' => $order->getKey(),
            'visibility' => OrderComment::VISIBILITY_CUSTOMER,
            'notify_customer' => 0,
            'notify_email' => 0,
            'notify_sms' => 0,
            'notify_whatsapp' => 0,
        ]);
    }

    public function test_customer_visible_comment_can_store_notification_intent_channels(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.comments.store', $order), [
                'comment' => 'Pickup is ready.',
                'visibility' => OrderComment::VISIBILITY_CUSTOMER,
                'notify_customer' => '1',
                'notify_email' => '1',
                'notify_whatsapp' => '1',
            ])
            ->assertRedirect(route('merchant.orders.show', $order));

        $this->assertDatabaseHas('order_comments', [
            'order_id' => $order->getKey(),
            'visibility' => OrderComment::VISIBILITY_CUSTOMER,
            'notify_customer' => 1,
            'notify_email' => 1,
            'notify_sms' => 0,
            'notify_whatsapp' => 1,
        ]);
    }

    public function test_customer_visible_notification_requires_at_least_one_channel(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->from(route('merchant.orders.show', $order))
            ->post(route('merchant.orders.comments.store', $order), [
                'comment' => 'Notify without channel.',
                'visibility' => OrderComment::VISIBILITY_CUSTOMER,
                'notify_customer' => '1',
            ])
            ->assertRedirect(route('merchant.orders.show', $order))
            ->assertSessionHasErrors('notify_channels');

        $this->assertDatabaseCount('order_comments', 0);
    }

    public function test_order_comment_wrong_shop_is_blocked(): void
    {
        [$user, $merchantId, $shopId] = $this->merchantShopFixture();
        $otherShopId = $this->shopForMerchant($merchantId, 'Other Action Shop');
        $order = $this->operationalOrder($shopId);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $otherShopId])
            ->post(route('merchant.orders.comments.store', $order), [
                'comment' => 'Wrong shop comment.',
                'visibility' => OrderComment::VISIBILITY_MERCHANT_ONLY,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('order_comments', 0);
    }

    public function test_pos_order_cannot_receive_merchant_operational_comment(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'created_source' => Order::SOURCE_POS,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'payment_status' => PaymentStatus::CODE_PAID,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.orders.comments.store', $order), [
                'comment' => 'POS comment.',
                'visibility' => OrderComment::VISIBILITY_MERCHANT_ONLY,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('order_comments', 0);
    }

    public function test_order_comments_appear_in_order_activity(): void
    {
        [$user, , $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'customer_order_note' => 'Please call before dispatch.',
        ]);
        $this->statusHistory($order, null, Order::STATUS_PENDING, 'Storefront order placed');
        $order->comments()->create([
            'author_type' => OrderComment::AUTHOR_MERCHANT,
            'comment' => 'Internal packing note.',
            'visibility' => OrderComment::VISIBILITY_MERCHANT_ONLY,
            'created_by' => $user->getKey(),
        ]);
        $order->comments()->create([
            'author_type' => OrderComment::AUTHOR_MERCHANT,
            'comment' => 'Customer visible update.',
            'visibility' => OrderComment::VISIBILITY_CUSTOMER,
            'notify_customer' => true,
            'notify_email' => true,
            'notify_whatsapp' => true,
            'created_by' => $user->getKey(),
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $order))
            ->assertOk()
            ->assertSee('Customer Order Note')
            ->assertSee('Please call before dispatch.')
            ->assertSee('Storefront order placed')
            ->assertSee('Internal Note')
            ->assertSee('Internal packing note.')
            ->assertSee('Merchant Only')
            ->assertSee('Customer Comment')
            ->assertSee('Customer visible update.')
            ->assertSee('Customer Visible')
            ->assertSee('Notify via Email, WhatsApp')
            ->assertSee('Added by '.$user->name);
    }

    /**
     * @return array{0: User, 1: int, 2: int}
     */
    private function merchantShopFixture(): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Order Action Merchant',
            'email' => 'order-action-'.Str::random(6).'@example.test',
            'mobile' => '90000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $roleId = (int) (DB::table('auth_roles')->where('slug', 'merchant')->value('id')
            ?? DB::table('auth_roles')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Merchant',
                'slug' => 'merchant',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        DB::table('auth_user_roles')->insert([
            'user_id' => $user->getKey(),
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $merchantId = (int) DB::table('merchant_profiles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'business_name' => 'Order Action Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $merchantId, $this->shopForMerchant($merchantId, 'Action Shop')];
    }

    private function shopForMerchant(int $merchantId, string $name): int
    {
        $categoryId = (int) (DB::table('product_categories')->where('slug', 'retail')->value('id')
            ?? DB::table('product_categories')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Retail',
                'slug' => 'retail',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        return (int) DB::table('shops')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchantId,
            'root_product_category_id' => $categoryId,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'address_line_1' => 'Shop 12, College Road',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function operationalOrder(int $shopId, array $overrides = []): Order
    {
        $merchantId = (int) DB::table('shops')->where('id', $shopId)->value('merchant_id');

        return Order::query()->create(array_merge([
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'merchant_id' => $merchantId,
            'shop_id' => $shopId,
            'created_source' => Order::SOURCE_STOREFRONT,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'order_status' => Order::STATUS_PENDING,
            'payment_method' => 'cash_at_shop',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'currency_code' => 'INR',
            'subtotal' => 1998,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 1998,
            'amount_paid' => 0,
            'customer_name' => 'Kishore Mishra',
            'customer_mobile' => '9422945125',
            'customer_email' => 'kishore@example.test',
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function cancellationReason(int $merchantId, array $overrides = []): MerchantCancellationReason
    {
        return MerchantCancellationReason::query()->create(array_merge([
            'merchant_id' => $merchantId,
            'code' => 'customer_requested_'.Str::lower(Str::random(5)),
            'name' => 'Customer Requested Cancellation',
            'sort_order' => 10,
            'customer_selectable' => true,
            'merchant_selectable' => true,
            'requires_comment' => false,
            'status' => MerchantCancellationReason::STATUS_ACTIVE,
        ], $overrides));
    }

    private function variantForShop(int $shopId, int $stock): int
    {
        $shop = DB::table('shops')->where('id', $shopId)->first();
        $categoryId = (int) $shop->root_product_category_id;
        $productId = (int) DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shopId,
            'root_product_category_id' => $categoryId,
            'product_category_id' => $categoryId,
            'product_name' => 'Cancelled Stock Product',
            'slug' => 'cancelled-stock-product-'.Str::random(6),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('product_variants')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'product_id' => $productId,
            'shop_id' => $shopId,
            'sku' => 'CANCEL-STOCK-'.Str::upper(Str::random(4)),
            'name' => 'Default',
            'mrp' => 999,
            'selling_price' => 999,
            'stock_quantity' => $stock,
            'is_default' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function orderItem(Order $order, int $variantId, int $quantity): void
    {
        $variant = DB::table('product_variants')->where('id', $variantId)->first();

        DB::table('order_items')->insert([
            'order_id' => $order->getKey(),
            'product_id' => $variant->product_id,
            'product_variant_id' => $variantId,
            'product_name' => 'Cancelled Stock Product',
            'variant_name' => 'Default',
            'sku' => $variant->sku,
            'quantity' => $quantity,
            'unit_mrp' => 999,
            'unit_price' => 999,
            'unit_discount' => 0,
            'line_subtotal' => 999 * $quantity,
            'line_discount' => 0,
            'line_tax' => 0,
            'line_total' => 999 * $quantity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function statusHistory(Order $order, ?string $fromStatus, string $toStatus, string $notes): void
    {
        DB::table('order_status_histories')->insert([
            'order_id' => $order->getKey(),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
