<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\OrderStatusSeeder;
use Database\Seeders\PaymentStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminPaymentStatusMasterTest extends TestCase
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

    public function test_seeder_creates_eight_system_statuses_idempotently_and_preserves_customisations(): void
    {
        $this->seed(PaymentStatusSeeder::class);

        $this->assertSame(8, PaymentStatus::query()->count());
        $this->assertDatabaseHas('payment_statuses', [
            'code' => PaymentStatus::CODE_PENDING,
            'name' => 'Pending',
            'category' => PaymentStatus::CATEGORY_AWAITING_PAYMENT,
            'category_description' => 'Waiting for payment.',
            'description' => 'Payment has not yet been received.',
            'is_terminal' => false,
            'is_system' => true,
            'merchant_visible' => true,
            'status' => PaymentStatus::STATUS_ACTIVE,
        ]);

        $pending = PaymentStatus::query()->where('code', PaymentStatus::CODE_PENDING)->firstOrFail();
        $pending->forceFill([
            'name' => 'Awaiting Money',
            'description' => 'Custom payment description stays.',
            'category_description' => 'Custom category description stays.',
            'badge_type' => PaymentStatus::BADGE_INFO,
            'sort_order' => 99,
            'merchant_visible' => false,
        ])->save();

        $this->seed(PaymentStatusSeeder::class);

        $this->assertSame(8, PaymentStatus::query()->count());
        $pending->refresh();
        $this->assertSame('Awaiting Money', $pending->name);
        $this->assertSame('Custom payment description stays.', $pending->description);
        $this->assertSame('Custom category description stays.', $pending->category_description);
        $this->assertSame(PaymentStatus::BADGE_INFO, $pending->badge_type);
        $this->assertSame(99, $pending->sort_order);
        $this->assertFalse($pending->merchant_visible);
    }

    public function test_admin_can_create_custom_status_with_generated_unique_immutable_code(): void
    {
        $admin = $this->createUserWithRole('super_admin');

        $this->actingAs($admin)
            ->post(route('admin.master.payment-statuses.store'), [
                'name' => 'Gateway Review',
                'category' => PaymentStatus::CATEGORY_DISPUTED,
                'description' => 'Gateway review is pending.',
                'category_description' => 'Gateway dispute category.',
                'badge_type' => PaymentStatus::BADGE_WARNING,
                'sort_order' => 90,
                'is_terminal' => '1',
                'merchant_visible' => '1',
                'status' => PaymentStatus::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.payment-statuses.index'));

        $status = PaymentStatus::query()->where('code', 'gateway_review')->firstOrFail();
        $this->assertFalse($status->is_system);
        $this->assertTrue($status->is_terminal);
        $this->assertSame('Gateway dispute category.', $status->category_description);
        $this->assertSame($admin->getKey(), $status->created_by);
        $this->assertSame($admin->getKey(), $status->updated_by);

        $this->actingAs($admin)
            ->post(route('admin.master.payment-statuses.store'), [
                'name' => 'Gateway Review',
                'category' => PaymentStatus::CATEGORY_DISPUTED,
                'badge_type' => PaymentStatus::BADGE_SECONDARY,
                'sort_order' => 91,
                'status' => PaymentStatus::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.payment-statuses.index'));

        $this->assertDatabaseHas('payment_statuses', ['code' => 'gateway_review_2']);

        $this->actingAs($admin)
            ->put(route('admin.master.payment-statuses.update', $status), [
                'code' => 'changed_code',
                'name' => 'Gateway Recheck',
                'category' => PaymentStatus::CATEGORY_FAILED,
                'description' => 'Changed payment description.',
                'category_description' => 'Changed category description.',
                'badge_type' => PaymentStatus::BADGE_INFO,
                'sort_order' => 92,
                'is_terminal' => '0',
                'merchant_visible' => '0',
                'status' => PaymentStatus::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('admin.master.payment-statuses.edit', $status));

        $status->refresh();
        $this->assertSame('gateway_review', $status->code);
        $this->assertSame('Gateway Recheck', $status->name);
        $this->assertSame(PaymentStatus::CATEGORY_FAILED, $status->category);
        $this->assertSame('Changed payment description.', $status->description);
        $this->assertFalse($status->is_terminal);
        $this->assertFalse($status->merchant_visible);
        $this->assertSame(PaymentStatus::STATUS_INACTIVE, $status->status);
    }

    public function test_system_payment_status_workflow_fields_are_protected_and_cannot_be_deleted(): void
    {
        $admin = $this->createUserWithRole('super_admin');
        $this->seed(PaymentStatusSeeder::class);
        $paid = PaymentStatus::query()->where('code', PaymentStatus::CODE_PAID)->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.master.payment-statuses.update', $paid), [
                'code' => 'settled',
                'name' => 'Settled',
                'category' => PaymentStatus::CATEGORY_FAILED,
                'description' => 'Presentation-only payment description.',
                'category_description' => 'Presentation-only category description.',
                'badge_type' => PaymentStatus::BADGE_PRIMARY,
                'sort_order' => 5,
                'is_terminal' => '0',
                'merchant_visible' => '0',
                'status' => PaymentStatus::STATUS_INACTIVE,
                'is_system' => '0',
            ])
            ->assertRedirect(route('admin.master.payment-statuses.edit', $paid));

        $paid->refresh();
        $this->assertSame(PaymentStatus::CODE_PAID, $paid->code);
        $this->assertSame(PaymentStatus::CATEGORY_PAID, $paid->category);
        $this->assertTrue($paid->is_system);
        $this->assertTrue($paid->is_terminal);
        $this->assertSame(PaymentStatus::STATUS_ACTIVE, $paid->status);
        $this->assertSame('Settled', $paid->name);
        $this->assertSame('Presentation-only payment description.', $paid->description);
        $this->assertFalse($paid->merchant_visible);

        $this->actingAs($admin)
            ->delete(route('admin.master.payment-statuses.destroy', $paid))
            ->assertSessionHasErrors('payment_status');

        $this->assertFalse($paid->fresh()->trashed());
    }

    public function test_system_description_is_required_but_custom_description_is_optional(): void
    {
        $admin = $this->createUserWithRole('super_admin');
        $this->seed(PaymentStatusSeeder::class);
        $paid = PaymentStatus::query()->where('code', PaymentStatus::CODE_PAID)->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.master.payment-statuses.edit', $paid))
            ->put(route('admin.master.payment-statuses.update', $paid), [
                'name' => 'Paid',
                'category' => PaymentStatus::CATEGORY_PAID,
                'description' => '',
                'badge_type' => PaymentStatus::BADGE_SUCCESS,
                'sort_order' => 30,
                'merchant_visible' => '1',
                'status' => PaymentStatus::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.payment-statuses.edit', $paid))
            ->assertSessionHasErrors('description');

        $this->actingAs($admin)
            ->post(route('admin.master.payment-statuses.store'), [
                'name' => 'Optional Payment Description',
                'category' => PaymentStatus::CATEGORY_AWAITING_PAYMENT,
                'badge_type' => PaymentStatus::BADGE_SECONDARY,
                'sort_order' => 95,
                'status' => PaymentStatus::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.payment-statuses.index'));

        $this->assertDatabaseHas('payment_statuses', [
            'code' => 'optional_payment_description',
            'description' => null,
            'is_system' => false,
        ]);
    }

    public function test_validation_rejects_invalid_category_and_badge_type(): void
    {
        $admin = $this->createUserWithRole('super_admin');

        $this->actingAs($admin)
            ->from(route('admin.master.payment-statuses.create'))
            ->post(route('admin.master.payment-statuses.store'), [
                'name' => 'Invalid Payment Status',
                'category' => 'unknown',
                'badge_type' => 'bg-success',
                'sort_order' => 1,
                'status' => PaymentStatus::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.payment-statuses.create'))
            ->assertSessionHasErrors(['category', 'badge_type']);
    }

    public function test_custom_unused_status_can_be_deleted_and_restored_with_audit_fields(): void
    {
        $admin = $this->createUserWithRole('super_admin');
        $status = $this->customStatus('Manual Gateway Check');

        $this->actingAs($admin)
            ->delete(route('admin.master.payment-statuses.destroy', $status))
            ->assertRedirect(route('admin.master.payment-statuses.index'));

        $trashed = PaymentStatus::withTrashed()->findOrFail($status->getKey());
        $this->assertTrue($trashed->trashed());
        $this->assertSame($admin->getKey(), $trashed->deleted_by);

        $this->actingAs($admin)
            ->post(route('admin.master.payment-statuses.restore', $trashed))
            ->assertRedirect(route('admin.master.payment-statuses.index', ['status' => 'trash']));

        $restored = $status->fresh();
        $this->assertFalse($restored->trashed());
        $this->assertNull($restored->deleted_by);
        $this->assertSame(PaymentStatus::STATUS_INACTIVE, $restored->status);
        $this->assertSame($admin->getKey(), $restored->updated_by);
    }

    public function test_custom_status_used_by_orders_cannot_be_deleted(): void
    {
        $admin = $this->createUserWithRole('super_admin');
        $used = $this->customStatus('Gateway Hold', 'gateway_hold');
        $this->orderWithStatuses(OrderStatus::CODE_CONFIRMED, $used->code);

        $this->actingAs($admin)
            ->delete(route('admin.master.payment-statuses.destroy', $used))
            ->assertSessionHasErrors('payment_status');
    }

    public function test_index_search_filters_sort_order_and_merchant_access_work(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->customStatus('Alpha Payment', 'alpha_payment', PaymentStatus::CATEGORY_AWAITING_PAYMENT, 30, PaymentStatus::STATUS_ACTIVE, false);
        $this->customStatus('Beta Dispute', 'beta_dispute', PaymentStatus::CATEGORY_DISPUTED, 10, PaymentStatus::STATUS_INACTIVE, true);
        $this->seed(PaymentStatusSeeder::class);

        $this->actingAs($admin)
            ->get(route('admin.master.payment-statuses.index', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Payment')
            ->assertDontSee('Beta Dispute');

        $this->actingAs($admin)
            ->get(route('admin.master.payment-statuses.index', ['category' => PaymentStatus::CATEGORY_DISPUTED]))
            ->assertOk()
            ->assertSee('Beta Dispute')
            ->assertSee('Chargeback');

        $this->actingAs($admin)
            ->get(route('admin.master.payment-statuses.index', ['status' => PaymentStatus::STATUS_INACTIVE]))
            ->assertOk()
            ->assertSee('Beta Dispute')
            ->assertDontSee('Alpha Payment');

        $this->actingAs($admin)
            ->get(route('admin.master.payment-statuses.index', ['type' => 'custom']))
            ->assertOk()
            ->assertSee('Beta Dispute')
            ->assertDontSee('Payment has not yet been received.');

        $ordered = PaymentStatus::query()->ordered()->pluck('code')->all();
        $this->assertLessThan(array_search('alpha_payment', $ordered, true), array_search('beta_dispute', $ordered, true));

        $merchant = $this->createUserWithRole('merchant');

        $this->actingAs($merchant)
            ->get(route('admin.master.payment-statuses.index'))
            ->assertForbidden();
    }

    public function test_payment_status_master_is_independent_from_order_status_master_and_runtime_strings(): void
    {
        $this->seed(OrderStatusSeeder::class);
        $this->seed(PaymentStatusSeeder::class);

        $this->assertSame(24, OrderStatus::query()->count());
        $this->assertSame(8, PaymentStatus::query()->count());

        $order = $this->orderWithStatuses(OrderStatus::CODE_CONFIRMED, PaymentStatus::CODE_PENDING);
        $this->assertSame(OrderStatus::CODE_CONFIRMED, $order->order_status);
        $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);

        $deliveredPaid = $this->orderWithStatuses(OrderStatus::CODE_DELIVERED, PaymentStatus::CODE_PAID);
        $cancelledRefunded = $this->orderWithStatuses(OrderStatus::CODE_CANCELLED, PaymentStatus::CODE_REFUNDED);

        $this->assertSame(OrderStatus::CODE_DELIVERED, $deliveredPaid->order_status);
        $this->assertSame(PaymentStatus::CODE_PAID, $deliveredPaid->payment_status);
        $this->assertSame(OrderStatus::CODE_CANCELLED, $cancelledRefunded->order_status);
        $this->assertSame(PaymentStatus::CODE_REFUNDED, $cancelledRefunded->payment_status);
    }

    private function customStatus(
        string $name,
        ?string $code = null,
        string $category = PaymentStatus::CATEGORY_AWAITING_PAYMENT,
        int $sortOrder = 90,
        string $status = PaymentStatus::STATUS_ACTIVE,
        bool $isTerminal = false,
    ): PaymentStatus {
        return PaymentStatus::query()->create([
            'code' => $code ?? Str::snake(Str::lower($name)),
            'name' => $name,
            'category' => $category,
            'description' => null,
            'category_description' => PaymentStatus::categoryDescriptions()[$category] ?? null,
            'badge_type' => PaymentStatus::BADGE_WARNING,
            'sort_order' => $sortOrder,
            'is_system' => false,
            'is_terminal' => $isTerminal,
            'merchant_visible' => true,
            'status' => $status,
        ]);
    }

    private function orderWithStatuses(string $orderStatus, string $paymentStatus): Order
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Payment Owner',
            'email' => 'payment-owner-'.Str::random(6).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Payment Status Merchant '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        $category = ProductCategory::query()->create([
            'name' => 'Payment Status Root '.Str::random(4),
            'slug' => 'payment-status-root-'.Str::random(8),
            'status' => 'active',
        ]);

        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $category->getKey(),
            'name' => 'Payment Status Shop '.Str::random(4),
            'slug' => 'payment-status-shop-'.Str::random(8),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return Order::query()->create([
            'order_number' => 'PAY-'.Str::upper(Str::random(10)),
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
            'currency_code' => 'INR',
        ]);
    }

    private function createUserWithRole(string $roleSlug): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($roleSlug).' User',
            'email' => $roleSlug.'-'.Str::random(8).'@example.test',
            'mobile' => '9'.random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $roleId = DB::table('auth_roles')->where('slug', $roleSlug)->value('id')
            ?? DB::table('auth_roles')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => Str::headline($roleSlug),
                'slug' => $roleSlug,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('auth_user_roles')->insert([
            'user_id' => $user->getKey(),
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
