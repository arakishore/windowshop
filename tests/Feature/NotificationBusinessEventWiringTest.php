<?php

namespace Tests\Feature;

use App\Enums\MerchantStatus;
use App\Enums\MerchantVerificationStatus;
use App\Events\CustomerRegistered;
use App\Events\MerchantAccountCreated;
use App\Events\MerchantLifecycleChanged;
use App\Events\OrderStatusChanged;
use App\Events\StorefrontOrderPlaced;
use App\Listeners\DispatchBusinessNotifications;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\User;
use App\Services\Merchant\MerchantService;
use App\Services\Merchant\ShopSettingsService;
use App\Services\Notification\AdditionalMerchantRecipientService;
use App\Services\Order\OrderStatusService;
use App\Services\Promotion\Redemptions\CouponRedemptionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NotificationBusinessEventWiringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['notification_delivery_logs', 'order_status_histories', 'shop_settings', 'merchant_settings', 'admin_settings', 'orders', 'shops', 'merchant_profiles', 'auth_user_roles', 'auth_roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('auth_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('auth_user_roles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();
        });
        Schema::create('merchant_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('business_name');
            $table->string('legal_name')->nullable();
            $table->string('business_type')->nullable();
            $table->string('gst_number')->nullable();
            $table->boolean('has_shop_license')->nullable();
            $table->boolean('has_fssai')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_mobile')->nullable();
            $table->string('verification_status');
            $table->string('status');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('shops', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('merchant_id');
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('order_number');
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_mobile')->nullable();
            $table->string('fulfilment_type')->default('pickup');
            $table->string('order_status')->default('pending');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
        });
        foreach (['merchant_settings' => 'merchant_id', 'shop_settings' => 'shop_id'] as $tableName => $owner) {
            Schema::create($tableName, function (Blueprint $table) use ($owner): void {
                $table->id();
                $table->unsignedBigInteger($owner);
                $table->string('group');
                $table->string('setting_key');
                $table->longText('setting_value')->nullable();
                $table->string('setting_type')->default('string');
                $table->timestamps();
                $table->unique([$owner, 'group', 'setting_key']);
            });
        }
        Schema::create('admin_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('setting_key');
            $table->longText('setting_value')->nullable();
            $table->string('setting_type')->default('string');
            $table->timestamps();
            $table->unique(['group', 'setting_key']);
        });
        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('delivery_key')->nullable()->unique();
            $table->string('notification_key');
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->string('related_type')->nullable();
            $table->string('related_id')->nullable();
            $table->string('channel');
            $table->string('destination')->nullable();
            $table->string('provider_mode')->nullable();
            $table->string('provider')->nullable();
            $table->string('status');
            $table->string('error_summary')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function test_merchant_lifecycle_mapping_evaluates_independent_real_transitions_only(): void
    {
        Event::fake([MerchantLifecycleChanged::class]);
        $merchant = $this->merchant();
        $service = app(MerchantService::class);

        $service->update($merchant, $this->merchantData(MerchantStatus::SUSPENDED->value, MerchantVerificationStatus::APPROVED->value), 99);

        Event::assertDispatched(MerchantLifecycleChanged::class, fn ($event): bool => $event->notificationKey === 'merchant.approved');
        Event::assertDispatched(MerchantLifecycleChanged::class, fn ($event): bool => $event->notificationKey === 'merchant.suspended');

        Event::fake([MerchantLifecycleChanged::class]);
        $service->update($merchant->fresh(), $this->merchantData(MerchantStatus::SUSPENDED->value, MerchantVerificationStatus::APPROVED->value), 99);
        Event::assertNotDispatched(MerchantLifecycleChanged::class);

        $service->update($merchant->fresh(), $this->merchantData(MerchantStatus::ACTIVE->value, MerchantVerificationStatus::REJECTED->value), 99);
        Event::assertDispatched(MerchantLifecycleChanged::class, fn ($event): bool => $event->notificationKey === 'merchant.rejected');
        Event::assertDispatched(MerchantLifecycleChanged::class, fn ($event): bool => $event->notificationKey === 'merchant.reactivated');
    }

    public function test_customer_registration_uses_current_account_contact_and_is_idempotent(): void
    {
        $user = User::query()->findOrFail($this->user('Registered Customer', 'customer@example.test', '9123456789'));
        $event = new CustomerRegistered($user, 'customer-registration-occurrence');

        app(DispatchBusinessNotifications::class)->customerRegistered($event);
        app(DispatchBusinessNotifications::class)->customerRegistered($event);

        $this->assertDatabaseHas('notification_delivery_logs', [
            'notification_key' => 'customer.registered',
            'channel' => 'email',
            'destination' => 'customer@example.test',
            'status' => 'not_configured',
        ]);
        $this->assertSame(3, DB::table('notification_delivery_logs')->where('notification_key', 'customer.registered')->count());
    }

    public function test_merchant_account_created_uses_primary_contact_not_operational_additions(): void
    {
        $merchant = $this->merchant();
        app(AdditionalMerchantRecipientService::class)->set($merchant->getKey(), 'email', ['operations@example.test']);

        app(DispatchBusinessNotifications::class)->merchantAccountCreated(new MerchantAccountCreated($merchant, 'merchant-created-occurrence'));

        $this->assertDatabaseHas('notification_delivery_logs', ['notification_key' => 'merchant.account_created', 'channel' => 'email', 'destination' => 'primary@example.test']);
        $this->assertDatabaseMissing('notification_delivery_logs', ['notification_key' => 'merchant.account_created', 'destination' => 'operations@example.test']);
    }

    public function test_after_commit_domain_event_is_not_processed_when_transaction_rolls_back(): void
    {
        $user = User::query()->findOrFail($this->user('Rollback Customer', 'rollback@example.test', '9234567890'));

        try {
            DB::transaction(function () use ($user): void {
                CustomerRegistered::dispatch($user, 'rolled-back-occurrence');
                throw new \RuntimeException('Force rollback');
            });
        } catch (\RuntimeException) {
            // Expected rollback.
        }

        $this->assertDatabaseMissing('notification_delivery_logs', ['notification_key' => 'customer.registered']);
    }

    public function test_after_commit_domain_event_is_processed_after_successful_commit(): void
    {
        $user = User::query()->findOrFail($this->user('Committed Customer', 'committed@example.test', '9345678901'));

        DB::transaction(function () use ($user): void {
            CustomerRegistered::dispatch($user, 'committed-occurrence');
            $this->assertDatabaseMissing('notification_delivery_logs', ['notification_key' => 'customer.registered']);
        });

        $this->assertDatabaseHas('notification_delivery_logs', [
            'notification_key' => 'customer.registered',
            'channel' => 'email',
            'destination' => 'committed@example.test',
        ]);
    }

    public function test_lifecycle_uses_only_primary_contact_and_duplicate_occurrence_is_idempotent(): void
    {
        $merchant = $this->merchant();
        app(AdditionalMerchantRecipientService::class)->set($merchant->getKey(), 'email', ['operations@example.test']);
        $event = new MerchantLifecycleChanged($merchant, 'merchant.approved', 'lifecycle-occurrence');

        app(DispatchBusinessNotifications::class)->merchantLifecycleChanged($event);
        app(DispatchBusinessNotifications::class)->merchantLifecycleChanged($event);

        $this->assertDatabaseHas('notification_delivery_logs', ['notification_key' => 'merchant.approved', 'channel' => 'email', 'destination' => 'primary@example.test', 'status' => 'not_configured']);
        $this->assertDatabaseMissing('notification_delivery_logs', ['notification_key' => 'merchant.approved', 'destination' => 'operations@example.test']);
        $this->assertSame(3, DB::table('notification_delivery_logs')->where('notification_key', 'merchant.approved')->count());
    }

    public function test_storefront_order_uses_snapshot_additional_recipients_deduplicates_and_obeys_global_admin_default(): void
    {
        $merchant = $this->merchant();
        $shopId = DB::table('shops')->insertGetId(['uuid' => (string) Str::uuid(), 'merchant_id' => $merchant->getKey(), 'name' => 'Test Shop', 'status' => 'active']);
        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(), 'order_number' => 'ORD-TEST', 'merchant_id' => $merchant->getKey(), 'shop_id' => $shopId,
            'customer_id' => 77, 'customer_name' => 'Snapshot Customer', 'customer_email' => 'snapshot@example.test', 'customer_mobile' => '9876543210',
        ]);
        app(AdditionalMerchantRecipientService::class)->set($merchant->getKey(), 'email', ['primary@example.test', 'operations@example.test']);
        $adminId = $this->user('Admin', 'admin@example.test', '9000000002');
        $roleId = DB::table('auth_roles')->insertGetId(['slug' => 'admin', 'status' => 'active']);
        DB::table('auth_user_roles')->insert(['user_id' => $adminId, 'role_id' => $roleId]);

        $event = new StorefrontOrderPlaced($order, 'storefront-order-occurrence');
        app(DispatchBusinessNotifications::class)->storefrontOrderPlaced($event);
        app(DispatchBusinessNotifications::class)->storefrontOrderPlaced($event);

        $this->assertDatabaseHas('notification_delivery_logs', ['notification_key' => 'order.placed.customer', 'channel' => 'email', 'destination' => 'snapshot@example.test', 'status' => 'not_configured']);
        $this->assertDatabaseHas('notification_delivery_logs', ['notification_key' => 'order.new.merchant', 'channel' => 'email', 'destination' => 'primary@example.test']);
        $this->assertDatabaseHas('notification_delivery_logs', ['notification_key' => 'order.new.merchant', 'channel' => 'email', 'destination' => 'operations@example.test']);
        $this->assertSame(1, DB::table('notification_delivery_logs')->where('notification_key', 'order.new.merchant')->where('channel', 'email')->where('destination', 'primary@example.test')->count());
        $this->assertDatabaseHas('notification_delivery_logs', ['notification_key' => 'order.new.admin', 'channel' => 'email', 'destination' => 'admin@example.test', 'status' => 'skipped']);
    }

    public function test_storefront_order_event_is_not_wired_into_generic_pos_or_exchange_creation(): void
    {
        $this->assertStringContainsString('StorefrontOrderPlaced::dispatch', file_get_contents(base_path('app/Services/Checkout/StorefrontCheckoutOrderService.php')));

        foreach ([
            'app/Services/Order/OrderCreationService.php',
            'app/Services/Order/OrderExchangeService.php',
            'app/Http/Controllers/Merchant/PosController.php',
        ] as $path) {
            $this->assertStringNotContainsString('StorefrontOrderPlaced', file_get_contents(base_path($path)));
        }
    }

    public function test_every_supported_order_status_maps_to_its_catalogue_event_and_default_email_policy(): void
    {
        $order = $this->notificationOrder();
        $cases = [
            'confirmed' => ['order.confirmed.customer', 'not_configured'],
            'processing' => ['order.processing.customer', 'skipped'],
            'ready_for_pickup' => ['order.ready_for_pickup.customer', 'not_configured'],
            'packed' => ['order.packed.customer', 'skipped'],
            'ready_for_dispatch' => ['order.ready_for_dispatch.customer', 'skipped'],
            'shipped' => ['order.shipped.customer', 'not_configured'],
            'in_transit' => ['order.in_transit.customer', 'skipped'],
            'out_for_delivery' => ['order.out_for_delivery.customer', 'not_configured'],
            'delivered' => ['order.delivered.customer', 'not_configured'],
            'completed' => ['order.completed.customer', 'skipped'],
            'cancelled' => ['order.cancelled.customer', 'not_configured'],
        ];

        foreach ($cases as $status => [$key, $expected]) {
            app(DispatchBusinessNotifications::class)->orderStatusChanged(new OrderStatusChanged($order, 'previous', $status, "status-{$status}"));
            $this->assertDatabaseHas('notification_delivery_logs', [
                'notification_key' => $key,
                'channel' => 'email',
                'destination' => 'snapshot@example.test',
                'status' => $expected,
            ]);
        }
    }

    public function test_automatic_completed_after_delivered_is_suppressed_but_independent_completed_remains_configurable(): void
    {
        $order = $this->notificationOrder();
        $listener = app(DispatchBusinessNotifications::class);

        $listener->orderStatusChanged(new OrderStatusChanged($order, 'out_for_delivery', 'delivered', 'delivered-occurrence'));
        $listener->orderStatusChanged(new OrderStatusChanged($order, 'delivered', 'completed', 'automatic-completed-occurrence', ['automatic_after_delivered' => true]));

        $this->assertDatabaseHas('notification_delivery_logs', ['notification_key' => 'order.delivered.customer', 'channel' => 'email', 'status' => 'not_configured']);
        $this->assertDatabaseMissing('notification_delivery_logs', ['notification_key' => 'order.completed.customer']);

        $listener->orderStatusChanged(new OrderStatusChanged($order, 'ready_for_pickup', 'completed', 'manual-completed-default'));
        $this->assertDatabaseHas('notification_delivery_logs', ['notification_key' => 'order.completed.customer', 'channel' => 'email', 'status' => 'skipped']);

        app(ShopSettingsService::class)->set($order->shop_id, 'notifications', 'events.order.completed.customer.email.enabled', true);
        $listener->orderStatusChanged(new OrderStatusChanged($order, 'ready_for_pickup', 'completed', 'manual-completed-enabled'));
        $this->assertDatabaseHas('notification_delivery_logs', ['notification_key' => 'order.completed.customer', 'channel' => 'email', 'status' => 'not_configured']);
    }

    public function test_status_notification_keeps_order_snapshot_after_account_contact_changes(): void
    {
        $order = $this->notificationOrder();
        DB::table('users')->where('id', $order->customer_id)->update(['email' => 'changed@example.test', 'mobile' => '9999999999']);

        $event = new OrderStatusChanged($order, 'pending', 'confirmed', 'snapshot-status-occurrence');
        app(DispatchBusinessNotifications::class)->orderStatusChanged($event);
        app(DispatchBusinessNotifications::class)->orderStatusChanged($event);

        $this->assertDatabaseHas('notification_delivery_logs', ['notification_key' => 'order.confirmed.customer', 'channel' => 'email', 'destination' => 'snapshot@example.test']);
        $this->assertDatabaseMissing('notification_delivery_logs', ['notification_key' => 'order.confirmed.customer', 'destination' => 'changed@example.test']);
        $this->assertSame(1, DB::table('notification_delivery_logs')->where('notification_key', 'order.confirmed.customer')->where('channel', 'email')->count());
    }

    public function test_authoritative_transition_commits_after_commit_and_invalid_or_rolled_back_changes_do_not_notify(): void
    {
        $order = $this->notificationOrder();
        $service = new OrderStatusService(app(CouponRedemptionService::class));

        $service->transition($order, 'confirmed');
        $this->assertDatabaseHas('notification_delivery_logs', ['notification_key' => 'order.confirmed.customer', 'channel' => 'email']);

        $before = DB::table('notification_delivery_logs')->count();
        try {
            $service->transition($order->fresh(), 'confirmed');
        } catch (ValidationException) {
            // Expected invalid no-op transition.
        }
        $this->assertSame($before, DB::table('notification_delivery_logs')->count());

        $rollingBack = $this->notificationOrder();
        try {
            DB::transaction(function () use ($service, $rollingBack): void {
                $service->transition($rollingBack, 'confirmed');
                $this->assertDatabaseMissing('notification_delivery_logs', ['related_id' => $rollingBack->uuid, 'notification_key' => 'order.confirmed.customer']);
                throw new \RuntimeException('Force rollback');
            });
        } catch (\RuntimeException) {
            // Expected rollback.
        }

        $this->assertSame('pending', $rollingBack->fresh()->order_status);
        $this->assertDatabaseMissing('notification_delivery_logs', ['related_id' => $rollingBack->uuid, 'notification_key' => 'order.confirmed.customer']);
    }

    public function test_committed_cancellation_uses_the_mandatory_customer_notification(): void
    {
        $order = $this->notificationOrder();
        app(ShopSettingsService::class)->set($order->shop_id, 'notifications', 'events.order.cancelled.customer.email.enabled', false);

        $couponRedemptions = \Mockery::mock(CouponRedemptionService::class);
        $couponRedemptions->shouldReceive('cancelForOrder')->once()->with($order)->andReturn(0);

        (new OrderStatusService($couponRedemptions))->transition($order, 'cancelled');

        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertDatabaseHas('notification_delivery_logs', [
            'notification_key' => 'order.cancelled.customer',
            'channel' => 'email',
            'destination' => 'snapshot@example.test',
            'status' => 'not_configured',
        ]);
    }

    private function merchant(): MerchantProfile
    {
        $userId = $this->user('Primary Merchant', 'user@example.test', '9000000001');
        $id = DB::table('merchant_profiles')->insertGetId([
            'uuid' => (string) Str::uuid(), 'user_id' => $userId, 'business_name' => 'Merchant Business', 'contact_person_name' => 'Primary Merchant',
            'contact_email' => 'primary@example.test', 'contact_mobile' => '9876543210', 'verification_status' => 'pending', 'status' => 'active',
        ]);

        return MerchantProfile::query()->findOrFail($id);
    }

    private function notificationOrder(): Order
    {
        $merchant = $this->merchant();
        $shopId = DB::table('shops')->insertGetId(['uuid' => (string) Str::uuid(), 'merchant_id' => $merchant->getKey(), 'name' => 'Status Shop', 'status' => 'active']);
        $customerId = $this->user('Snapshot Customer', 'current@example.test', '9000000011');

        return Order::query()->create([
            'uuid' => (string) Str::uuid(), 'order_number' => 'ORD-STATUS', 'merchant_id' => $merchant->getKey(), 'shop_id' => $shopId,
            'customer_id' => $customerId, 'customer_name' => 'Snapshot Customer', 'customer_email' => 'snapshot@example.test', 'customer_mobile' => '9876543210',
            'fulfilment_type' => 'pickup', 'order_status' => 'pending',
        ]);
    }

    private function user(string $name, string $email, string $mobile): int
    {
        return DB::table('users')->insertGetId(['uuid' => (string) Str::uuid(), 'name' => $name, 'email' => $email, 'mobile' => $mobile, 'password' => Hash::make('password'), 'status' => 'active']);
    }

    private function merchantData(string $status, string $verification): array
    {
        return [
            'name' => 'Primary Merchant', 'email' => 'user@example.test', 'mobile' => '9000000001', 'business_name' => 'Merchant Business',
            'business_type' => 'retail', 'verification_status' => $verification, 'status' => $status, 'admin_note' => null,
        ];
    }
}
