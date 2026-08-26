<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use PDO;
use Tests\TestCase;

class StorefrontCustomerAuthPagesTest extends TestCase
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

    public function test_customer_login_page_renders_static_login_and_register_panels(): void
    {
        $this->get(route('storefront.login'))
            ->assertOk()
            ->assertSee('Customer Login')
            ->assertSee('New Customer')
            ->assertSee('Returning Customer')
            ->assertSee('Checkout faster with saved customer details.')
            ->assertSee('Receive new offers and latest trends from nearby shops.')
            ->assertSee('Forgotten Password')
            ->assertSee(route('storefront.register'), false)
            ->assertSee(route('storefront.forgot-password'), false)
            ->assertSee('data-customer-login-form', false)
            ->assertSee('Please enter a valid email address.')
            ->assertSee('validate(false)', false)
            ->assertSee('validate(true)', false);
    }

    public function test_customer_register_and_forgot_password_pages_render(): void
    {
        $this->get(route('storefront.register'))
            ->assertOk()
            ->assertSee('Create Your Account')
            ->assertSee('Your Personal Details')
            ->assertSee('Last Name')
            ->assertSee('Phone Number')
            ->assertSee('E-Mail')
            ->assertSee('Your Password')
            ->assertSee('Confirm password:')
            ->assertSee('data-customer-register-form', false)
            ->assertSee('Password must be at least 8 characters.')
            ->assertSee('Passwords do not match.')
            ->assertSee('Please accept the terms and conditions.')
            ->assertSee('Terms &amp; Conditions', false)
            ->assertSee('href="'.route('storefront.terms').'"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('validate(false)', false)
            ->assertSee('validate(true)', false)
            ->assertSee('Faster Checkout')
            ->assertSee('Follow Local Shops')
            ->assertSee('Order Updates')
            ->assertSee('New Offers')
            ->assertSee('Latest Trends')
            ->assertSee('Saved Favourites')
            ->assertDontSee('<section class="section-page-title text-center storefront-page-title">', false)
            ->assertSee(route('storefront.login'), false);

        $this->get(route('storefront.forgot-password'))
            ->assertOk()
            ->assertSee('Forgotten Password')
            ->assertSee('Enter your account e-mail')
            ->assertSee('Check your inbox')
            ->assertSee('Create a new password')
            ->assertSee('assets/storefront/images/section/forgot_password.png', false)
            ->assertSee('E-Mail Address')
            ->assertSee('Back to Login')
            ->assertSee(route('storefront.login'), false);
    }

    public function test_storefront_account_links_point_to_customer_login(): void
    {
        $content = $this->get(route('storefront.home'))->assertOk()->getContent();

        $this->assertStringContainsString(route('storefront.account'), $content);
        $this->assertStringContainsString(route('storefront.register'), $content);
    }

    public function test_customer_account_requires_customer_and_auth_pages_redirect_authenticated_customer(): void
    {
        $this->get(route('storefront.account'))
            ->assertRedirect(route('storefront.login'));

        $customer = $this->customerUser('account-customer@example.test');
        $roleId = $this->assignRole($customer, 'customer');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account'))
            ->assertOk()
            ->assertSee('My Account')
            ->assertSee('Welcome back, '.$customer->name)
            ->assertSee('Dashboard')
            ->assertSee('Profile')
            ->assertSee('Addresses')
            ->assertSee('My Orders')
            ->assertSee('Wishlist');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.login'))
            ->assertRedirect(route('storefront.account'));

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.register'))
            ->assertRedirect(route('storefront.account'));
    }

    private function customerUser(string $email): User
    {
        $user = User::query()->create([
            'name' => 'Account Customer',
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        return $user->refresh();
    }

    private function assignRole(User $user, string $slug): int
    {
        $roleId = $this->roleId($slug);

        DB::table('auth_user_roles')->updateOrInsert([
            'user_id' => $user->getKey(),
            'role_id' => $roleId,
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $roleId;
    }

    private function roleId(string $slug): int
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

        return (int) DB::table('auth_roles')->where('slug', $slug)->value('id');
    }
}
