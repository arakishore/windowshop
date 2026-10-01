<?php

namespace Tests\Feature;

use App\Events\MerchantAccountCreated;
use App\Models\Customer;
use App\Models\MerchantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontMerchantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleId('merchant');
        $this->roleId('customer');
    }

    public function test_public_merchant_registration_page_renders(): void
    {
        $this->assertSame(url('/merchant-registration'), route('storefront.merchant-register'));
        $this->assertSame(url('/merchant-registration'), route('storefront.merchant-register.store'));
        $this->assertSame(url('/merchant-registration/success'), route('storefront.merchant-register.success'));

        $this->get(route('storefront.merchant-register'))
            ->assertOk()
            ->assertSee('Sell on')
            ->assertSee('Create Merchant Account')
            ->assertSee(route('merchant.login'), false);
    }

    public function test_new_visitor_registration_creates_merchant_foundation_with_safe_states(): void
    {
        Event::fake([MerchantAccountCreated::class]);

        $response = $this->post(route('storefront.merchant-register.store'), $this->payload());

        $user = User::query()->where('email', 'seller@example.test')->firstOrFail();
        $merchant = MerchantProfile::query()->where('user_id', $user->getKey())->firstOrFail();

        $response->assertRedirect(route('storefront.merchant-register.success'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('storefront', $user->registration_source);
        $this->assertSame('active', $merchant->status);
        $this->assertSame('pending', $merchant->verification_status);
        $this->assertDatabaseHas('auth_user_roles', [
            'user_id' => $user->getKey(),
            'role_id' => $this->roleId('merchant'),
        ]);
        $this->assertGreaterThan(0, DB::table('merchant_settings')->where('merchant_id', $merchant->getKey())->count());
        $this->assertGreaterThan(0, DB::table('product_availability_statuses')->where('merchant_id', $merchant->getKey())->count());
        Event::assertDispatched(MerchantAccountCreated::class, fn ($event): bool => $event->merchant->is($merchant) && $event->storefrontRegistration);
    }

    public function test_authenticated_customer_is_upgraded_without_changing_identity_or_password(): void
    {
        Event::fake([MerchantAccountCreated::class]);
        $user = User::query()->create([
            'name' => 'Existing Customer',
            'email' => 'customer@example.test',
            'mobile' => '9876543211',
            'password' => Hash::make('original-password'),
            'status' => 'active',
            'registration_source' => 'web',
        ]);
        $originalPassword = $user->password;
        $customer = Customer::query()->create([
            'user_id' => $user->getKey(),
            'name' => $user->name,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'status' => 'active',
        ]);
        DB::table('auth_user_roles')->insert([
            'user_id' => $user->getKey(),
            'role_id' => $this->roleId('customer'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('storefront.merchant-register.store'), [
            'name' => 'Existing Customer',
            'email' => 'customer@example.test',
            'mobile' => '9876543211',
            'business_name' => 'Customer Business',
            'business_type' => 'proprietorship',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('storefront.merchant-register.success'));
        $this->assertSame($user->getKey(), MerchantProfile::query()->sole()->user_id);
        $this->assertTrue(Customer::query()->findOrFail($customer->getKey())->user->is($user));
        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertDatabaseHas('auth_user_roles', ['user_id' => $user->getKey(), 'role_id' => $this->roleId('customer')]);
        $this->assertDatabaseHas('auth_user_roles', ['user_id' => $user->getKey(), 'role_id' => $this->roleId('merchant')]);
        Event::assertDispatchedTimes(MerchantAccountCreated::class, 1);
        Event::assertDispatched(MerchantAccountCreated::class, fn ($event): bool => $event->storefrontRegistration);
    }

    public function test_anonymous_existing_email_cannot_upgrade_account(): void
    {
        Event::fake([MerchantAccountCreated::class]);
        $user = User::query()->create([
            'name' => 'Protected User',
            'email' => 'seller@example.test',
            'mobile' => '9876543212',
            'password' => Hash::make('original-password'),
            'status' => 'active',
        ]);
        DB::table('auth_user_roles')->insert([
            'user_id' => $user->getKey(),
            'role_id' => $this->roleId('customer'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->from(route('storefront.merchant-register'))
            ->post(route('storefront.merchant-register.store'), $this->payload())
            ->assertRedirect(route('storefront.merchant-register'))
            ->assertSessionHasErrors(['email' => 'An account already exists with this email. Sign in to continue merchant registration.']);

        $this->assertDatabaseCount('merchant_profiles', 0);
        Event::assertNotDispatched(MerchantAccountCreated::class);

        $this->post(route('storefront.login.store'), [
            'email' => 'seller@example.test',
            'password' => 'original-password',
        ])->assertRedirect(route('storefront.merchant-register'));
    }

    public function test_existing_merchant_is_not_duplicated(): void
    {
        $user = $this->merchantUser('existing-merchant@example.test');

        $this->actingAs($user)
            ->get(route('storefront.merchant-register'))
            ->assertRedirect(route('merchant.dashboard'));

        $this->assertDatabaseCount('merchant_profiles', 1);
    }

    public function test_public_request_cannot_select_merchant_statuses(): void
    {
        Event::fake([MerchantAccountCreated::class]);

        $this->post(route('storefront.merchant-register.store'), [
            ...$this->payload(),
            'status' => 'suspended',
            'verification_status' => 'approved',
        ])->assertRedirect(route('storefront.merchant-register.success'));

        $merchant = MerchantProfile::query()->sole();
        $this->assertSame('active', $merchant->status);
        $this->assertSame('pending', $merchant->verification_status);
    }

    public function test_pending_rejected_inactive_and_suspended_merchants_can_authenticate(): void
    {
        foreach ([
            ['pending@example.test', 'active', 'pending'],
            ['rejected@example.test', 'active', 'rejected'],
            ['inactive@example.test', 'inactive', 'approved'],
            ['suspended@example.test', 'suspended', 'approved'],
        ] as [$email, $status, $verification]) {
            $this->merchantUser($email, $status, $verification);

            $this->post(route('merchant.authenticate'), ['login' => $email, 'password' => 'password'])
                ->assertRedirect(route('merchant.dashboard'));

            auth()->logout();
        }
    }

    public function test_suspended_merchant_sees_warning_and_cannot_mutate(): void
    {
        $user = $this->merchantUser('restricted@example.test', 'suspended', 'approved');

        $this->actingAs($user)
            ->get(route('merchant.profile.edit'))
            ->assertOk()
            ->assertSee('Merchant account suspended')
            ->assertSee('Merchant operations are restricted');

        $this->actingAs($user)
            ->post(route('merchant.shops.store'), [])
            ->assertForbidden();
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return [
            'name' => 'Public Seller',
            'email' => 'seller@example.test',
            'mobile' => '9876543210',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'business_name' => 'Public Seller Store',
            'business_type' => 'proprietorship',
            'terms' => '1',
        ];
    }

    private function merchantUser(string $email, string $status = 'active', string $verification = 'approved'): User
    {
        $user = User::query()->create([
            'name' => 'Merchant User',
            'email' => $email,
            'mobile' => '9'.random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => $status,
        ]);
        DB::table('auth_user_roles')->insert([
            'user_id' => $user->getKey(),
            'role_id' => $this->roleId('merchant'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Merchant Business',
            'status' => $status,
            'verification_status' => $verification,
        ]);

        return $user->refresh();
    }

    private function roleId(string $slug): int
    {
        DB::table('auth_roles')->updateOrInsert(['slug' => $slug], [
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('auth_roles')->where('slug', $slug)->value('id');
    }
}
