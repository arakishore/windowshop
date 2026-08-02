<?php

namespace Tests\Feature;

use App\Models\MerchantCancellationReason;
use App\Models\MerchantProfile;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\MerchantCancellationReasonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MerchantCancellationReasonCrudTest extends TestCase
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

    public function test_seeder_creates_default_reasons_for_existing_merchants_and_is_idempotent(): void
    {
        $first = $this->fixture('first-cancel-seed@example.test');
        $second = $this->fixture('second-cancel-seed@example.test');

        app(MerchantCancellationReasonSeeder::class)->run();
        app(MerchantCancellationReasonSeeder::class)->run();

        $this->assertSame(10, MerchantCancellationReason::query()->where('merchant_id', $first['merchant']->getKey())->count());
        $this->assertSame(10, MerchantCancellationReason::query()->where('merchant_id', $second['merchant']->getKey())->count());

        $this->assertDatabaseHas('merchant_cancellation_reasons', [
            'merchant_id' => $first['merchant']->getKey(),
            'code' => 'customer_requested',
            'name' => 'Customer Requested Cancellation',
            'customer_selectable' => true,
            'merchant_selectable' => true,
            'requires_comment' => false,
            'status' => MerchantCancellationReason::STATUS_ACTIVE,
        ]);
        $this->assertSame(10, MerchantCancellationReason::query()->where('merchant_id', $first['merchant']->getKey())->distinct('code')->count('code'));
        $this->assertDatabaseHas('auth_permissions', ['slug' => 'merchant.cancellation-reasons.view']);
        $this->assertDatabaseHas('auth_role_permissions', [
            'role_id' => DB::table('auth_roles')->where('slug', 'merchant')->value('id'),
            'permission_id' => DB::table('auth_permissions')->where('slug', 'merchant.cancellation-reasons.restore')->value('id'),
        ]);
    }

    public function test_merchant_can_view_own_reasons_and_not_other_merchants_reasons(): void
    {
        $first = $this->fixture('first-cancel-view@example.test');
        $second = $this->fixture('second-cancel-view@example.test');
        $own = $this->reason($first['merchant'], ['code' => 'own_reason', 'name' => 'Own reason']);
        $other = $this->reason($second['merchant'], ['code' => 'other_reason', 'name' => 'Other merchant reason']);

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->get(route('merchant.cancellation-reasons.index'))
            ->assertOk()
            ->assertSee('Cancellation Reasons')
            ->assertSee('id="cancellation-reasons-table"', false)
            ->assertSee('Own reason')
            ->assertDontSee('Other merchant reason');

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->get(route('merchant.cancellation-reasons.edit', $other))
            ->assertNotFound();

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->put(route('merchant.cancellation-reasons.update', $other), $this->payload(['name' => 'Hacked']))
            ->assertNotFound();

        $this->assertSame('Own reason', $own->fresh()->name);
    }

    public function test_merchant_can_create_and_update_reason_with_read_only_code(): void
    {
        $fixture = $this->fixture('cancel-create@example.test');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.cancellation-reasons.store'), $this->payload([
                'code' => 'weather_issue',
                'name' => 'Weather Issue',
                'description' => 'Weather prevents fulfilment.',
                'customer_selectable' => '1',
                'merchant_selectable' => '1',
            ]))
            ->assertRedirect(route('merchant.cancellation-reasons.index'));

        $reason = MerchantCancellationReason::query()->where('merchant_id', $fixture['merchant']->getKey())->where('code', 'weather_issue')->firstOrFail();
        $this->assertSame('Weather Issue', $reason->name);
        $this->assertTrue($reason->customer_selectable);
        $this->assertSame($fixture['user']->getKey(), $reason->created_by);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->put(route('merchant.cancellation-reasons.update', $reason), $this->payload([
                'code' => 'attempted_code_change',
                'name' => 'Weather Delay',
                'status' => MerchantCancellationReason::STATUS_INACTIVE,
            ]))
            ->assertRedirect(route('merchant.cancellation-reasons.edit', $reason));

        $reason->refresh();
        $this->assertSame('weather_issue', $reason->code);
        $this->assertSame('Weather Delay', $reason->name);
        $this->assertSame(MerchantCancellationReason::STATUS_INACTIVE, $reason->status);
        $this->assertSame($fixture['user']->getKey(), $reason->updated_by);
    }

    public function test_duplicate_code_is_rejected_within_same_merchant_and_allowed_for_different_merchant(): void
    {
        $first = $this->fixture('first-cancel-dup@example.test');
        $second = $this->fixture('second-cancel-dup@example.test');
        $this->reason($first['merchant'], ['code' => 'duplicate_test', 'name' => 'Existing']);

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->post(route('merchant.cancellation-reasons.store'), $this->payload([
                'code' => 'duplicate_test',
                'name' => 'Duplicate',
            ]))
            ->assertSessionHasErrors('code');

        $this->actingAs($second['user'])
            ->withSession(['active_shop_id' => $second['shop']->getKey()])
            ->post(route('merchant.cancellation-reasons.store'), $this->payload([
                'code' => 'duplicate_test',
                'name' => 'Allowed Duplicate',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('merchant_cancellation_reasons', [
            'merchant_id' => $second['merchant']->getKey(),
            'code' => 'duplicate_test',
        ]);
    }

    public function test_invalid_code_other_comment_and_selectable_audience_rules_are_enforced(): void
    {
        $fixture = $this->fixture('cancel-validation@example.test');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.cancellation-reasons.store'), $this->payload([
                'code' => 'Invalid Code',
                'name' => 'Invalid',
            ]))
            ->assertSessionHasErrors('code');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.cancellation-reasons.store'), $this->payload([
                'code' => 'other',
                'name' => 'Other',
                'requires_comment' => null,
            ]))
            ->assertSessionHasErrors('requires_comment');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.cancellation-reasons.store'), $this->payload([
                'code' => 'no_audience',
                'name' => 'No Audience',
                'customer_selectable' => null,
                'merchant_selectable' => null,
            ]))
            ->assertSessionHasErrors('merchant_selectable');
    }

    public function test_status_filter_and_search(): void
    {
        $fixture = $this->fixture('cancel-filter@example.test');
        $this->reason($fixture['merchant'], ['code' => 'visible_active', 'name' => 'Visible Active', 'status' => MerchantCancellationReason::STATUS_ACTIVE]);
        $this->reason($fixture['merchant'], ['code' => 'hidden_inactive', 'name' => 'Hidden Inactive', 'status' => MerchantCancellationReason::STATUS_INACTIVE]);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->get(route('merchant.cancellation-reasons.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('Visible Active')
            ->assertDontSee('Hidden Inactive');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->get(route('merchant.cancellation-reasons.index', ['search' => 'hidden']))
            ->assertOk()
            ->assertSee('Hidden Inactive')
            ->assertDontSee('Visible Active');
    }

    public function test_soft_delete_trash_and_restore_are_scoped_to_merchant(): void
    {
        $first = $this->fixture('first-cancel-trash@example.test');
        $second = $this->fixture('second-cancel-trash@example.test');
        $own = $this->reason($first['merchant'], ['code' => 'own_delete', 'name' => 'Own Delete']);
        $other = $this->reason($second['merchant'], ['code' => 'other_delete', 'name' => 'Other Delete']);

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->delete(route('merchant.cancellation-reasons.destroy', $own))
            ->assertRedirect(route('merchant.cancellation-reasons.index'));

        $this->assertSoftDeleted('merchant_cancellation_reasons', ['id' => $own->getKey()]);

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->get(route('merchant.cancellation-reasons.index'))
            ->assertOk()
            ->assertDontSee('Own Delete');

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->get(route('merchant.cancellation-reasons.trash'))
            ->assertOk()
            ->assertSee('Own Delete')
            ->assertDontSee('Other Delete');

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->post(route('merchant.cancellation-reasons.restore', $other->getRouteKey()))
            ->assertNotFound();

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->post(route('merchant.cancellation-reasons.restore', $own->getRouteKey()))
            ->assertRedirect(route('merchant.cancellation-reasons.edit', $own->fresh()));

        $this->assertFalse($own->fresh()->trashed());
    }

    /**
     * @return array{user: User, merchant: MerchantProfile, shop: Shop}
     */
    private function fixture(string $email): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Cancellation Merchant',
            'email' => $email,
            'mobile' => '90000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $roleId = DB::table('auth_roles')->where('slug', 'merchant')->value('id')
            ?? DB::table('auth_roles')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Merchant',
                'slug' => 'merchant',
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

        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Cancellation Merchant '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        $root = ProductCategory::query()->create([
            'name' => 'Cancellation Root '.Str::random(4),
            'slug' => 'cancellation-root-'.Str::random(8),
            'status' => 'active',
        ]);

        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Cancellation Shop '.Str::random(4),
            'slug' => 'cancellation-shop-'.Str::random(8),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return compact('user', 'merchant', 'shop');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function reason(MerchantProfile $merchant, array $overrides = []): MerchantCancellationReason
    {
        return MerchantCancellationReason::query()->create([
            'merchant_id' => $merchant->getKey(),
            'code' => $overrides['code'] ?? 'reason_'.Str::random(6),
            'name' => $overrides['name'] ?? 'Cancellation Reason',
            'description' => $overrides['description'] ?? 'Cancellation description.',
            'internal_notes' => $overrides['internal_notes'] ?? null,
            'sort_order' => $overrides['sort_order'] ?? 99,
            'customer_selectable' => $overrides['customer_selectable'] ?? true,
            'merchant_selectable' => $overrides['merchant_selectable'] ?? true,
            'requires_comment' => $overrides['requires_comment'] ?? false,
            'status' => $overrides['status'] ?? MerchantCancellationReason::STATUS_ACTIVE,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'custom_reason',
            'name' => 'Custom Reason',
            'description' => 'Custom cancellation description.',
            'internal_notes' => 'Internal note.',
            'sort_order' => 100,
            'customer_selectable' => null,
            'merchant_selectable' => '1',
            'requires_comment' => null,
            'status' => MerchantCancellationReason::STATUS_ACTIVE,
        ], $overrides);
    }
}
