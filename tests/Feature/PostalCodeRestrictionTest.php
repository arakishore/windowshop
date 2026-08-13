<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\PostalCode;
use App\Models\PostalCodeRestriction;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use App\Services\PostalCodeServiceabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class PostalCodeRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_serviceability_respects_global_shop_scope_dates_status_and_priority(): void
    {
        $fixture = $this->merchantFixture('Alpha');
        $otherShop = $this->shop($fixture['merchant'], 'Alpha Other');
        $otherFixture = $this->merchantFixture('Beta');
        $service = app(PostalCodeServiceabilityService::class);

        $this->postalCode('422009');
        $this->postalCode('422010');
        $this->postalCode('422011');
        $this->postalCode('422012');

        PostalCodeRestriction::query()->create([
            'postal_code' => '422009',
            'reason' => 'Flood',
            'status' => PostalCodeRestriction::STATUS_ACTIVE,
        ]);
        PostalCodeRestriction::query()->create([
            'postal_code' => '422009',
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'reason' => 'Shop issue',
            'status' => PostalCodeRestriction::STATUS_ACTIVE,
        ]);

        $global = $service->check('422009', $fixture['merchant']->getKey(), $fixture['shop']->getKey());
        $this->assertFalse($global['serviceable']);
        $this->assertSame('global', $global['scope']);
        $this->assertSame('Flood', $global['reason']);

        PostalCodeRestriction::query()->where('postal_code', '422009')->whereNull('merchant_id')->delete();
        $shopBlocked = $service->check('422009', $fixture['merchant']->getKey(), $fixture['shop']->getKey());
        $this->assertFalse($shopBlocked['serviceable']);
        $this->assertSame('shop', $shopBlocked['scope']);

        $differentShop = $service->check('422009', $fixture['merchant']->getKey(), $otherShop->getKey());
        $this->assertTrue($differentShop['serviceable']);

        $differentMerchant = $service->check('422009', $otherFixture['merchant']->getKey(), $otherFixture['shop']->getKey());
        $this->assertTrue($differentMerchant['serviceable']);

        PostalCodeRestriction::query()->create([
            'postal_code' => '422010',
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'starts_at' => now()->addDay(),
            'status' => PostalCodeRestriction::STATUS_ACTIVE,
        ]);
        $this->assertTrue($service->check('422010', $fixture['merchant']->getKey(), $fixture['shop']->getKey())['serviceable']);

        PostalCodeRestriction::query()->create([
            'postal_code' => '422011',
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'ends_at' => now()->subMinute(),
            'status' => PostalCodeRestriction::STATUS_ACTIVE,
        ]);
        $this->assertTrue($service->check('422011', $fixture['merchant']->getKey(), $fixture['shop']->getKey())['serviceable']);

        PostalCodeRestriction::query()->create([
            'postal_code' => '422012',
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'status' => PostalCodeRestriction::STATUS_INACTIVE,
        ]);
        $this->assertTrue($service->check('422012', $fixture['merchant']->getKey(), $fixture['shop']->getKey())['serviceable']);
    }

    public function test_admin_creates_global_restriction_and_rejects_invalid_or_duplicate_pin(): void
    {
        $admin = $this->userWithRole('super_admin');
        $this->postalCode('422009');

        $this->actingAs($admin)
            ->post(route('admin.master.postal-code-restrictions.store'), [
                'postal_code' => '422009',
                'reason' => 'Flood',
                'starts_at' => null,
                'ends_at' => null,
                'status' => PostalCodeRestriction::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.postal-code-restrictions.index'));

        $this->assertDatabaseHas('postal_code_restrictions', [
            'postal_code' => '422009',
            'merchant_id' => null,
            'shop_id' => null,
            'reason' => 'Flood',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.master.postal-code-restrictions.store'), [
                'postal_code' => '999999',
                'status' => PostalCodeRestriction::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('postal_code');

        $this->actingAs($admin)
            ->post(route('admin.master.postal-code-restrictions.store'), [
                'postal_code' => '422009',
                'status' => PostalCodeRestriction::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('postal_code');

        $restriction = PostalCodeRestriction::query()->firstOrFail();
        $this->actingAs($admin)
            ->patch(route('admin.master.postal-code-restrictions.toggle-status', $restriction))
            ->assertRedirect(route('admin.master.postal-code-restrictions.index'));
        $this->assertSame(PostalCodeRestriction::STATUS_INACTIVE, $restriction->fresh()->status);

        $this->actingAs($admin)
            ->delete(route('admin.master.postal-code-restrictions.destroy', $restriction))
            ->assertRedirect(route('admin.master.postal-code-restrictions.index'));
        $this->assertSoftDeleted('postal_code_restrictions', ['id' => $restriction->getKey()]);

        $this->actingAs($admin)
            ->post(route('admin.master.postal-code-restrictions.restore', $restriction))
            ->assertRedirect(route('admin.master.postal-code-restrictions.index', ['status' => 'trash']));
        $this->assertFalse($restriction->fresh()->trashed());
    }

    public function test_merchant_restrictions_are_for_active_shop_and_cannot_access_other_scopes(): void
    {
        $fixture = $this->merchantFixture('Alpha');
        $otherShop = $this->shop($fixture['merchant'], 'Alpha Other');
        $otherFixture = $this->merchantFixture('Beta');
        $this->postalCode('422009');
        $this->postalCode('422010');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.postal-code-restrictions.store'), [
                'postal_code' => '422009',
                'reason' => 'Road work',
                'starts_at' => null,
                'ends_at' => null,
                'status' => PostalCodeRestriction::STATUS_ACTIVE,
                'merchant_id' => $otherFixture['merchant']->getKey(),
                'shop_id' => $otherFixture['shop']->getKey(),
            ])
            ->assertRedirect(route('merchant.postal-code-restrictions.index'));

        $restriction = PostalCodeRestriction::query()->where('postal_code', '422009')->firstOrFail();
        $this->assertSame($fixture['merchant']->getKey(), (int) $restriction->merchant_id);
        $this->assertSame($fixture['shop']->getKey(), (int) $restriction->shop_id);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.postal-code-restrictions.store'), [
                'postal_code' => '422009',
                'status' => PostalCodeRestriction::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('postal_code');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $otherShop->getKey()])
            ->get(route('merchant.postal-code-restrictions.edit', $restriction))
            ->assertNotFound();

        $global = PostalCodeRestriction::query()->create([
            'postal_code' => '422010',
            'reason' => 'Marketplace restriction',
            'status' => PostalCodeRestriction::STATUS_ACTIVE,
        ]);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->get(route('merchant.postal-code-restrictions.edit', $global))
            ->assertNotFound();

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->delete(route('merchant.postal-code-restrictions.destroy', $global))
            ->assertNotFound();

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->get(route('merchant.postal-code-restrictions.index'))
            ->assertOk()
            ->assertSee('Road work')
            ->assertDontSee('Marketplace restriction');
    }

    private function postalCode(string $postalCode): PostalCode
    {
        return PostalCode::query()->create([
            'source_key' => sha1($postalCode.'|cidco colony b.o|bo|nashik|maharashtra'),
            'circle_name' => 'Maharashtra Circle',
            'region_name' => 'Mumbai Region',
            'division_name' => 'Nashik Division',
            'office_name' => 'Cidco Colony B.O',
            'postal_code' => $postalCode,
            'office_type' => 'BO',
            'delivery_status' => 'Delivery',
            'shipping_enabled' => true,
            'district' => 'NASHIK',
            'state' => 'MAHARASHTRA',
            'status' => PostalCode::STATUS_ACTIVE,
        ]);
    }

    /**
     * @return array{user: User, merchant: MerchantProfile, shop: Shop}
     */
    private function merchantFixture(string $name): array
    {
        $user = $this->userWithRole('merchant');
        $merchant = MerchantProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'business_name' => "{$name} Business",
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        return [
            'user' => $user,
            'merchant' => $merchant,
            'shop' => $this->shop($merchant, "{$name} Shop"),
        ];
    }

    private function shop(MerchantProfile $merchant, string $name): Shop
    {
        $category = ProductCategory::query()->first() ?? ProductCategory::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Apparel',
            'slug' => 'apparel',
            'status' => 'active',
        ]);

        return Shop::query()->create([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $category->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $roleSlug): User
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
