<?php

namespace Tests\Feature;

use App\Mail\TransactionalNotificationMail;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTotal;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\NotificationMessage;
use App\Services\Notification\EmailConfigurationService;
use App\Services\Notification\MerchantOperationalEmailRecipientResolver;
use App\Services\Notification\OrderEmailPresenter;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MerchantNotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', static fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTemplateSeeder::class);
    }

    public function test_authorized_merchant_manages_shop_scoped_recipient_groups(): void
    {
        [$merchant, $shopA] = $this->merchantShop('Owner', 'owner@example.test');
        $shopB = $this->shop($merchant, 'Second Shop');
        $this->merchantRole($merchant->user);

        $this->actingAs($merchant->user)->withSession(['active_shop_id' => $shopA->getKey()])
            ->get(route('merchant.notification-settings.edit'))
            ->assertOk()->assertSee('owner@example.test')->assertSee('Shop Owner')->assertSee('Required')
            ->assertSee('SMS notifications are not currently available.')
            ->assertSee('WhatsApp notifications are not currently available.')
            ->assertDontSee('Email subject')->assertDontSee('Email body');

        $merchant->user->update(['email' => 'new-owner@example.test']);
        $this->actingAs($merchant->user->fresh())->withSession(['active_shop_id' => $shopA->getKey()])
            ->get(route('merchant.notification-settings.edit'))->assertOk()->assertSee('new-owner@example.test');

        $this->actingAs($merchant->user)->withSession(['active_shop_id' => $shopA->getKey()])
            ->put(route('merchant.notification-settings.update'), [
                'shop_id' => $shopB->getKey(),
                'email' => [
                    'additional_to' => ' Manager@Example.test, sales@example.test, ',
                    'cc' => 'accounts@example.test',
                    'bcc' => 'backoffice@example.test',
                ],
            ])->assertSessionHas('success');

        $routingA = app(MerchantOperationalEmailRecipientResolver::class)->resolve($shopA);
        $this->assertSame(['new-owner@example.test', 'manager@example.test', 'sales@example.test'], $routingA['to']);
        $this->assertSame(['accounts@example.test'], $routingA['cc']);
        $this->assertSame(['backoffice@example.test'], $routingA['bcc']);
        $this->assertSame([], app(MerchantOperationalEmailRecipientResolver::class)->resolve($shopB)['additional_to']);
        $this->actingAs($merchant->user->fresh())->withSession(['active_shop_id' => $shopA->getKey()])
            ->get(route('merchant.notification-settings.edit'))
            ->assertSee('value="manager@example.test, sales@example.test"', false);

        $this->actingAs($merchant->user)->withSession(['active_shop_id' => $shopA->getKey()])
            ->put(route('merchant.notification-settings.update'), ['email' => ['additional_to' => '', 'cc' => '', 'bcc' => '']])
            ->assertSessionHas('success');
        $this->assertSame([], app(MerchantOperationalEmailRecipientResolver::class)->resolve($shopA)['additional_to']);
    }

    public function test_validation_rejects_invalid_owner_duplicate_and_cross_group_duplicates(): void
    {
        [$merchant, $shop] = $this->merchantShop('Owner', 'owner@example.test');
        $this->merchantRole($merchant->user);
        $session = ['active_shop_id' => $shop->getKey()];

        $this->actingAs($merchant->user)->withSession($session)->put(route('merchant.notification-settings.update'), [
            'email' => ['additional_to' => 'a@example.com, invalid', 'cc' => '', 'bcc' => ''],
        ])->assertSessionHasErrors('email.additional_to.1')
            ->assertSessionHasInput('email.additional_to', 'a@example.com, invalid');

        foreach (['additional_to', 'cc', 'bcc'] as $group) {
            $payload = ['additional_to' => '', 'cc' => '', 'bcc' => ''];
            $payload[$group] = 'OWNER@example.test';
            $this->actingAs($merchant->user)->withSession($session)->put(route('merchant.notification-settings.update'), ['email' => $payload])
                ->assertSessionHasErrors("email.{$group}.0");
        }

        $this->actingAs($merchant->user)->withSession($session)->put(route('merchant.notification-settings.update'), [
            'email' => ['additional_to' => 'team@example.test', 'cc' => 'TEAM@example.test', 'bcc' => ''],
        ])->assertSessionHasErrors('email.cc.0');

        $this->actingAs($merchant->user)->withSession($session)->put(route('merchant.notification-settings.update'), [
            'email' => ['additional_to' => 'a@example.test, A@example.test', 'cc' => '', 'bcc' => ''],
        ])->assertSessionHasErrors('email.additional_to.1');

        $tooMany = collect(range(1, 11))->map(fn (int $number): string => "user{$number}@example.test")->implode(', ');
        $this->actingAs($merchant->user)->withSession($session)->put(route('merchant.notification-settings.update'), [
            'email' => ['additional_to' => $tooMany, 'cc' => '', 'bcc' => ''],
        ])->assertSessionHasErrors('email.additional_to');
    }

    public function test_another_merchant_cannot_select_or_modify_the_first_merchants_shop(): void
    {
        [$merchantA, $shopA] = $this->merchantShop('A', 'a@example.test');
        [$merchantB, $shopB] = $this->merchantShop('B', 'b@example.test');
        $this->merchantRole($merchantB->user);

        $this->actingAs($merchantB->user)->withSession(['active_shop_id' => $shopA->getKey()])
            ->put(route('merchant.notification-settings.update'), ['email' => ['additional_to' => 'b-team@example.test', 'cc' => '', 'bcc' => '']])
            ->assertSessionHas('success');

        $this->assertSame([], app(MerchantOperationalEmailRecipientResolver::class)->resolve($shopA)['additional_to']);
        $this->assertSame(['b-team@example.test'], app(MerchantOperationalEmailRecipientResolver::class)->resolve($shopB)['additional_to']);
    }

    public function test_operational_email_uses_primary_additional_to_cc_and_bcc_once(): void
    {
        Mail::fake();
        [$merchant, $shop] = $this->merchantShop('Mail', 'owner@example.test');
        $routing = app(MerchantOperationalEmailRecipientResolver::class)->save($shop, [
            'additional_to' => ['manager@example.test'],
            'cc' => ['accounts@example.test'],
            'bcc' => ['hidden@example.test'],
        ]);
        app(EmailConfigurationService::class)->save([
            'enabled' => true, 'smtp.host' => 'smtp.example.test', 'smtp.port' => 587, 'smtp.encryption' => 'tls',
            'from_name' => 'WindowShop', 'from_email' => 'sender@example.test',
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-MAIL-PICKUP', 'merchant_id' => $merchant->getKey(), 'shop_id' => $shop->getKey(),
            'created_source' => Order::SOURCE_STOREFRONT, 'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'order_status' => Order::STATUS_PENDING, 'payment_method' => 'cash_at_shop', 'payment_status' => Order::PAYMENT_PENDING,
            'currency_code' => 'INR', 'customer_name' => 'Snapshot Customer', 'customer_email' => 'snapshot@example.test',
            'customer_mobile' => '9876543210', 'grand_total' => '450.00',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->getKey(), 'product_name' => 'Snapshot Kurta', 'variant_name' => 'Blue / M',
            'quantity' => 2, 'unit_price' => '225.00', 'line_total' => '450.00',
        ]);
        OrderTotal::query()->create(['order_id' => $order->getKey(), 'code' => OrderTotal::CODE_SUBTOTAL, 'title' => 'Subtotal', 'amount' => '450.00', 'sort_order' => 10]);
        OrderTotal::query()->create(['order_id' => $order->getKey(), 'code' => OrderTotal::CODE_GRAND_TOTAL, 'title' => 'Grand Total', 'amount' => '450.00', 'sort_order' => 100]);

        $result = app(EmailChannel::class)->send(new NotificationMessage(
            'order.new.merchant', 'merchant', 'email', $routing['primary'], shopId: $shop->getKey(), merchantId: $merchant->getKey(),
            relatedType: 'order', relatedId: $order->uuid,
            context: ['merchant_name' => 'Owner', 'order_number' => 'ORD-1', 'shop_name' => $shop->name],
            metadata: ['email_routing' => $routing],
        ));

        $this->assertSame('sent', $result->status);
        Mail::assertSent(TransactionalNotificationMail::class, function (TransactionalNotificationMail $mail) use ($order): bool {
            return $mail->hasTo('owner@example.test')
                && $mail->hasTo('manager@example.test')
                && $mail->hasCc('accounts@example.test')
                && $mail->hasBcc('hidden@example.test')
                && data_get($mail->orderEmail, 'order_number') === 'ORD-MAIL-PICKUP'
                && data_get($mail->orderEmail, 'fulfilment_label') === 'Pickup'
                && data_get($mail->orderEmail, 'delivery_address') === []
                && data_get($mail->orderEmail, 'payment_method_label') === 'Cash at Shop'
                && data_get($mail->orderEmail, 'items.0.name') === 'Snapshot Kurta'
                && data_get($mail->orderEmail, 'items.0.quantity') === 2
                && data_get($mail->orderEmail, 'cta_url') === route('merchant.orders.show', $order);
        });
        Mail::assertSentCount(1);
    }

    public function test_merchant_order_email_uses_delivery_and_commercial_snapshots(): void
    {
        [$merchant, $shop] = $this->merchantShop('Delivery', 'delivery@example.test');
        $order = Order::query()->create([
            'order_number' => 'ORD-MAIL-DELIVERY', 'merchant_id' => $merchant->getKey(), 'shop_id' => $shop->getKey(),
            'created_source' => Order::SOURCE_STOREFRONT, 'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'order_status' => Order::STATUS_PENDING, 'payment_method' => 'cash_on_delivery', 'payment_status' => Order::PAYMENT_PENDING,
            'currency_code' => 'INR', 'customer_name' => 'Historical Customer', 'customer_email' => 'history@example.test',
            'customer_mobile' => '9000000001', 'shipping_recipient_name' => 'Historical Recipient',
            'shipping_address_line_1' => '12 Snapshot Road', 'shipping_address_line_2' => 'Old Building',
            'shipping_city' => 'Nashik', 'shipping_state' => 'Maharashtra', 'shipping_country' => 'India',
            'shipping_postal_code' => '422001', 'subtotal' => '500.00', 'discount_total' => '50.00', 'grand_total' => '450.00',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->getKey(), 'product_name' => 'Archived Product Name', 'variant_name' => 'Snapshot Option',
            'quantity' => 2, 'unit_price' => '250.00', 'line_total' => '450.00',
            'metadata' => ['promotion' => ['name' => 'Snapshot Offer', 'coupon_code' => 'SAVE50', 'details' => []]],
        ]);
        $duplicate = OrderItem::query()->create([
            'order_id' => $order->getKey(), 'product_name' => 'Duplicate Product', 'variant_name' => 'Duplicate Product',
            'sku' => 'DUP-001', 'quantity' => 1, 'unit_price' => '10.00', 'line_total' => '10.00',
        ]);
        $caseDuplicate = OrderItem::query()->create([
            'order_id' => $order->getKey(), 'product_name' => 'Case Product', 'variant_name' => '  CASE PRODUCT  ',
            'sku' => 'CASE-001', 'quantity' => 1, 'unit_price' => '10.00', 'line_total' => '10.00',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->getKey(), 'product_name' => 'Empty Variant Product', 'variant_name' => '   ',
            'sku' => 'EMPTY-001', 'quantity' => 1, 'unit_price' => '10.00', 'line_total' => '10.00',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->getKey(), 'product_name' => 'Plain Product', 'variant_name' => null,
            'sku' => null, 'quantity' => 1, 'unit_price' => '10.00', 'line_total' => '10.00',
        ]);
        foreach ([
            [OrderTotal::CODE_SUBTOTAL, 'Subtotal', '500.00', 10],
            [OrderTotal::CODE_COUPON_DISCOUNT, 'Coupon Discount', '-50.00', 20],
            [OrderTotal::CODE_SHIPPING, 'Delivery Charge', '0.00', 30],
            [OrderTotal::CODE_GRAND_TOTAL, 'Grand Total', '450.00', 100],
        ] as [$code, $title, $amount, $sort]) {
            OrderTotal::query()->create(compact('code', 'title', 'amount') + ['order_id' => $order->getKey(), 'sort_order' => $sort]);
        }

        $email = app(OrderEmailPresenter::class)->present($order, 'order.new.merchant', ['merchant_name' => 'Owner']);

        $this->assertSame('ORD-MAIL-DELIVERY', $email['order_number']);
        $this->assertSame('Historical Customer', $email['customer']['name']);
        $this->assertSame('Delivery', $email['fulfilment_label']);
        $this->assertSame('Cash on Delivery', $email['payment_method_label']);
        $this->assertContains('12 Snapshot Road, Old Building', $email['delivery_address']);
        $this->assertSame('Archived Product Name', $email['items'][0]['name']);
        $this->assertSame(2, $email['items'][0]['quantity']);
        $this->assertSame('Snapshot Option', $email['items'][0]['variant']);
        $this->assertNull($email['items'][1]['variant']);
        $this->assertSame('DUP-001', $email['items'][1]['sku']);
        $this->assertNull($email['items'][2]['variant']);
        $this->assertSame('CASE-001', $email['items'][2]['sku']);
        $this->assertNull($email['items'][3]['variant']);
        $this->assertSame('EMPTY-001', $email['items'][3]['sku']);
        $this->assertNull($email['items'][4]['variant']);
        $this->assertNull($email['items'][4]['sku']);
        $this->assertSame('Duplicate Product', $duplicate->fresh()->variant_name);
        $this->assertSame('  CASE PRODUCT  ', $caseDuplicate->fresh()->variant_name);
        $this->assertSame('Snapshot Offer', $email['promotions'][0]['name']);
        $this->assertSame('SAVE50', $email['promotions'][0]['coupon_code']);
        $this->assertCount(3, $email['totals']);
        $this->assertSame(route('merchant.orders.show', $order), $email['cta_url']);

        $html = (new TransactionalNotificationMail(
            'New order received', 'Hello Merchant', $shop->name, null, 'WindowShop', 'sender@example.test', 'WindowShop',
            orderEmail: $email,
        ))->render();
        $this->assertStringContainsString('VIEW &amp; PROCESS ORDER', $html);
        $this->assertStringContainsString('12 Snapshot Road', $html);
        $this->assertStringContainsString('Archived Product Name', $html);
        $this->assertSame(1, substr_count($html, 'Duplicate Product'));
        $this->assertStringContainsString('DUP-001', $html);
        $this->assertSame(1, substr_count($html, 'Case Product'));
        $this->assertStringContainsString('CASE-001', $html);
        $this->assertStringContainsString('EMPTY-001', $html);
        $this->assertStringContainsString('Button not working? Copy and paste this link into your browser:', $html);
        $this->assertSame(2, substr_count($html, 'href="'.$email['cta_url'].'"'));
        $this->assertSame(3, substr_count($html, $email['cta_url']));
        $this->assertStringContainsString('overflow-wrap:anywhere;word-break:break-word', $html);

        $customerEmail = app(OrderEmailPresenter::class)->present($order, 'order.placed.customer');
        $this->assertSame('customer', $customerEmail['audience']);
        $this->assertSame('Order received — ORD-MAIL-DELIVERY', $customerEmail['subject']);
        $this->assertSame('Order received', $customerEmail['heading']);
        $this->assertStringContainsString('Hi Historical Customer,', $customerEmail['greeting']);
        $this->assertStringNotContainsString('confirmed', strtolower($customerEmail['intro']));
        $this->assertSame('VIEW YOUR ORDER', $customerEmail['cta_label']);
        $this->assertSame(route('storefront.account.orders.show', $order), $customerEmail['cta_url']);
        $this->assertStringNotContainsString('/merchant/', $customerEmail['cta_url']);
        $customerHtml = (new TransactionalNotificationMail(
            $customerEmail['subject'], '', $shop->name, null, 'WindowShop', 'sender@example.test', 'WindowShop',
            orderEmail: $customerEmail,
        ))->render();
        $this->assertSame(2, substr_count($customerHtml, 'href="'.$customerEmail['cta_url'].'"'));
        $this->assertSame(3, substr_count($customerHtml, $customerEmail['cta_url']));
        $this->assertStringContainsString('Button not working? Copy and paste this link into your browser:', $customerHtml);

        $adminEmail = app(OrderEmailPresenter::class)->present($order, 'order.new.admin');
        $this->assertSame('admin', $adminEmail['audience']);
        $this->assertSame('New order received — ORD-MAIL-DELIVERY', $adminEmail['subject']);
        $this->assertStringContainsString('A new order has been placed', $adminEmail['intro']);
        $this->assertNull($adminEmail['cta_url']);
        $adminHtml = (new TransactionalNotificationMail(
            $adminEmail['subject'], '', $shop->name, null, 'WindowShop', 'sender@example.test', 'WindowShop',
            orderEmail: $adminEmail,
        ))->render();
        $this->assertStringNotContainsString('Button not working?', $adminHtml);
    }

    /** @return array{MerchantProfile, Shop} */
    private function merchantShop(string $name, string $email): array
    {
        $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => Hash::make('password'), 'status' => 'active']);
        $merchant = MerchantProfile::query()->create(['user_id' => $user->getKey(), 'business_name' => $name, 'verification_status' => 'approved', 'status' => 'active']);

        return [$merchant, $this->shop($merchant, $name.' Shop')];
    }

    private function shop(MerchantProfile $merchant, string $name): Shop
    {
        $category = ProductCategory::query()->create(['name' => $name.' Category', 'slug' => Str::slug($name).'-'.Str::random(5), 'status' => 'active']);

        return Shop::query()->create(['merchant_id' => $merchant->getKey(), 'root_product_category_id' => $category->getKey(), 'name' => $name, 'slug' => Str::slug($name).'-'.Str::random(5), 'address_line_1' => 'Road', 'status' => 'active']);
    }

    private function merchantRole(User $user): void
    {
        $role = DB::table('auth_roles')->where('slug', 'merchant')->value('id') ?: DB::table('auth_roles')->insertGetId(['name' => 'Merchant', 'slug' => 'merchant', 'status' => 'active']);
        DB::table('auth_user_roles')->insert(['user_id' => $user->getKey(), 'role_id' => $role]);
    }
}
