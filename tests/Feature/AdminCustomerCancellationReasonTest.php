<?php

namespace Tests\Feature;

use App\Models\CustomerCancellationReason;
use App\Models\MerchantCancellationReason;
use App\Models\MerchantProfile;
use App\Models\User;
use Database\Seeders\CustomerCancellationReasonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminCustomerCancellationReasonTest extends TestCase
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

    public function test_seeded_default_customer_cancellation_reasons_exist_and_are_idempotent(): void
    {
        app(CustomerCancellationReasonSeeder::class)->run();
        app(CustomerCancellationReasonSeeder::class)->run();

        $this->assertSame(6, CustomerCancellationReason::query()->count());
        $this->assertDatabaseHas('customer_cancellation_reasons', [
            'code' => 'ordered_by_mistake',
            'name' => 'Ordered by mistake',
            'requires_comment' => false,
            'status' => CustomerCancellationReason::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('customer_cancellation_reasons', [
            'code' => CustomerCancellationReason::CODE_OTHER,
            'name' => 'Other',
            'requires_comment' => true,
            'status' => CustomerCancellationReason::STATUS_ACTIVE,
        ]);
    }

    public function test_admin_can_create_update_filter_and_soft_delete_global_customer_cancellation_reasons(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.master.customer-cancellation-reasons.index'))
            ->assertOk()
            ->assertSee('Customer Cancellation Reasons')
            ->assertSee('Ordered by mistake');

        $this->actingAs($admin)
            ->post(route('admin.master.customer-cancellation-reasons.store'), [
                'code' => 'delivery_too_late',
                'name' => 'Delivery is too late',
                'requires_comment' => '1',
                'sort_order' => 5,
                'status' => CustomerCancellationReason::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.customer-cancellation-reasons.index'));

        $reason = CustomerCancellationReason::query()->where('code', 'delivery_too_late')->firstOrFail();
        $this->assertTrue($reason->requires_comment);
        $this->assertSame(5, $reason->sort_order);

        $this->actingAs($admin)
            ->get(route('admin.master.customer-cancellation-reasons.index', ['search' => 'late']))
            ->assertOk()
            ->assertSee('Delivery is too late')
            ->assertDontSee('Ordered by mistake');

        $this->actingAs($admin)
            ->put(route('admin.master.customer-cancellation-reasons.update', $reason), [
                'code' => 'delivery_too_late',
                'name' => 'Delivery timing does not work',
                'sort_order' => 15,
                'status' => CustomerCancellationReason::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('admin.master.customer-cancellation-reasons.edit', $reason));

        $reason->refresh();
        $this->assertSame('Delivery timing does not work', $reason->name);
        $this->assertFalse($reason->requires_comment);
        $this->assertSame(15, $reason->sort_order);
        $this->assertSame(CustomerCancellationReason::STATUS_INACTIVE, $reason->status);

        $this->actingAs($admin)
            ->delete(route('admin.master.customer-cancellation-reasons.destroy', $reason))
            ->assertRedirect(route('admin.master.customer-cancellation-reasons.index'));

        $this->assertSoftDeleted('customer_cancellation_reasons', ['id' => $reason->getKey()]);
    }

    public function test_guest_and_merchant_cannot_manage_global_customer_cancellation_reasons(): void
    {
        $this->get(route('admin.master.customer-cancellation-reasons.index'))
            ->assertRedirect(route('admin.login'));

        $merchant = $this->createUserWithRole('merchant');

        $this->actingAs($merchant)
            ->get(route('admin.master.customer-cancellation-reasons.index'))
            ->assertForbidden();
    }

    public function test_admin_customer_reason_changes_do_not_touch_merchant_cancellation_reasons(): void
    {
        $admin = $this->createUserWithRole('super_admin');
        $merchantUser = $this->createUserWithRole('merchant');
        $merchant = MerchantProfile::query()->create([
            'user_id' => $merchantUser->getKey(),
            'business_name' => 'Customer Reason Isolation Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $merchantReason = MerchantCancellationReason::query()->create([
            'merchant_id' => $merchant->getKey(),
            'code' => 'ordered_by_mistake',
            'name' => 'Merchant custom ordered by mistake',
            'sort_order' => 1,
            'customer_selectable' => true,
            'merchant_selectable' => true,
            'requires_comment' => false,
            'status' => MerchantCancellationReason::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.master.customer-cancellation-reasons.store'), [
                'code' => 'merchant_separate_check',
                'name' => 'Merchant table stays separate',
                'sort_order' => 1,
                'status' => CustomerCancellationReason::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.customer-cancellation-reasons.index'));

        $this->assertDatabaseHas('merchant_cancellation_reasons', [
            'id' => $merchantReason->getKey(),
            'name' => 'Merchant custom ordered by mistake',
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
