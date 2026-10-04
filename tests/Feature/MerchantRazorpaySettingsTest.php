<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\PaymentAttempt;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use App\Services\Merchant\MerchantSettingsInitializer;
use App\Services\Payment\PaymentAccountResolver;
use App\Services\Payment\PaymentAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MerchantRazorpaySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_enabled_razorpay_requires_complete_configuration(): void
    {
        [$merchant, $shop] = $this->merchantShop();

        $this->save($merchant, $shop, ['razorpay_enabled' => '1', 'razorpay_name' => '', 'razorpay_key_id' => 'rzp_test_key', 'razorpay_key_secret' => 'secret'])
            ->assertSessionHasErrors('razorpay_name');
        $this->save($merchant, $shop, ['razorpay_enabled' => '1', 'razorpay_name' => 'Merchant', 'razorpay_key_id' => '', 'razorpay_key_secret' => 'secret'])
            ->assertSessionHasErrors('razorpay_key_id');
        $this->save($merchant, $shop, ['razorpay_enabled' => '1', 'razorpay_name' => 'Merchant', 'razorpay_key_id' => 'rzp_test_key', 'razorpay_key_secret' => ''])
            ->assertSessionHasErrors('razorpay_key_secret');

        $this->assertDatabaseCount('payment_accounts', 0);
    }

    public function test_enabled_existing_account_with_blank_secret_preserves_encrypted_secret(): void
    {
        [$merchant, $shop] = $this->merchantShop();
        $account = $this->account($merchant, $shop, 'test', true, 'original-secret');
        $ciphertext = $account->getRawOriginal('secret');

        $this->save($merchant, $shop, [
            'razorpay_enabled' => '1', 'razorpay_name' => 'Updated Name',
            'razorpay_key_id' => 'rzp_test_updated', 'razorpay_key_secret' => '',
        ])->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertTrue($account->enabled);
        $this->assertSame('Updated Name', $account->name);
        $this->assertSame('rzp_test_updated', $account->public_key);
        $this->assertSame($ciphertext, $account->getRawOriginal('secret'));
        $this->assertSame('original-secret', app(PaymentAccountService::class)->secret($account));
    }

    public function test_disabled_blank_configuration_clears_credentials_without_deleting_history_or_live_account(): void
    {
        [$merchant, $shop] = $this->merchantShop();
        $test = $this->account($merchant, $shop, 'test', true, 'test-secret');
        $live = $this->account($merchant, $shop, 'live', true, 'live-secret');
        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(), 'order_number' => 'WS-HISTORY-1',
            'merchant_id' => $merchant->getKey(), 'shop_id' => $shop->getKey(),
            'created_source' => Order::SOURCE_STOREFRONT, 'order_status' => Order::STATUS_PENDING,
            'payment_method' => 'online_payment', 'payment_status' => Order::PAYMENT_PAID,
            'grand_total' => 999, 'amount_paid' => 999,
        ]);
        $attempt = PaymentAttempt::query()->create([
            'order_id' => $order->getKey(), 'shop_id' => $shop->getKey(), 'payment_account_id' => $test->getKey(),
            'provider' => 'razorpay', 'provider_order_id' => 'order_history', 'provider_payment_id' => 'pay_history',
            'amount_minor' => 99900, 'currency' => 'INR', 'status' => PaymentAttempt::PAID, 'paid_at' => now(),
        ]);

        $this->save($merchant, $shop, [
            'razorpay_environment' => 'test', 'razorpay_name' => '',
            'razorpay_key_id' => '', 'razorpay_key_secret' => '',
        ])->assertSessionHasNoErrors();

        $test->refresh();
        $live->refresh();
        $attempt->refresh();
        $this->assertFalse($test->enabled);
        $this->assertSame('', $test->name);
        $this->assertNull($test->public_key);
        $this->assertNull($test->secret);
        $this->assertTrue($test->shops()->whereKey($shop)->exists());
        $this->assertDatabaseHas('payment_accounts', ['id' => $test->getKey()]);
        $this->assertSame('order_history', $attempt->provider_order_id);
        $this->assertSame('pay_history', $attempt->provider_payment_id);
        $this->assertSame(99900, $attempt->amount_minor);
        $this->assertSame(PaymentAttempt::PAID, $attempt->status);
        $this->assertSame(Order::PAYMENT_PAID, $order->fresh()->payment_status);
        $this->assertTrue($live->enabled);
        $this->assertSame('rzp_live_key', $live->public_key);
        $this->assertSame('live-secret', app(PaymentAccountService::class)->secret($live));
        $this->assertNull(app(PaymentAccountResolver::class)->resolveForShop($shop, 'razorpay', 'test'));
        $this->assertSame($live->getKey(), app(PaymentAccountResolver::class)->resolveForShop($shop, 'razorpay', 'live')?->getKey());

        $this->save($merchant, $shop, [
            'razorpay_environment' => 'test', 'razorpay_enabled' => '1', 'razorpay_name' => 'Reconfigured Test',
            'razorpay_key_id' => 'rzp_test_new', 'razorpay_key_secret' => 'new-test-secret',
        ])->assertSessionHasNoErrors();
        $this->assertSame($test->getKey(), PaymentAccount::query()->where('mode', 'test')->sole()->getKey());
        $this->assertSame('new-test-secret', app(PaymentAccountService::class)->secret($test->fresh()));
    }

    public function test_clearing_live_does_not_modify_test(): void
    {
        [$merchant, $shop] = $this->merchantShop();
        $test = $this->account($merchant, $shop, 'test', true, 'test-secret');
        $live = $this->account($merchant, $shop, 'live', true, 'live-secret');

        $this->save($merchant, $shop, [
            'razorpay_environment' => 'live', 'razorpay_name' => '',
            'razorpay_key_id' => '', 'razorpay_key_secret' => '',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($live->fresh()->enabled);
        $this->assertNull($live->fresh()->secret);
        $this->assertTrue($test->fresh()->enabled);
        $this->assertSame('rzp_test_key', $test->fresh()->public_key);
        $this->assertSame('test-secret', app(PaymentAccountService::class)->secret($test->fresh()));
    }

    public function test_enabled_account_with_missing_secret_does_not_resolve(): void
    {
        [$merchant, $shop] = $this->merchantShop();
        $account = $this->account($merchant, $shop, 'test', true, 'test-secret');
        app(PaymentAccountService::class)->clearSecret($account);

        $this->assertNull(app(PaymentAccountResolver::class)->resolveForShop($shop, 'razorpay', 'test'));
    }

    private function save(MerchantProfile $merchant, Shop $shop, array $overrides)
    {
        return $this->actingAs($merchant->user)->withSession([
            'merchant_id' => $merchant->getKey(), 'active_shop_id' => $shop->getKey(),
        ])->from(route('merchant.settings.edit'))->put(route('merchant.settings.update'), [
            'active_tab' => 'payments', 'settings' => $this->defaultMerchantSettings(),
            'razorpay_environment' => 'test', ...$overrides,
        ]);
    }

    private function defaultMerchantSettings(): array
    {
        $payload = [];
        foreach (app(MerchantSettingsInitializer::class)->defaults() as $group => $settings) {
            foreach ($settings as $key => $definition) {
                $payload[$group][$key] = $definition['value'];
            }
        }

        return $payload;
    }

    private function account(MerchantProfile $merchant, Shop $shop, string $mode, bool $enabled, string $secret): PaymentAccount
    {
        $service = app(PaymentAccountService::class);
        $account = $service->create($merchant, [
            'provider' => 'razorpay', 'name' => ucfirst($mode).' Account', 'mode' => $mode,
            'enabled' => $enabled, 'public_key' => 'rzp_'.$mode.'_key', 'secret' => $secret,
        ]);
        $service->map($account, $shop);

        return $account;
    }

    private function merchantShop(): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(), 'name' => 'Razorpay Merchant',
            'email' => Str::random(8).'@example.test', 'mobile' => '9000012345',
            'password' => Hash::make('password'), 'status' => 'active',
        ]);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(), 'business_name' => 'Razorpay Business '.Str::random(4),
            'verification_status' => 'approved', 'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'uuid' => (string) Str::uuid(), 'name' => 'Razorpay Category '.Str::random(4),
            'slug' => 'razorpay-category-'.Str::random(6), 'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(), 'root_product_category_id' => $category->getKey(),
            'name' => 'Razorpay Shop', 'slug' => 'razorpay-shop-'.Str::random(6),
            'address_line_1' => 'Main Road', 'status' => 'active',
        ]);
        $roleId = DB::table('auth_roles')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'Merchant', 'slug' => 'merchant',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('auth_user_roles')->insert(['user_id' => $user->getKey(), 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);

        return [$merchant, $shop];
    }
}
