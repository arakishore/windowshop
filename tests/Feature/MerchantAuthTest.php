<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MerchantAuthTest extends TestCase
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

    public function test_active_merchant_can_login_with_email(): void
    {
        $userId = $this->createMerchantUser(email: 'merchant@example.test');

        $response = $this->post('/merchant/login', [
            'login' => 'merchant@example.test',
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('merchant.dashboard'));
        $this->assertAuthenticatedAsUserId($userId);
        $this->assertDatabaseHas('auth_user_sessions', [
            'user_id' => $userId,
            'guard_name' => 'merchant_web',
            'is_current' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('auth_user_login_history', [
            'user_id' => $userId,
            'guard_name' => 'merchant_web',
            'status' => 'success',
        ]);
    }

    public function test_active_merchant_can_login_with_mobile(): void
    {
        $userId = $this->createMerchantUser(mobile: '9876543210');

        $response = $this->post('/merchant/login', [
            'login' => '9876543210',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('merchant.dashboard'));
        $this->assertAuthenticatedAsUserId($userId);
    }

    public function test_authenticated_merchant_can_view_dashboard(): void
    {
        $userId = $this->createMerchantUser(email: 'dashboard@example.test');
        $this->createShopForMerchantUser($userId, 'Vana Clothing', 'Nashik');

        $this->actingAs(\App\Models\User::findOrFail($userId));

        $response = $this->get('/merchant/dashboard');

        $response->assertOk();
        $response->assertSee('Merchant Dashboard');
        $response->assertSee('Active Shop');
        $response->assertSee('Vana Clothing - Nashik');
        $response->assertDontSee('All Shops');
        $response->assertSee('Latest Orders');
    }

    public function test_merchant_can_switch_active_shop(): void
    {
        $userId = $this->createMerchantUser(email: 'switch-shop@example.test');
        $firstShopId = $this->createShopForMerchantUser($userId, 'Vana Clothing', 'Nashik');
        $secondShopId = $this->createShopForMerchantUser($userId, 'Urban Fits', 'Pune');

        $this
            ->actingAs(\App\Models\User::findOrFail($userId))
            ->withSession(['active_shop_id' => $firstShopId])
            ->post('/merchant/active-shop', [
                'shop_id' => $secondShopId,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Now managing "Urban Fits - Pune".');

        $merchantId = (int) DB::table('merchant_profiles')->where('user_id', $userId)->value('id');
        $roleId = (int) DB::table('auth_roles')->where('slug', 'merchant')->value('id');

        $this->assertSame($merchantId, session('merchant_id'));
        $this->assertSame($roleId, session('active_role_id'));
        $this->assertSame($secondShopId, session('active_shop_id'));
        $this->assertSame('Urban Fits - Pune', session('active_shop_name'));
    }

    public function test_merchant_sees_only_own_shops(): void
    {
        $userId = $this->createMerchantUser(email: 'own-shops@example.test');
        $otherUserId = $this->createMerchantUser(email: 'other-shops@example.test', mobile: '9000000301');
        $this->createShopForMerchantUser($userId, 'Owner Studio', 'Nashik');
        $this->createShopForMerchantUser($otherUserId, 'Hidden Studio', 'Pune');

        $response = $this->actingAs(\App\Models\User::findOrFail($userId))->get('/merchant/shops');

        $response->assertOk();
        $response->assertSee('Owner Studio');
        $response->assertDontSee('Hidden Studio');
        $response->assertDontSee('Delete');
    }

    public function test_merchant_cannot_open_or_update_another_merchants_shop(): void
    {
        $userId = $this->createMerchantUser(email: 'shop-owner@example.test');
        $otherUserId = $this->createMerchantUser(email: 'shop-other@example.test', mobile: '9000000302');
        $otherShopId = $this->createShopForMerchantUser($otherUserId, 'Other Merchant Shop', 'Pune');
        $otherShopUuid = $this->shopUuid($otherShopId);

        $this->actingAs(\App\Models\User::findOrFail($userId));

        $this->get("/merchant/shops/{$otherShopUuid}")->assertNotFound();
        $this->put("/merchant/shops/{$otherShopUuid}", [
            'name' => 'Should Not Update',
            'address_line_1' => 'Blocked Road',
        ])->assertNotFound();
    }

    public function test_merchant_can_activate_eligible_shop(): void
    {
        $userId = $this->createMerchantUser(email: 'activate-shop@example.test');
        $shopId = $this->createShopForMerchantUser($userId, 'Active Candidate', 'Nashik');
        $shopUuid = $this->shopUuid($shopId);

        $this->actingAs(\App\Models\User::findOrFail($userId))
            ->post("/merchant/shops/{$shopUuid}/activate")
            ->assertRedirect()
            ->assertSessionHas('success', 'Now managing "Active Candidate - Nashik".');

        $this->assertSame($shopId, session('active_shop_id'));
        $this->assertSame('Active Candidate - Nashik', session('active_shop_name'));
    }

    public function test_inactive_shop_cannot_be_activated(): void
    {
        $userId = $this->createMerchantUser(email: 'inactive-shop@example.test');
        $shopId = $this->createShopForMerchantUser($userId, 'Inactive Candidate', 'Nashik', 'inactive');
        $shopUuid = $this->shopUuid($shopId);

        $this->actingAs(\App\Models\User::findOrFail($userId))
            ->post("/merchant/shops/{$shopUuid}/activate")
            ->assertStatus(422);

        $this->assertNull(session('active_shop_id'));
    }

    public function test_active_shop_name_updates_when_active_shop_name_changes(): void
    {
        $userId = $this->createMerchantUser(email: 'rename-shop@example.test');
        $shopId = $this->createShopForMerchantUser($userId, 'Old Shop Name', 'Nashik');
        $shopUuid = $this->shopUuid($shopId);
        $shop = DB::table('shops')->where('id', $shopId)->first();

        $this->actingAs(\App\Models\User::findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId, 'active_shop_name' => 'Old Shop Name - Nashik'])
            ->put("/merchant/shops/{$shopUuid}", [
                'name' => 'New Shop Name',
                'short_description' => 'Updated short text',
                'email' => ' SHOP@EXAMPLE.TEST ',
                'mobile' => ' 9000000999 ',
                'address_line_1' => 'Updated Road',
                'country_id' => $shop->country_id,
                'state_id' => $shop->state_id,
                'city_id' => $shop->city_id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Shop updated successfully.');

        $this->assertDatabaseHas('shops', [
            'id' => $shopId,
            'name' => 'New Shop Name',
            'slug' => 'new-shop-name',
            'email' => 'shop@example.test',
            'mobile' => '9000000999',
            'address_line_1' => 'Updated Road',
            'updated_by' => $userId,
        ]);
        $this->assertSame('New Shop Name - Nashik', session('active_shop_name'));
    }

    public function test_deactivating_current_shop_selects_another_active_shop(): void
    {
        $userId = $this->createMerchantUser(email: 'deactivate-current@example.test');
        $currentShopId = $this->createShopForMerchantUser($userId, 'Beta Shop', 'Nashik');
        $nextShopId = $this->createShopForMerchantUser($userId, 'Alpha Shop', 'Pune');
        $currentShopUuid = $this->shopUuid($currentShopId);
        $shop = DB::table('shops')->where('id', $currentShopId)->first();

        $this->actingAs(\App\Models\User::findOrFail($userId))
            ->withSession(['active_shop_id' => $currentShopId, 'active_shop_name' => 'Beta Shop - Nashik'])
            ->put("/merchant/shops/{$currentShopUuid}", [
                'name' => 'Beta Shop',
                'address_line_1' => 'Main Road',
                'country_id' => $shop->country_id,
                'state_id' => $shop->state_id,
                'city_id' => $shop->city_id,
                'status' => 'inactive',
            ])
            ->assertRedirect("/merchant/shops/{$currentShopUuid}/edit")
            ->assertSessionHas('success', 'Shop updated successfully. Now managing "Alpha Shop - Pune".');

        $this->assertDatabaseHas('shops', [
            'id' => $currentShopId,
            'status' => 'inactive',
        ]);
        $this->assertSame($nextShopId, session('active_shop_id'));
        $this->assertSame('Alpha Shop - Pune', session('active_shop_name'));
    }

    public function test_deactivating_only_current_shop_clears_active_shop_session(): void
    {
        $userId = $this->createMerchantUser(email: 'deactivate-only@example.test');
        $shopId = $this->createShopForMerchantUser($userId, 'Only Shop', 'Nashik');
        $shopUuid = $this->shopUuid($shopId);
        $shop = DB::table('shops')->where('id', $shopId)->first();

        $this->actingAs(\App\Models\User::findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId, 'active_shop_name' => 'Only Shop - Nashik'])
            ->put("/merchant/shops/{$shopUuid}", [
                'name' => 'Only Shop',
                'address_line_1' => 'Main Road',
                'country_id' => $shop->country_id,
                'state_id' => $shop->state_id,
                'city_id' => $shop->city_id,
                'status' => 'inactive',
            ])
            ->assertRedirect('/merchant/shops')
            ->assertSessionHas('warning', 'This shop is now inactive. No other active shop is available.');

        $this->assertDatabaseHas('shops', [
            'id' => $shopId,
            'status' => 'inactive',
        ]);
        $this->assertNull(session('active_shop_id'));
        $this->assertNull(session('active_shop_name'));
    }

    public function test_merchant_cannot_change_protected_shop_status(): void
    {
        $userId = $this->createMerchantUser(email: 'protected-status@example.test');
        $shopId = $this->createShopForMerchantUser($userId, 'Protected Shop', 'Nashik', 'suspended');
        $shopUuid = $this->shopUuid($shopId);
        $shop = DB::table('shops')->where('id', $shopId)->first();

        $this->actingAs(\App\Models\User::findOrFail($userId))
            ->from("/merchant/shops/{$shopUuid}/edit")
            ->put("/merchant/shops/{$shopUuid}", [
                'name' => 'Protected Shop',
                'address_line_1' => 'Main Road',
                'country_id' => $shop->country_id,
                'state_id' => $shop->state_id,
                'city_id' => $shop->city_id,
                'status' => 'active',
            ])
            ->assertRedirect("/merchant/shops/{$shopUuid}/edit")
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('shops', [
            'id' => $shopId,
            'status' => 'suspended',
        ]);
    }

    public function test_shop_update_validation_errors_display(): void
    {
        $userId = $this->createMerchantUser(email: 'shop-validation@example.test');
        $shopId = $this->createShopForMerchantUser($userId, 'Validation Shop', 'Nashik');
        $shopUuid = $this->shopUuid($shopId);

        $this->actingAs(\App\Models\User::findOrFail($userId))
            ->from("/merchant/shops/{$shopUuid}/edit")
            ->put("/merchant/shops/{$shopUuid}", [
                'name' => '',
                'address_line_1' => '',
            ])
            ->assertRedirect("/merchant/shops/{$shopUuid}/edit")
            ->assertSessionHasErrors(['name', 'address_line_1']);
    }

    public function test_authenticated_merchant_can_update_profile(): void
    {
        $userId = $this->createMerchantUser(email: 'profile@example.test', mobile: '9000000001');
        $this->actingAs(\App\Models\User::findOrFail($userId));

        $this->put('/merchant/profile', [
            'name' => 'Updated Merchant',
            'email' => 'updated-profile@example.test',
            'mobile' => '9000000002',
        ])->assertRedirect()
            ->assertSessionHas('success', 'Profile updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'name' => 'Updated Merchant',
            'email' => 'updated-profile@example.test',
            'mobile' => '9000000002',
        ]);
        $this->assertDatabaseHas('merchant_profiles', [
            'user_id' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function test_profile_update_enforces_unique_email_and_mobile(): void
    {
        $this->createUser(email: 'taken@example.test', mobile: '9000000010');
        $userId = $this->createMerchantUser(email: 'unique@example.test', mobile: '9000000011');
        $this->actingAs(\App\Models\User::findOrFail($userId));

        $this->from('/merchant/profile')->put('/merchant/profile', [
            'name' => 'Unique Merchant',
            'email' => 'taken@example.test',
            'mobile' => '9000000010',
        ])->assertRedirect('/merchant/profile')
            ->assertSessionHasErrors(['email', 'mobile']);
    }

    public function test_merchant_can_update_unverified_merchant_details(): void
    {
        $userId = $this->createMerchantUser(email: 'details@example.test', verificationStatus: 'pending');
        $this->actingAs(\App\Models\User::findOrFail($userId));

        $this->put('/merchant/details', [
            'business_name' => 'Updated Business',
            'legal_name' => 'Updated Business LLP',
            'business_type' => 'llp',
            'contact_person_name' => 'Details Owner',
            'contact_email' => 'CONTACT@EXAMPLE.TEST',
            'contact_mobile' => ' 9000000101 ',
            'gst_number' => '27ABCDE1234F1Z5',
            'has_shop_license' => '1',
            'has_fssai' => '0',
        ])->assertRedirect()
            ->assertSessionHas('success', 'Merchant details updated successfully.');

        $this->assertDatabaseHas('merchant_profiles', [
            'user_id' => $userId,
            'business_name' => 'Updated Business',
            'legal_name' => 'Updated Business LLP',
            'business_type' => 'llp',
            'contact_person_name' => 'Details Owner',
            'contact_email' => 'contact@example.test',
            'contact_mobile' => '9000000101',
            'gst_number' => '27ABCDE1234F1Z5',
            'has_shop_license' => true,
            'has_fssai' => false,
            'updated_by' => $userId,
        ]);
    }

    public function test_merchant_details_reject_invalid_gst(): void
    {
        $userId = $this->createMerchantUser(email: 'invalid-gst@example.test', verificationStatus: 'pending');
        $this->actingAs(\App\Models\User::findOrFail($userId));

        $this->from('/merchant/details')->put('/merchant/details', [
            'business_name' => 'GST Test',
            'gst_number' => 'BAD-GST',
        ])->assertRedirect('/merchant/details')
            ->assertSessionHasErrors('gst_number');
    }

    public function test_verified_merchant_cannot_edit_locked_details(): void
    {
        $userId = $this->createMerchantUser(email: 'verified-details@example.test', verificationStatus: 'approved');
        $this->actingAs(\App\Models\User::findOrFail($userId));

        $this->from('/merchant/details')->put('/merchant/details', [
            'business_name' => 'Allowed Business Name',
            'legal_name' => 'Blocked Legal Name',
            'business_type' => 'llp',
            'gst_number' => '27ABCDE1234F1Z5',
            'has_shop_license' => '1',
            'has_fssai' => '1',
        ])->assertRedirect('/merchant/details')
            ->assertSessionHasErrors(['legal_name', 'business_type', 'gst_number', 'has_shop_license', 'has_fssai']);
    }

    public function test_non_merchant_cannot_access_merchant_details(): void
    {
        $userId = $this->createUser(email: 'not-merchant-details@example.test');

        $this->actingAs(\App\Models\User::findOrFail($userId));

        $this->get('/merchant/details')->assertForbidden();
        $this->put('/merchant/details', [
            'business_name' => 'Nope',
        ])->assertForbidden();
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $userId = $this->createMerchantUser(email: 'wrong-password@example.test');
        $this->actingAs(\App\Models\User::findOrFail($userId));

        $this->from('/merchant/change-password')->put('/merchant/change-password', [
            'current_password' => 'incorrect',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect('/merchant/change-password')
            ->assertSessionHasErrors('current_password');
    }

    public function test_merchant_can_change_password(): void
    {
        $userId = $this->createMerchantUser(email: 'change-password@example.test');
        $user = \App\Models\User::findOrFail($userId);
        $this->actingAs($user);

        $this->put('/merchant/change-password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect()
            ->assertSessionHas('success', 'Password changed successfully.');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertAuthenticatedAsUserId($userId);
    }

    public function test_non_merchant_cannot_access_profile_pages(): void
    {
        $userId = $this->createUser(email: 'not-merchant-profile@example.test');

        $this->actingAs(\App\Models\User::findOrFail($userId));

        $this->get('/merchant/profile')->assertForbidden();
        $this->get('/merchant/change-password')->assertForbidden();
    }

    public function test_inactive_user_cannot_login_as_merchant(): void
    {
        $this->createMerchantUser(email: 'inactive@example.test', userStatus: 'inactive');

        $response = $this->from('/merchant/login')->post('/merchant/login', [
            'login' => 'inactive@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('merchant.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertDatabaseHas('auth_user_login_history', [
            'email' => 'inactive@example.test',
            'guard_name' => 'merchant_web',
            'status' => 'failed',
            'failure_reason' => 'inactive_user',
        ]);
    }

    public function test_suspended_merchant_profile_cannot_login(): void
    {
        $this->createMerchantUser(email: 'suspended@example.test', merchantStatus: 'suspended');

        $response = $this->from('/merchant/login')->post('/merchant/login', [
            'login' => 'suspended@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('merchant.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertDatabaseHas('auth_user_login_history', [
            'email' => 'suspended@example.test',
            'guard_name' => 'merchant_web',
            'status' => 'blocked',
            'failure_reason' => 'suspended_merchant',
        ]);
    }

    public function test_user_without_merchant_role_cannot_login(): void
    {
        $this->createUser(email: 'customer@example.test');

        $response = $this->from('/merchant/login')->post('/merchant/login', [
            'login' => 'customer@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('merchant.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertDatabaseHas('auth_user_login_history', [
            'email' => 'customer@example.test',
            'guard_name' => 'merchant_web',
            'status' => 'blocked',
            'failure_reason' => 'merchant_role_required',
        ]);
    }

    private function createMerchantUser(
        string $email = 'merchant@example.test',
        string $mobile = '9000000000',
        string $userStatus = 'active',
        string $merchantStatus = 'active',
        string $verificationStatus = 'approved',
    ): int {
        $userId = $this->createUser($email, $mobile, $userStatus);
        $roleId = $this->createRole('Merchant', 'merchant');

        DB::table('auth_user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('merchant_profiles')->insert([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'business_name' => 'Test Merchant',
            'legal_name' => 'Test Merchant Legal',
            'business_type' => 'proprietorship',
            'gst_number' => null,
            'contact_person_name' => 'Test User',
            'contact_email' => $email,
            'contact_mobile' => $mobile,
            'verification_status' => $verificationStatus,
            'verified_at' => $verificationStatus === 'approved' ? now() : null,
            'status' => $merchantStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $userId;
    }

    private function createUser(
        string $email = 'user@example.test',
        string $mobile = '9000000000',
        string $status = 'active',
    ): int {
        return (int) DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test User',
            'email' => $email,
            'mobile' => $mobile,
            'password' => Hash::make('password'),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createRole(string $name, string $slug): int
    {
        $existingRoleId = DB::table('auth_roles')->where('slug', $slug)->value('id');

        if ($existingRoleId !== null) {
            return (int) $existingRoleId;
        }

        return (int) DB::table('auth_roles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createShopForMerchantUser(int $userId, string $shopName, string $cityName, string $status = 'active'): int
    {
        $countryId = (int) (DB::table('loc_countries')->where('iso2', 'IN')->value('id')
            ?? DB::table('loc_countries')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'India',
                'iso2' => 'IN',
                'iso3' => 'IND',
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        $stateId = (int) (DB::table('loc_states')
            ->where('country_id', $countryId)
            ->where('iso2', 'MH')
            ->value('id')
            ?? DB::table('loc_states')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Maharashtra',
                'country_id' => $countryId,
                'country_code' => 'IN',
                'iso2' => 'MH',
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        $cityId = (int) (DB::table('loc_cities')
            ->where('country_id', $countryId)
            ->where('state_id', $stateId)
            ->where('name', $cityName)
            ->value('id')
            ?? DB::table('loc_cities')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => $cityName,
                'country_id' => $countryId,
                'state_id' => $stateId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        $categoryId = (int) (DB::table('shop_categories')->where('slug', 'apparel')->value('id')
            ?? DB::table('shop_categories')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Apparel',
                'slug' => 'apparel',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        $merchantId = (int) DB::table('merchant_profiles')
            ->where('user_id', $userId)
            ->value('id');

        return (int) DB::table('shops')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchantId,
            'shop_category_id' => $categoryId,
            'name' => $shopName,
            'slug' => Str::slug($shopName),
            'address_line_1' => 'Main Road',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function shopUuid(int $shopId): string
    {
        return (string) DB::table('shops')->where('id', $shopId)->value('uuid');
    }

    private function assertAuthenticatedAsUserId(int $userId): void
    {
        $this->assertAuthenticated();
        $this->assertSame($userId, auth()->id());
    }
}
