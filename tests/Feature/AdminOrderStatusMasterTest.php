<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\OrderStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminOrderStatusMasterTest extends TestCase
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

    public function test_seeder_creates_system_statuses_idempotently_and_preserves_customised_presentation_fields(): void
    {
        $this->seed(OrderStatusSeeder::class);

        $this->assertSame(24, OrderStatus::query()->count());
        $this->assertDatabaseHas('order_statuses', [
            'code' => OrderStatus::CODE_PENDING,
            'name' => 'Pending',
            'customer_label' => 'Order Pending',
            'description' => 'Order has been created but has not yet been confirmed by the merchant.',
            'category' => OrderStatus::CATEGORY_OPEN,
            'badge_type' => OrderStatus::BADGE_SECONDARY,
            'sort_order' => 10,
            'is_terminal' => false,
            'is_system' => true,
            'customer_visible' => true,
            'merchant_visible' => true,
            'status' => OrderStatus::STATUS_ACTIVE,
        ]);

        $pending = OrderStatus::query()->where('code', OrderStatus::CODE_PENDING)->firstOrFail();
        $pending->forceFill([
            'name' => 'Awaiting Review',
            'customer_label' => 'We received your order',
            'description' => 'Custom description stays.',
            'internal_notes' => 'Custom admin note stays.',
            'badge_type' => OrderStatus::BADGE_INFO,
            'sort_order' => 99,
            'customer_visible' => false,
            'merchant_visible' => false,
        ])->save();

        $this->seed(OrderStatusSeeder::class);

        $this->assertSame(24, OrderStatus::query()->count());
        $pending->refresh();
        $this->assertSame('Awaiting Review', $pending->name);
        $this->assertSame('We received your order', $pending->customer_label);
        $this->assertSame('Custom description stays.', $pending->description);
        $this->assertSame('Custom admin note stays.', $pending->internal_notes);
        $this->assertSame(OrderStatus::BADGE_INFO, $pending->badge_type);
        $this->assertSame(99, $pending->sort_order);
        $this->assertFalse($pending->customer_visible);
        $this->assertFalse($pending->merchant_visible);
        $this->assertSame('Used by POS, reports and revenue calculations. Do not delete or rename the code.', OrderStatus::query()->where('code', OrderStatus::CODE_COMPLETED)->value('internal_notes'));
    }

    public function test_admin_can_create_custom_status_with_generated_unique_immutable_code_and_visibility_fields(): void
    {
        $admin = $this->createUserWithRole('super_admin');

        $this->actingAs($admin)
            ->post(route('admin.master.order-statuses.store'), [
                'name' => 'Quality Check',
                'customer_label' => 'Quality Check',
                'description' => 'Internal review before dispatch.',
                'internal_notes' => 'Admin-only implementation note.',
                'category' => OrderStatus::CATEGORY_PROCESSING,
                'badge_type' => OrderStatus::BADGE_WARNING,
                'sort_order' => 70,
                'is_terminal' => '1',
                'customer_visible' => '1',
                'merchant_visible' => '1',
                'status' => OrderStatus::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.order-statuses.index'));

        $status = OrderStatus::query()->where('code', 'quality_check')->firstOrFail();
        $this->assertFalse($status->is_system);
        $this->assertTrue($status->is_terminal);
        $this->assertTrue($status->customer_visible);
        $this->assertTrue($status->merchant_visible);
        $this->assertSame('Admin-only implementation note.', $status->internal_notes);
        $this->assertSame($admin->getKey(), $status->created_by);
        $this->assertSame($admin->getKey(), $status->updated_by);

        $this->actingAs($admin)
            ->post(route('admin.master.order-statuses.store'), [
                'name' => 'Quality Check',
                'category' => OrderStatus::CATEGORY_OPEN,
                'badge_type' => OrderStatus::BADGE_SECONDARY,
                'sort_order' => 71,
                'status' => OrderStatus::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.order-statuses.index'));

        $this->assertDatabaseHas('order_statuses', ['code' => 'quality_check_2']);

        $this->actingAs($admin)
            ->put(route('admin.master.order-statuses.update', $status), [
                'code' => 'changed_code',
                'name' => 'Quality Recheck',
                'customer_label' => 'Checking Order',
                'description' => 'Changed description.',
                'internal_notes' => 'Changed note.',
                'category' => OrderStatus::CATEGORY_OPEN,
                'badge_type' => OrderStatus::BADGE_INFO,
                'sort_order' => 80,
                'is_terminal' => '0',
                'customer_visible' => '0',
                'merchant_visible' => '1',
                'status' => OrderStatus::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('admin.master.order-statuses.edit', $status));

        $status->refresh();
        $this->assertSame('quality_check', $status->code);
        $this->assertSame('Quality Recheck', $status->name);
        $this->assertSame('Changed description.', $status->description);
        $this->assertSame('Changed note.', $status->internal_notes);
        $this->assertSame(OrderStatus::CATEGORY_OPEN, $status->category);
        $this->assertFalse($status->is_terminal);
        $this->assertFalse($status->customer_visible);
        $this->assertTrue($status->merchant_visible);
        $this->assertSame(OrderStatus::STATUS_INACTIVE, $status->status);
    }

    public function test_system_status_workflow_fields_are_protected_and_system_status_cannot_be_deleted(): void
    {
        $admin = $this->createUserWithRole('super_admin');
        $this->seed(OrderStatusSeeder::class);
        $completed = OrderStatus::query()->where('code', OrderStatus::CODE_COMPLETED)->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.master.order-statuses.update', $completed), [
                'code' => 'done',
                'name' => 'Done',
                'customer_label' => 'Done',
                'description' => 'Presentation-only change.',
                'internal_notes' => 'System implementation note.',
                'category' => OrderStatus::CATEGORY_FAILED,
                'badge_type' => OrderStatus::BADGE_PRIMARY,
                'sort_order' => 5,
                'is_terminal' => '0',
                'customer_visible' => '0',
                'merchant_visible' => '1',
                'status' => OrderStatus::STATUS_INACTIVE,
                'is_system' => '0',
            ])
            ->assertRedirect(route('admin.master.order-statuses.edit', $completed));

        $completed->refresh();
        $this->assertSame(OrderStatus::CODE_COMPLETED, $completed->code);
        $this->assertSame(OrderStatus::CATEGORY_FULFILLED, $completed->category);
        $this->assertTrue($completed->is_system);
        $this->assertTrue($completed->is_terminal);
        $this->assertSame(OrderStatus::STATUS_ACTIVE, $completed->status);
        $this->assertSame('Done', $completed->name);
        $this->assertSame('Presentation-only change.', $completed->description);
        $this->assertSame('System implementation note.', $completed->internal_notes);
        $this->assertSame(OrderStatus::BADGE_PRIMARY, $completed->badge_type);
        $this->assertSame(5, $completed->sort_order);
        $this->assertFalse($completed->customer_visible);

        $this->actingAs($admin)
            ->delete(route('admin.master.order-statuses.destroy', $completed))
            ->assertSessionHasErrors('order_status');

        $this->assertFalse($completed->fresh()->trashed());
    }

    public function test_system_status_description_is_required_but_custom_description_is_optional(): void
    {
        $admin = $this->createUserWithRole('super_admin');
        $this->seed(OrderStatusSeeder::class);
        $completed = OrderStatus::query()->where('code', OrderStatus::CODE_COMPLETED)->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.master.order-statuses.edit', $completed))
            ->put(route('admin.master.order-statuses.update', $completed), [
                'name' => 'Completed',
                'customer_label' => 'Order Completed',
                'description' => '',
                'category' => OrderStatus::CATEGORY_FULFILLED,
                'badge_type' => OrderStatus::BADGE_SUCCESS,
                'sort_order' => 90,
                'customer_visible' => '1',
                'merchant_visible' => '1',
                'status' => OrderStatus::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.order-statuses.edit', $completed))
            ->assertSessionHasErrors('description');

        $this->actingAs($admin)
            ->post(route('admin.master.order-statuses.store'), [
                'name' => 'Custom Optional Description',
                'category' => OrderStatus::CATEGORY_OPEN,
                'badge_type' => OrderStatus::BADGE_SECONDARY,
                'sort_order' => 250,
                'status' => OrderStatus::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.order-statuses.index'));

        $this->assertDatabaseHas('order_statuses', [
            'code' => 'custom_optional_description',
            'description' => null,
            'is_system' => false,
        ]);
    }

    public function test_validation_rejects_invalid_category_and_badge_type(): void
    {
        $admin = $this->createUserWithRole('super_admin');

        $this->actingAs($admin)
            ->from(route('admin.master.order-statuses.create'))
            ->post(route('admin.master.order-statuses.store'), [
                'name' => 'Invalid Status',
                'category' => 'made_up',
                'badge_type' => 'bg-success',
                'sort_order' => 1,
                'status' => OrderStatus::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.order-statuses.create'))
            ->assertSessionHasErrors(['category', 'badge_type']);
    }

    public function test_custom_unused_status_can_be_deleted_and_restored_with_audit_fields(): void
    {
        $admin = $this->createUserWithRole('super_admin');
        $status = $this->customStatus('Awaiting Customer Confirmation');

        $this->actingAs($admin)
            ->delete(route('admin.master.order-statuses.destroy', $status))
            ->assertRedirect(route('admin.master.order-statuses.index'));

        $trashed = OrderStatus::withTrashed()->findOrFail($status->getKey());
        $this->assertTrue($trashed->trashed());
        $this->assertSame($admin->getKey(), $trashed->deleted_by);

        $this->actingAs($admin)
            ->post(route('admin.master.order-statuses.restore', $trashed))
            ->assertRedirect(route('admin.master.order-statuses.index', ['status' => 'trash']));

        $restored = $status->fresh();
        $this->assertFalse($restored->trashed());
        $this->assertNull($restored->deleted_by);
        $this->assertSame(OrderStatus::STATUS_INACTIVE, $restored->status);
        $this->assertSame($admin->getKey(), $restored->updated_by);
    }

    public function test_custom_status_used_by_orders_or_history_cannot_be_deleted(): void
    {
        $admin = $this->createUserWithRole('super_admin');
        $usedByOrder = $this->customStatus('Manual Review', 'manual_review');
        $usedByHistory = $this->customStatus('Escalated', 'escalated');
        $order = $this->orderWithStatus($usedByOrder->code);

        DB::table('order_status_histories')->insert([
            'order_id' => $order->getKey(),
            'from_status' => OrderStatus::CODE_PENDING,
            'to_status' => $usedByHistory->code,
            'notes' => null,
            'changed_by' => null,
            'metadata' => null,
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.master.order-statuses.destroy', $usedByOrder))
            ->assertSessionHasErrors('order_status');

        $this->actingAs($admin)
            ->delete(route('admin.master.order-statuses.destroy', $usedByHistory))
            ->assertSessionHasErrors('order_status');
    }

    public function test_admin_index_search_filters_and_sort_order_work(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->customStatus('Alpha Review', 'alpha_review', OrderStatus::CATEGORY_OPEN, 30, OrderStatus::STATUS_ACTIVE, false);
        $this->customStatus('Beta Closed', 'beta_closed', OrderStatus::CATEGORY_CANCELLATION, 10, OrderStatus::STATUS_INACTIVE, false);
        $this->seed(OrderStatusSeeder::class);

        $this->actingAs($admin)
            ->get(route('admin.master.order-statuses.index', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Review')
            ->assertDontSee('Beta Closed');

        $this->actingAs($admin)
            ->get(route('admin.master.order-statuses.index', ['category' => OrderStatus::CATEGORY_CANCELLATION]))
            ->assertOk()
            ->assertSee('Beta Closed')
            ->assertSee('Cancelled');

        $this->actingAs($admin)
            ->get(route('admin.master.order-statuses.index', ['status' => OrderStatus::STATUS_INACTIVE]))
            ->assertOk()
            ->assertSee('Beta Closed')
            ->assertDontSee('Alpha Review');

        $this->actingAs($admin)
            ->get(route('admin.master.order-statuses.index', ['type' => 'custom']))
            ->assertOk()
            ->assertSee('Beta Closed')
            ->assertDontSee('Order Pending');

        $ordered = OrderStatus::query()->ordered()->pluck('code')->all();
        $this->assertLessThan(array_search('alpha_review', $ordered, true), array_search('beta_closed', $ordered, true));
    }

    public function test_guest_and_merchant_cannot_access_admin_order_status_routes(): void
    {
        $this->get(route('admin.master.order-statuses.index'))
            ->assertRedirect(route('admin.login'));

        $merchant = $this->createUserWithRole('merchant');

        $this->actingAs($merchant)
            ->get(route('admin.master.order-statuses.index'))
            ->assertForbidden();
    }

    private function customStatus(
        string $name,
        ?string $code = null,
        string $category = OrderStatus::CATEGORY_PROCESSING,
        int $sortOrder = 70,
        string $status = OrderStatus::STATUS_ACTIVE,
        bool $isTerminal = false,
    ): OrderStatus {
        return OrderStatus::query()->create([
            'code' => $code ?? Str::snake(Str::lower($name)),
            'name' => $name,
            'customer_label' => $name,
            'description' => null,
            'internal_notes' => null,
            'category' => $category,
            'badge_type' => OrderStatus::BADGE_WARNING,
            'sort_order' => $sortOrder,
            'is_system' => false,
            'is_terminal' => $isTerminal,
            'customer_visible' => true,
            'merchant_visible' => true,
            'status' => $status,
        ]);
    }

    private function orderWithStatus(string $status): Order
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Order Owner',
            'email' => 'order-owner-'.Str::random(6).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Order Status Merchant '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        $category = ProductCategory::query()->create([
            'name' => 'Order Status Root '.Str::random(4),
            'slug' => 'order-status-root-'.Str::random(8),
            'status' => 'active',
        ]);

        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $category->getKey(),
            'name' => 'Order Status Shop '.Str::random(4),
            'slug' => 'order-status-shop-'.Str::random(8),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return Order::query()->create([
            'order_number' => 'ORD-'.Str::upper(Str::random(10)),
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'order_status' => $status,
            'payment_status' => Order::PAYMENT_UNPAID,
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
