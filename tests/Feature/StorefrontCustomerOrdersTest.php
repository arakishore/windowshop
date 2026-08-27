<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\Customer;
use App\Models\CustomerCancellationReason;
use App\Models\MerchantCancellationReason;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\OrderStatusHistory;
use App\Models\OrderTotal;
use App\Models\PaymentStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\OrderStatusSeeder;
use Database\Seeders\PaymentStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontCustomerOrdersTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(OrderStatusSeeder::class);
        $this->seed(PaymentStatusSeeder::class);
        $this->currencySetting('symbol', 'INR ');
        $this->currencySetting('decimal_places', '2', AdminSetting::TYPE_INTEGER);
        $this->currencySetting('thousands_separator', ',');
        $this->currencySetting('decimal_separator', '.');
        $this->currencySetting('symbol_position', 'before');
    }

    public function test_global_customer_sees_orders_from_multiple_shops_newest_first_with_pagination(): void
    {
        $customer = $this->customerUser('orders-list@example.test', 'Orders Customer', '9422945101');
        $roleId = $this->assignRole($customer, 'customer');
        $globalCustomer = $this->globalCustomer($customer);
        $vana = $this->fixture('Vana Womens Studio');
        $fitzone = $this->fixture('FitZone Sports');
        $urban = $this->fixture('Urban Man');

        $oldest = null;
        foreach (range(1, 11) as $index) {
            $fixture = [$vana, $fitzone, $urban][($index - 1) % 3];
            $product = $this->product($fixture, 'Customer Order Product '.$index);
            $this->variant($product);
            $order = $this->order($globalCustomer, $fixture, [
                'order_number' => 'ORD-PAGE-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'created_at' => now()->subMinutes(20 - $index),
                'grand_total' => 100 + $index,
            ]);
            $this->item($order, $product, ['product_name' => 'Snapshot Product '.$index, 'quantity' => 1, 'line_total' => 100 + $index]);
            $this->total($order, OrderTotal::CODE_GRAND_TOTAL, 'Grand Total', 100 + $index, 100);
            $oldest = $index === 1 ? $order : $oldest;
        }

        $response = $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.orders'));

        $response->assertOk()
            ->assertSee('My Orders')
            ->assertSee('Vana Womens Studio')
            ->assertSee('FitZone Sports')
            ->assertSee('Urban Man')
            ->assertSee('ORD-PAGE-011')
            ->assertDontSee($oldest->order_number);

        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'ORD-PAGE-010'), strpos($content, 'ORD-PAGE-011'));
        $response->assertSee('View Order')
            ->assertSee('1 Item')
            ->assertSee('INR 111.00')
            ->assertSee('Delivery')
            ->assertSee('Cash on Delivery');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.orders', ['page' => 2]))
            ->assertOk()
            ->assertSee($oldest->order_number);
    }

    public function test_order_detail_uses_snapshots_progress_payment_addresses_and_comment_visibility(): void
    {
        $customer = $this->customerUser('orders-detail@example.test', 'Detail Customer', '9422945102');
        $roleId = $this->assignRole($customer, 'customer');
        $globalCustomer = $this->globalCustomer($customer);
        $fixture = $this->fixture('Detail Shop');
        $product = $this->product($fixture, 'Current Product Name');
        $this->variant($product, sellingPrice: '999.00');
        $this->image($product, 'products/orders/detail-thumb.webp');
        $order = $this->order($globalCustomer, $fixture, [
            'order_number' => 'ORD-DETAIL-001',
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'order_status' => OrderStatus::CODE_OUT_FOR_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'subtotal' => 1998,
            'discount_total' => 100,
            'shipping_total' => 50,
            'tax_total' => 20,
            'grand_total' => 1968,
            'amount_paid' => 500,
            'shipping_recipient_name' => 'Snapshot Ship Name',
            'shipping_mobile_country_code' => '+91',
            'shipping_mobile' => '9422945125',
            'shipping_address_line_1' => 'Flat 8, Snapshot Towers',
            'shipping_landmark' => 'Near Snapshot Park',
            'shipping_city' => 'Nashik',
            'shipping_state' => 'Maharashtra',
            'shipping_country' => 'India',
            'shipping_postal_code' => '422009',
            'billing_recipient_name' => 'Snapshot Bill Name',
            'billing_mobile_country_code' => '+91',
            'billing_mobile' => '9422945126',
            'billing_address_line_1' => 'Billing Snapshot Road',
            'billing_city' => 'Pune',
            'billing_state' => 'Maharashtra',
            'billing_country' => 'India',
            'billing_postal_code' => '411001',
        ]);
        $this->item($order, $product, [
            'product_name' => 'Historical Product Name',
            'variant_name' => 'Large / Grey',
            'unit_price' => 999,
            'line_discount' => 100,
            'line_tax' => 20,
            'line_total' => 1898,
            'quantity' => 2,
            'product_image' => 'products/orders/detail-thumb.webp',
        ]);
        $product->forceFill(['product_name' => 'Changed Current Product Name'])->save();
        $this->total($order, OrderTotal::CODE_SUBTOTAL, 'Subtotal', 1998, 10);
        $this->total($order, OrderTotal::CODE_ORDER_DISCOUNT, 'Discount', -100, 20);
        $this->total($order, OrderTotal::CODE_SHIPPING, 'Delivery', 50, 30);
        $this->total($order, OrderTotal::CODE_TAX, 'Tax', 20, 40);
        $this->total($order, OrderTotal::CODE_GRAND_TOTAL, 'Grand Total', 1968, 100);
        $placedAt = now()->subHours(5);
        $confirmedAt = now()->subHours(4);
        $processingAt = now()->subHours(3);
        $commentAt = now()->subHours(2)->subMinutes(30);
        $packedAt = now()->subHours(2);
        $shippedAt = now()->subHour();
        $outForDeliveryAt = now()->subMinutes(30);
        $this->history($order, null, Order::STATUS_PENDING, $placedAt);
        $this->history($order, Order::STATUS_PENDING, Order::STATUS_CONFIRMED, $confirmedAt);
        $this->history($order, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, $processingAt);
        $this->history($order, Order::STATUS_PROCESSING, OrderStatus::CODE_PACKED, $packedAt);
        $this->history($order, OrderStatus::CODE_PACKED, OrderStatus::CODE_SHIPPED, $shippedAt);
        $this->history($order, OrderStatus::CODE_SHIPPED, OrderStatus::CODE_OUT_FOR_DELIVERY, $outForDeliveryAt);
        $this->comment($order, 'Customer visible update', OrderComment::VISIBILITY_CUSTOMER, $commentAt);
        $this->comment($order, 'Merchant only internal note', OrderComment::VISIBILITY_MERCHANT_ONLY, $commentAt);
        $otherOrder = $this->order($globalCustomer, $fixture, ['order_number' => 'ORD-OTHER-COMMENTS']);
        $this->comment($otherOrder, 'Other order customer visible update', OrderComment::VISIBILITY_CUSTOMER, $commentAt);

        $response = $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.orders.show', $order));

        $response->assertOk()
            ->assertSee('ORD-DETAIL-001')
            ->assertSee('Out for Delivery')
            ->assertSee('Placed')
            ->assertSee($fixture['shop']->name)
            ->assertSee('Order Progress')
            ->assertSee('Packed')
            ->assertSee('Shipped')
            ->assertSee('Out for Delivery')
            ->assertSee('Delivered')
            ->assertSee('Historical Product Name')
            ->assertDontSee('Changed Current Product Name')
            ->assertSee('Large / Grey')
            ->assertSee('INR 999.00')
            ->assertSee('Discount INR 100.00')
            ->assertSee('INR 1,898.00')
            ->assertSee('Subtotal')
            ->assertSee('Grand Total')
            ->assertSee('INR 1,968.00')
            ->assertSee('Shipping Address')
            ->assertSee('Snapshot Ship Name')
            ->assertSee('Flat 8, Snapshot Towers')
            ->assertSee('Billing Address')
            ->assertSee('Snapshot Bill Name')
            ->assertSee('Payment')
            ->assertSee('Cash on Delivery')
            ->assertSee('Pending')
            ->assertSee('Amount Paid')
            ->assertSee('INR 500.00')
            ->assertSee('Balance')
            ->assertSee('INR 1,468.00')
            ->assertSee('Order Activity')
            ->assertSee('Order Placed')
            ->assertSee('We have received your order and will confirm it shortly.')
            ->assertSee('Order Confirmed')
            ->assertSee('Your order has been confirmed and is being prepared.')
            ->assertSee('Order Processing')
            ->assertSee('Your order is currently being prepared.')
            ->assertSee('Message from '.$fixture['shop']->name)
            ->assertSee('Customer visible update')
            ->assertSee(app_datetime($commentAt))
            ->assertDontSee('Merchant only internal note')
            ->assertDontSee('Other order customer visible update')
            ->assertDontSee('Merchant Only')
            ->assertDontSee('notify_email')
            ->assertDontSee('notify_sms')
            ->assertDontSee('notify_whatsapp')
            ->assertDontSee('Updated by');

        $content = $this->orderActivitySection($response->getContent());
        $this->assertLessThan(strpos($content, 'Order Confirmed'), strpos($content, 'Order Placed'));
        $this->assertLessThan(strpos($content, 'Order Processing'), strpos($content, 'Order Confirmed'));
        $this->assertLessThan(strpos($content, 'Customer visible update'), strpos($content, 'Order Processing'));
        $this->assertLessThan(strpos($content, 'Packed'), strpos($content, 'Customer visible update'));
    }

    public function test_pickup_progress_and_cancelled_order_are_customer_friendly(): void
    {
        $customer = $this->customerUser('orders-pickup@example.test', 'Pickup Customer', '9422945103');
        $roleId = $this->assignRole($customer, 'customer');
        $globalCustomer = $this->globalCustomer($customer);
        $fixture = $this->fixture('Pickup Shop');
        $product = $this->product($fixture, 'Pickup Product');
        $this->variant($product);
        $pickup = $this->order($globalCustomer, $fixture, [
            'order_number' => 'ORD-PICKUP-001',
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'order_status' => Order::STATUS_READY_FOR_PICKUP,
            'payment_method' => 'cash_at_shop',
        ]);
        $this->item($pickup, $product, ['product_name' => 'Pickup Snapshot Product']);
        $this->history($pickup, null, Order::STATUS_PENDING, now()->subHours(3));
        $this->history($pickup, Order::STATUS_PENDING, Order::STATUS_CONFIRMED, now()->subHours(2));
        $this->history($pickup, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, now()->subHour());
        $this->history($pickup, Order::STATUS_PROCESSING, Order::STATUS_READY_FOR_PICKUP, now()->subMinutes(20));

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.orders.show', $pickup))
            ->assertOk()
            ->assertSee('Ready for Pickup')
            ->assertSee('Order Activity')
            ->assertSee('Order Placed')
            ->assertSee('Order Confirmed')
            ->assertSee('Order Processing')
            ->assertSee('Pickup From')
            ->assertSee('Cash at Shop')
            ->assertDontSee('Packed')
            ->assertDontSee('Out for Delivery');

        $cancelled = $this->order($globalCustomer, $fixture, [
            'order_number' => 'ORD-CANCELLED-001',
            'order_status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now()->subMinutes(10),
        ]);
        $this->item($cancelled, $product, ['product_name' => 'Cancelled Product']);
        $this->history($cancelled, Order::STATUS_PENDING, Order::STATUS_CANCELLED, now()->subMinutes(10), [
            'reason_name' => 'Shop could not fulfil this order.',
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.orders.show', $cancelled))
            ->assertOk()
            ->assertSee('Cancelled')
            ->assertSee('Order Cancelled')
            ->assertSee('Your order has been cancelled.')
            ->assertSee('Order was cancelled')
            ->assertSee('Shop could not fulfil this order.');
    }

    public function test_order_detail_is_owned_by_authenticated_global_customer_and_guest_uses_login_flow(): void
    {
        $owner = $this->customerUser('orders-owner@example.test', 'Owner Customer', '9422945104');
        $other = $this->customerUser('orders-other@example.test', 'Other Customer', '9422945105');
        $ownerRole = $this->assignRole($owner, 'customer');
        $otherRole = $this->assignRole($other, 'customer');
        $fixture = $this->fixture('Secure Order Shop');
        $product = $this->product($fixture, 'Secure Product');
        $this->variant($product);
        $order = $this->order($this->globalCustomer($owner), $fixture, ['order_number' => 'ORD-SECURE-001']);
        $this->item($order, $product, ['product_name' => 'Secure Snapshot Product']);

        $this->get(route('storefront.account.orders'))
            ->assertRedirect(route('storefront.login'));

        $this->actingAs($owner)
            ->withSession(['active_role_id' => $ownerRole])
            ->get(route('storefront.account.orders.show', $order))
            ->assertOk()
            ->assertSee('ORD-SECURE-001');

        $this->actingAs($other)
            ->withSession(['active_role_id' => $otherRole])
            ->get(route('storefront.account.orders.show', $order))
            ->assertNotFound();
    }

    public function test_customer_can_cancel_own_pickup_cash_at_shop_order_from_allowed_statuses(): void
    {
        $customer = $this->customerUser('orders-cancel@example.test', 'Cancel Customer', '9422945106');
        $roleId = $this->assignRole($customer, 'customer');
        $globalCustomer = $this->globalCustomer($customer);
        $fixture = $this->fixture('Customer Cancel Shop');
        $merchantRoleId = $this->assignRole($fixture['merchantUser'], 'merchant');

        foreach ([Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, Order::STATUS_READY_FOR_PICKUP] as $status) {
            $product = $this->product($fixture, 'Cancellable Product '.$status);
            $variant = $this->variant($product);
            $variant->forceFill(['stock_quantity' => 8])->save();
            $order = $this->order($globalCustomer, $fixture, [
                'order_number' => 'ORD-CANCEL-'.Str::upper(Str::random(6)),
                'fulfilment_type' => Order::FULFILMENT_PICKUP,
                'order_status' => $status,
                'payment_method' => 'cash_at_shop',
                'payment_status' => PaymentStatus::CODE_PENDING,
                'amount_paid' => 0,
            ]);
            $this->item($order, $product, [
                'product_variant_id' => $variant->getKey(),
                'quantity' => 2,
                'line_total' => 200,
            ]);
            $this->history($order, null, $status, now()->subMinutes(5));

            $this->actingAs($customer)
                ->withSession(['active_role_id' => $roleId])
                ->get(route('storefront.account.orders.show', $order))
                ->assertOk()
                ->assertSee('Cancel Order');

            $this->actingAs($customer)
                ->withSession(['active_role_id' => $roleId])
                ->post(route('storefront.account.orders.cancel', $order), [
                    'cancellation_reason' => 'ordered_by_mistake',
                    'cancellation_note' => 'Please do not show this note to customer activity.',
                ])
                ->assertRedirect(route('storefront.account.orders.show', $order))
                ->assertSessionHas('success', 'Order cancelled.');

            $order->refresh();
            $this->assertSame(Order::STATUS_CANCELLED, $order->order_status);
            $this->assertNotNull($order->cancelled_at);
            $this->assertSame(10, (int) $variant->refresh()->stock_quantity);
            $this->assertDatabaseHas('order_status_histories', [
                'order_id' => $order->getKey(),
                'from_status' => $status,
                'to_status' => Order::STATUS_CANCELLED,
                'changed_by' => $customer->getKey(),
            ]);

            $history = $order->statusHistories()->latest('id')->firstOrFail();
            $this->assertSame('customer_cancel', $history->metadata['action'] ?? null);
            $this->assertSame('customer', $history->metadata['initiated_by'] ?? null);
            $this->assertSame('Ordered by mistake', $history->metadata['reason_name'] ?? null);

            $this->actingAs($customer)
                ->withSession(['active_role_id' => $roleId])
                ->get(route('storefront.account.orders.show', $order))
                ->assertOk()
                ->assertDontSee('Cancel Order')
                ->assertSee('Order Cancelled')
                ->assertSee('Your order has been cancelled. Reason: Ordered by mistake.')
                ->assertSee('Reason: Ordered by mistake')
                ->assertDontSee('Please do not show this note to customer activity.');
        }

        $latestOrder = Order::query()->where('customer_id', $globalCustomer->getKey())->latest('id')->firstOrFail();

        $this->actingAs($fixture['merchantUser'])
            ->withSession(['active_role_id' => $merchantRoleId, 'active_shop_id' => $fixture['shop']->getKey()])
            ->get(route('merchant.orders.show', $latestOrder))
            ->assertOk()
            ->assertSee('Order Activity')
            ->assertSee('Customer requested cancellation. Reason: Ordered by mistake. Note: Please do not show this note to customer activity.')
            ->assertSee('Updated by '.$customer->name);
    }

    public function test_customer_cancel_order_rejects_ineligible_forged_and_unowned_requests(): void
    {
        $customer = $this->customerUser('orders-cancel-security@example.test', 'Cancel Security Customer', '9422945107');
        $other = $this->customerUser('orders-cancel-other@example.test', 'Other Cancel Customer', '9422945108');
        $roleId = $this->assignRole($customer, 'customer');
        $otherRoleId = $this->assignRole($other, 'customer');
        $globalCustomer = $this->globalCustomer($customer);
        $otherGlobalCustomer = $this->globalCustomer($other);
        $fixture = $this->fixture('Customer Cancel Security Shop');
        $product = $this->product($fixture, 'Security Cancel Product');
        $variant = $this->variant($product);

        foreach ([
            'completed' => [
                'order_status' => Order::STATUS_COMPLETED,
                'payment_status' => PaymentStatus::CODE_PENDING,
                'amount_paid' => 0,
            ],
            'cancelled' => [
                'order_status' => Order::STATUS_CANCELLED,
                'payment_status' => PaymentStatus::CODE_PENDING,
                'amount_paid' => 0,
            ],
            'paid' => [
                'order_status' => Order::STATUS_PROCESSING,
                'payment_status' => PaymentStatus::CODE_PAID,
                'amount_paid' => 100,
            ],
            'delivery' => [
                'order_status' => Order::STATUS_PROCESSING,
                'fulfilment_type' => Order::FULFILMENT_DELIVERY,
                'payment_method' => 'cash_on_delivery',
                'payment_status' => PaymentStatus::CODE_PENDING,
                'amount_paid' => 0,
            ],
        ] as $label => $overrides) {
            $variant->forceFill(['stock_quantity' => 8])->save();
            $order = $this->order($globalCustomer, $fixture, array_merge([
                'order_number' => 'ORD-REJECT-'.Str::upper($label).'-'.Str::upper(Str::random(4)),
                'fulfilment_type' => Order::FULFILMENT_PICKUP,
                'order_status' => Order::STATUS_PROCESSING,
                'payment_method' => 'cash_at_shop',
            ], $overrides));
            $originalStatus = $order->order_status;
            $this->item($order, $product, ['product_variant_id' => $variant->getKey(), 'quantity' => 2]);

            $this->actingAs($customer)
                ->withSession(['active_role_id' => $roleId])
                ->get(route('storefront.account.orders.show', $order))
                ->assertOk()
                ->assertDontSee('data-bs-target="#cancelOrderModal"', false);

            $this->actingAs($customer)
                ->withSession(['active_role_id' => $roleId])
                ->from(route('storefront.account.orders.show', $order))
                ->post(route('storefront.account.orders.cancel', $order), [
                    'cancellation_reason' => 'want_to_change_items',
                ])
                ->assertRedirect(route('storefront.account.orders.show', $order))
                ->assertSessionHasErrors('order_status');

            $this->assertSame($originalStatus, $order->fresh()->order_status);
            $this->assertSame(8, (int) $variant->refresh()->stock_quantity);
        }

        $ownedOrder = $this->order($globalCustomer, $fixture, [
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'order_status' => Order::STATUS_PENDING,
            'payment_method' => 'cash_at_shop',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'amount_paid' => 0,
        ]);
        $this->item($ownedOrder, $product, ['product_variant_id' => $variant->getKey(), 'quantity' => 1]);

        $this->actingAs($other)
            ->withSession(['active_role_id' => $otherRoleId])
            ->post(route('storefront.account.orders.cancel', $ownedOrder), [
                'cancellation_reason' => 'ordered_by_mistake',
            ])
            ->assertNotFound();

        $otherOrder = $this->order($otherGlobalCustomer, $fixture, [
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'order_status' => Order::STATUS_PENDING,
            'payment_method' => 'cash_at_shop',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'amount_paid' => 0,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->post(route('storefront.account.orders.cancel', $otherOrder), [
                'cancellation_reason' => 'ordered_by_mistake',
            ])
            ->assertNotFound();

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->from(route('storefront.account.orders.show', $ownedOrder))
            ->post(route('storefront.account.orders.cancel', $ownedOrder), [
                'cancellation_reason' => 'other',
                'cancellation_note' => '',
            ])
            ->assertRedirect(route('storefront.account.orders.show', $ownedOrder))
            ->assertSessionHasErrors('cancellation_note');
    }

    public function test_customer_cancel_modal_uses_active_global_reasons_in_sort_order_not_merchant_reasons(): void
    {
        $customer = $this->customerUser('orders-cancel-reasons@example.test', 'Cancel Reason Customer', '9422945109');
        $roleId = $this->assignRole($customer, 'customer');
        $globalCustomer = $this->globalCustomer($customer);
        $fixture = $this->fixture('Customer Cancel Reason Shop');
        $product = $this->product($fixture, 'Reason Modal Product');
        $this->variant($product);
        $order = $this->order($globalCustomer, $fixture, [
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'order_status' => Order::STATUS_PENDING,
            'payment_method' => 'cash_at_shop',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'amount_paid' => 0,
        ]);
        $this->item($order, $product);

        CustomerCancellationReason::query()->update(['status' => CustomerCancellationReason::STATUS_INACTIVE]);
        CustomerCancellationReason::query()->create([
            'code' => 'second_global_reason',
            'name' => 'Second Global Reason',
            'requires_comment' => false,
            'sort_order' => 20,
            'status' => CustomerCancellationReason::STATUS_ACTIVE,
        ]);
        CustomerCancellationReason::query()->create([
            'code' => 'first_global_reason',
            'name' => 'First Global Reason',
            'requires_comment' => true,
            'sort_order' => 10,
            'status' => CustomerCancellationReason::STATUS_ACTIVE,
        ]);
        CustomerCancellationReason::query()->create([
            'code' => 'hidden_global_reason',
            'name' => 'Hidden Global Reason',
            'requires_comment' => false,
            'sort_order' => 1,
            'status' => CustomerCancellationReason::STATUS_INACTIVE,
        ]);
        MerchantCancellationReason::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'code' => 'merchant_only_reason',
            'name' => 'Merchant Only Reason',
            'sort_order' => 1,
            'customer_selectable' => true,
            'merchant_selectable' => true,
            'requires_comment' => false,
            'status' => MerchantCancellationReason::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.orders.show', $order));

        $response->assertOk()
            ->assertSee('First Global Reason')
            ->assertSee('Second Global Reason')
            ->assertDontSee('Hidden Global Reason')
            ->assertDontSee('Merchant Only Reason');

        $select = $this->cancellationReasonSelect($response->getContent());
        $this->assertLessThan(strpos($select, 'Second Global Reason'), strpos($select, 'First Global Reason'));
        $this->assertStringContainsString('value="first_global_reason" data-requires-note="1"', $select);
    }

    /**
     * @return array{merchantUser: User, merchant: MerchantProfile, shop: Shop, root: ProductCategory, category: ProductCategory}
     */
    private function fixture(string $name): array
    {
        $merchantUser = User::query()->create([
            'name' => $name.' Owner',
            'email' => Str::slug($name).'-'.Str::random(6).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $merchantUser->getKey(),
            'business_name' => $name,
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $root = ProductCategory::query()->create([
            'name' => $name.' Root',
            'slug' => Str::slug($name).'-root-'.Str::random(6),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => $name.' Leaf',
            'slug' => Str::slug($name).'-leaf-'.Str::random(6),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'address_line_1' => 'Shop Road',
            'pincode' => '422009',
            'status' => 'active',
        ]);

        return compact('merchantUser', 'merchant', 'shop', 'root', 'category');
    }

    private function product(array $fixture, string $name): Product
    {
        return Product::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'root_product_category_id' => $fixture['root']->getKey(),
            'product_category_id' => $fixture['category']->getKey(),
            'product_name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'tax_mode' => 'inherit',
            'status' => 'active',
        ]);
    }

    private function variant(Product $product, string $sellingPrice = '100.00'): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'name' => $product->product_name,
            'mrp' => $sellingPrice,
            'selling_price' => $sellingPrice,
            'stock_quantity' => 5,
            'low_stock_threshold' => 0,
            'is_sellable' => true,
            'is_default' => true,
            'sort_order' => 0,
            'status' => 'active',
        ]);
    }

    private function image(Product $product, string $path): ProductImage
    {
        Storage::disk('public')->put($path, 'image');

        $image = ProductImage::query()->create([
            'product_id' => $product->getKey(),
            'image_path' => str_replace('-thumb.', '-original.', $path),
            'thumbnail_path' => $path,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $product->forceFill(['primary_image_id' => $image->getKey()])->save();

        return $image;
    }

    private function order(Customer $customer, array $fixture, array $overrides = []): Order
    {
        $order = Order::query()->create(array_merge([
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'customer_id' => $customer->getKey(),
            'created_source' => Order::SOURCE_STOREFRONT,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'order_status' => Order::STATUS_PENDING,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'currency_code' => 'INR',
            'subtotal' => 100,
            'grand_total' => 100,
            'customer_name' => $customer->name,
            'customer_mobile' => $customer->mobile,
            'customer_email' => $customer->email,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        if (array_key_exists('created_at', $overrides) || array_key_exists('updated_at', $overrides)) {
            $order->forceFill([
                'created_at' => $overrides['created_at'] ?? $order->created_at,
                'updated_at' => $overrides['updated_at'] ?? ($overrides['created_at'] ?? $order->updated_at),
            ])->save();
        }

        return $order->refresh();
    }

    private function item(Order $order, Product $product, array $overrides = []): OrderItem
    {
        return OrderItem::query()->create(array_merge([
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'product_variant_id' => null,
            'product_name' => $product->product_name,
            'variant_name' => null,
            'sku' => 'SKU-'.$product->getKey(),
            'quantity' => 1,
            'unit_mrp' => 100,
            'unit_price' => 100,
            'unit_discount' => 0,
            'line_subtotal' => 100,
            'line_discount' => 0,
            'line_tax' => 0,
            'line_total' => 100,
        ], $overrides));
    }

    private function total(Order $order, string $code, string $title, float $amount, int $sortOrder): OrderTotal
    {
        return OrderTotal::query()->create([
            'order_id' => $order->getKey(),
            'code' => $code,
            'title' => $title,
            'amount' => $amount,
            'sort_order' => $sortOrder,
            'source' => 'test',
        ]);
    }

    private function history(Order $order, ?string $from, string $to, mixed $createdAt, array $metadata = []): OrderStatusHistory
    {
        return OrderStatusHistory::query()->create([
            'order_id' => $order->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'notes' => 'History '.$to,
            'metadata' => $metadata,
            'created_at' => $createdAt,
        ]);
    }

    private function comment(Order $order, string $comment, string $visibility, mixed $createdAt = null): OrderComment
    {
        $orderComment = OrderComment::query()->create([
            'order_id' => $order->getKey(),
            'author_type' => OrderComment::AUTHOR_MERCHANT,
            'comment' => $comment,
            'visibility' => $visibility,
        ]);

        if ($createdAt !== null) {
            $orderComment->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        return $orderComment->refresh();
    }

    private function customerUser(string $email, string $name, string $mobile): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $user->forceFill(['mobile' => $mobile])->save();

        return $user->refresh();
    }

    private function assignRole(User $user, string $slug): int
    {
        DB::table('auth_roles')->updateOrInsert([
            'slug' => $slug,
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'description' => Str::headline($slug).' role',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = (int) DB::table('auth_roles')->where('slug', $slug)->value('id');

        DB::table('auth_user_roles')->updateOrInsert([
            'user_id' => $user->getKey(),
            'role_id' => $roleId,
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $roleId;
    }

    private function globalCustomer(User $user): Customer
    {
        return Customer::query()->firstOrCreate([
            'user_id' => $user->getKey(),
        ], [
            'name' => $user->name,
            'mobile_country_code' => '+91',
            'mobile' => $user->mobile,
            'mobile_normalized' => '91'.$user->mobile,
            'email' => $user->email,
            'status' => Customer::STATUS_ACTIVE,
        ]);
    }

    private function currencySetting(string $key, string $value, string $type = AdminSetting::TYPE_STRING): void
    {
        AdminSetting::query()->updateOrCreate(
            ['group' => 'currency', 'setting_key' => $key],
            ['setting_value' => $value, 'setting_type' => $type],
        );
    }

    private function orderActivitySection(string $content): string
    {
        $start = strpos($content, '<h6 class="mb-16">Order Activity</h6>');
        $this->assertIsInt($start);

        $end = strpos($content, '</section>', $start);
        $this->assertIsInt($end);

        return substr($content, $start, $end - $start);
    }

    private function cancellationReasonSelect(string $content): string
    {
        $start = strpos($content, '<select id="cancellation_reason"');
        $this->assertIsInt($start);

        $end = strpos($content, '</select>', $start);
        $this->assertIsInt($end);

        return substr($content, $start, $end - $start);
    }
}
