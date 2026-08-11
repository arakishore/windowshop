<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->assertSee(route('storefront.forgot-password'), false);
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

        $this->assertStringContainsString(route('storefront.login'), $content);
        $this->assertStringContainsString(route('storefront.register'), $content);
    }
}
