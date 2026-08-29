<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductReturnPolicy;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\Merchant\ShopSettingsService;
use App\Services\Product\ProductReturnPolicyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class ProductReturnPolicyResolverTest extends TestCase
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

    public function test_product_with_no_override_uses_complete_shop_policy(): void
    {
        $fixture = $this->fixture();
        $this->setShopPolicy($fixture['shop'], true, 5, true, 10);

        $policy = $this->resolver()->resolve($fixture['product']);

        $this->assertSame([
            'refund_allowed' => true,
            'refund_window_days' => 5,
            'exchange_allowed' => true,
            'exchange_window_days' => 10,
            'refund_source' => 'shop',
            'exchange_source' => 'shop',
        ], $policy);
    }

    public function test_full_product_override_wins_over_shop_policy(): void
    {
        $fixture = $this->fixture();
        $this->setShopPolicy($fixture['shop'], false, 0, true, 7);
        $this->policy($fixture['product'], true, 4, false, 0);

        $policy = $this->resolver()->resolve($fixture['product']->refresh());

        $this->assertTrue($policy['refund_allowed']);
        $this->assertSame(4, $policy['refund_window_days']);
        $this->assertFalse($policy['exchange_allowed']);
        $this->assertSame(0, $policy['exchange_window_days']);
        $this->assertSame('product', $policy['refund_source']);
        $this->assertSame('product', $policy['exchange_source']);
    }

    public function test_partial_refund_override_inherits_remaining_refund_value(): void
    {
        $fixture = $this->fixture();
        $this->setShopPolicy($fixture['shop'], true, 9, true, 7);
        $this->policy($fixture['product'], true, null, null, null);

        $policy = $this->resolver()->resolve($fixture['product']->refresh());

        $this->assertTrue($policy['refund_allowed']);
        $this->assertSame(9, $policy['refund_window_days']);
        $this->assertSame('product', $policy['refund_source']);
    }

    public function test_partial_exchange_override_inherits_remaining_exchange_value(): void
    {
        $fixture = $this->fixture();
        $this->setShopPolicy($fixture['shop'], false, 0, true, 6);
        $this->policy($fixture['product'], null, null, true, null);

        $policy = $this->resolver()->resolve($fixture['product']->refresh());

        $this->assertTrue($policy['exchange_allowed']);
        $this->assertSame(6, $policy['exchange_window_days']);
        $this->assertSame('product', $policy['exchange_source']);
    }

    public function test_null_correctly_means_inherit(): void
    {
        $fixture = $this->fixture();
        $this->setShopPolicy($fixture['shop'], true, 11, false, 0);
        $this->policy($fixture['product'], null, null, null, null);

        $policy = $this->resolver()->resolve($fixture['product']->refresh());

        $this->assertTrue($policy['refund_allowed']);
        $this->assertSame(11, $policy['refund_window_days']);
        $this->assertFalse($policy['exchange_allowed']);
        $this->assertSame(0, $policy['exchange_window_days']);
    }

    public function test_explicit_false_is_not_treated_as_null(): void
    {
        $fixture = $this->fixture();
        $this->setShopPolicy($fixture['shop'], true, 12, true, 8);
        $this->policy($fixture['product'], false, null, false, null);

        $policy = $this->resolver()->resolve($fixture['product']->refresh());

        $this->assertFalse($policy['refund_allowed']);
        $this->assertSame(0, $policy['refund_window_days']);
        $this->assertFalse($policy['exchange_allowed']);
        $this->assertSame(0, $policy['exchange_window_days']);
    }

    public function test_refund_disabled_resolves_window_to_zero(): void
    {
        $fixture = $this->fixture();
        $this->setShopPolicy($fixture['shop'], true, 12, true, 7);
        $this->policy($fixture['product'], false, 12, null, null);

        $policy = $this->resolver()->resolve($fixture['product']->refresh());

        $this->assertFalse($policy['refund_allowed']);
        $this->assertSame(0, $policy['refund_window_days']);
    }

    public function test_exchange_disabled_resolves_window_to_zero(): void
    {
        $fixture = $this->fixture();
        $this->setShopPolicy($fixture['shop'], false, 0, true, 7);
        $this->policy($fixture['product'], null, null, false, 7);

        $policy = $this->resolver()->resolve($fixture['product']->refresh());

        $this->assertFalse($policy['exchange_allowed']);
        $this->assertSame(0, $policy['exchange_window_days']);
    }

    public function test_product_can_override_exchange_from_seven_days_to_three_days(): void
    {
        $fixture = $this->fixture();
        $this->setShopPolicy($fixture['shop'], false, 0, true, 7);
        $this->policy($fixture['product'], null, null, true, 3);

        $policy = $this->resolver()->resolve($fixture['product']->refresh());

        $this->assertTrue($policy['exchange_allowed']);
        $this->assertSame(3, $policy['exchange_window_days']);
    }

    public function test_product_can_enable_refund_when_shop_refund_is_disabled(): void
    {
        $fixture = $this->fixture();
        $this->setShopPolicy($fixture['shop'], false, 0, true, 7);
        $this->policy($fixture['product'], true, 2, null, null);

        $policy = $this->resolver()->resolve($fixture['product']->refresh());

        $this->assertTrue($policy['refund_allowed']);
        $this->assertSame(2, $policy['refund_window_days']);
    }

    public function test_product_can_disable_exchange_when_shop_exchange_is_enabled(): void
    {
        $fixture = $this->fixture();
        $this->setShopPolicy($fixture['shop'], false, 0, true, 7);
        $this->policy($fixture['product'], null, null, false, null);

        $policy = $this->resolver()->resolve($fixture['product']->refresh());

        $this->assertFalse($policy['exchange_allowed']);
        $this->assertSame(0, $policy['exchange_window_days']);
    }

    public function test_missing_product_policy_row_does_not_fail(): void
    {
        $fixture = $this->fixture();

        $policy = $this->resolver()->resolve($fixture['product']->load('shop', 'returnPolicy'));

        $this->assertFalse($policy['refund_allowed']);
        $this->assertSame(0, $policy['refund_window_days']);
        $this->assertTrue($policy['exchange_allowed']);
        $this->assertSame(7, $policy['exchange_window_days']);
    }

    public function test_resolver_uses_existing_shop_settings_defaults_when_rows_are_missing(): void
    {
        $fixture = $this->fixture();
        ShopSetting::query()
            ->where('shop_id', $fixture['shop']->getKey())
            ->where('group', 'returns')
            ->delete();

        $policy = $this->resolver()->resolve($fixture['product']->refresh());

        $this->assertFalse($policy['refund_allowed']);
        $this->assertSame(0, $policy['refund_window_days']);
        $this->assertTrue($policy['exchange_allowed']);
        $this->assertSame(7, $policy['exchange_window_days']);
    }

    private function resolver(): ProductReturnPolicyResolver
    {
        return app(ProductReturnPolicyResolver::class);
    }

    private function setShopPolicy(Shop $shop, bool $refundAllowed, int $refundWindowDays, bool $exchangeAllowed, int $exchangeWindowDays): void
    {
        $settings = app(ShopSettingsService::class);
        $settings->setTyped($shop->getKey(), 'returns', 'refund_allowed', $refundAllowed, ShopSetting::TYPE_BOOLEAN);
        $settings->setTyped($shop->getKey(), 'returns', 'refund_window_days', $refundWindowDays, ShopSetting::TYPE_INTEGER);
        $settings->setTyped($shop->getKey(), 'returns', 'exchange_allowed', $exchangeAllowed, ShopSetting::TYPE_BOOLEAN);
        $settings->setTyped($shop->getKey(), 'returns', 'exchange_window_days', $exchangeWindowDays, ShopSetting::TYPE_INTEGER);
    }

    private function policy(Product $product, ?bool $refundAllowed, ?int $refundWindowDays, ?bool $exchangeAllowed, ?int $exchangeWindowDays): ProductReturnPolicy
    {
        return ProductReturnPolicy::query()->create([
            'product_id' => $product->getKey(),
            'refund_allowed' => $refundAllowed,
            'refund_window_days' => $refundWindowDays,
            'exchange_allowed' => $exchangeAllowed,
            'exchange_window_days' => $exchangeWindowDays,
        ]);
    }

    /**
     * @return array{merchant: MerchantProfile, shop: Shop, category: ProductCategory, product: Product}
     */
    private function fixture(): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Policy Merchant',
            'email' => 'policy-'.Str::random(6).'@example.test',
            'mobile' => '90000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Policy Merchant '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $root = ProductCategory::query()->create([
            'name' => 'Apparel '.Str::random(4),
            'slug' => 'apparel-'.Str::random(6),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Shirts '.Str::random(4),
            'slug' => 'shirts-'.Str::random(6),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Policy Shop '.Str::random(6),
            'slug' => 'policy-shop-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $root->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => 'Policy Product '.Str::random(6),
            'slug' => 'policy-product-'.Str::random(6),
            'status' => 'draft',
        ]);

        return [
            'merchant' => $merchant,
            'shop' => $shop,
            'category' => $category,
            'product' => $product,
        ];
    }
}
