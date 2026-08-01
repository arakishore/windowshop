<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductAvailabilityStatus;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Services\ProductAvailability\CustomerPurchaseAvailabilityGuard;
use App\Services\ProductAvailability\MerchantAvailabilityStatusSeeder;
use App\Services\ProductAvailability\ProductAvailabilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class ProductAvailabilityStatusTest extends TestCase
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

    public function test_defaults_are_created_idempotently_and_customisations_are_preserved(): void
    {
        $fixture = $this->fixture();
        $merchant = $fixture['merchant'];

        $this->assertSame(6, $merchant->availabilityStatuses()->count());

        $preorder = $this->availabilityStatus($merchant, ProductAvailabilityStatus::CODE_PREORDER);
        $preorder->forceFill([
            'name' => 'Book Before Launch',
            'customer_description' => 'Reserve this product before release.',
            'purchase_allowed' => false,
            'badge_type' => ProductAvailabilityStatus::BADGE_SECONDARY,
        ])->save();

        app(MerchantAvailabilityStatusSeeder::class)->seedDefaultsForMerchant($merchant->refresh());

        $this->assertSame(6, $merchant->availabilityStatuses()->count());
        $preorder->refresh();
        $this->assertSame('Book Before Launch', $preorder->name);
        $this->assertSame('Reserve this product before release.', $preorder->customer_description);
        $this->assertFalse($preorder->purchase_allowed);
        $this->assertSame(ProductAvailabilityStatus::BADGE_SECONDARY, $preorder->badge_type);
    }

    public function test_merchant_can_manage_only_own_availability_statuses(): void
    {
        $first = $this->fixture('first@example.test');
        $second = $this->fixture('second@example.test');
        $otherStatus = $this->availabilityStatus($second['merchant'], ProductAvailabilityStatus::CODE_BACKORDER);

        $this->actingAs($first['merchantUser'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->get(route('merchant.availability-statuses.index'))
            ->assertOk()
            ->assertSee('In Stock')
            ->assertDontSee($second['merchant']->business_name);

        $this->actingAs($first['merchantUser'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->put(route('merchant.availability-statuses.update', $otherStatus), [
                'name' => 'Hacked',
                'badge_type' => 'danger',
                'sort_order' => 1,
                'status' => 'active',
            ])
            ->assertNotFound();
    }

    public function test_product_defaults_to_in_stock_and_rejects_other_merchant_status(): void
    {
        $first = $this->fixture('first-product@example.test');
        $second = $this->fixture('second-product@example.test');

        $response = $this->actingAs($first['merchantUser'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->post(route('merchant.products.store'), [
                'shop_id' => $first['shop']->getKey(),
                'product_category_id' => $first['category']->getKey(),
                'product_name' => 'Availability Product',
                'status' => 'draft',
            ]);

        $response->assertRedirect();
        $product = Product::query()->where('product_name', 'Availability Product')->firstOrFail();
        $this->assertSame(ProductAvailabilityStatus::CODE_IN_STOCK, $product->availabilityStatus->code);

        $otherStatus = $this->availabilityStatus($second['merchant'], ProductAvailabilityStatus::CODE_BACKORDER);

        $this->actingAs($first['merchantUser'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->put(route('merchant.products.update', $product), [
                'shop_id' => $first['shop']->getKey(),
                'product_category_id' => $first['category']->getKey(),
                'product_name' => 'Availability Product',
                'availability_status_id' => $otherStatus->getKey(),
                'sort_order' => 0,
                'status' => 'draft',
                'tax_mode' => 'inherit',
            ])
            ->assertSessionHasErrors('availability_status_id');
    }

    public function test_variant_inherits_product_status_and_can_override_it(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, ProductAvailabilityStatus::CODE_OUT_OF_STOCK);
        $variant = $this->variant($product, ['stock_quantity' => 0]);
        $resolver = app(ProductAvailabilityResolver::class);

        $inherited = $resolver->resolve($variant);
        $this->assertSame(ProductAvailabilityStatus::CODE_OUT_OF_STOCK, $inherited['availability_code']);
        $this->assertFalse($inherited['can_purchase']);

        $variant->forceFill([
            'availability_status_id' => $this->availabilityStatus($fixture['merchant'], ProductAvailabilityStatus::CODE_PREORDER)->getKey(),
        ])->save();

        $overridden = $resolver->resolve($variant->refresh());
        $this->assertSame(ProductAvailabilityStatus::CODE_PREORDER, $overridden['availability_code']);
        $this->assertTrue($overridden['can_purchase']);
    }

    public function test_variant_assignment_rejects_other_merchant_status(): void
    {
        $first = $this->fixture('first-variant@example.test');
        $second = $this->fixture('second-variant@example.test');
        $product = $this->product($first);
        $variant = $this->variant($product);
        $otherStatus = $this->availabilityStatus($second['merchant'], ProductAvailabilityStatus::CODE_PREORDER);

        $this->actingAs($first['merchantUser'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->put(route('merchant.products.variants.update', $product), [
                'variants' => [
                    $variant->getKey() => [
                        'sku' => null,
                        'barcode' => null,
                        'mrp' => '100.00',
                        'selling_price' => '90.00',
                        'cost_price' => null,
                        'stock_quantity' => 0,
                        'low_stock_threshold' => 0,
                        'availability_status_id' => $otherStatus->getKey(),
                        'status' => 'active',
                    ],
                ],
            ])
            ->assertSessionHasErrors("variants.{$variant->getKey()}.availability_status_id");
    }

    public function test_zero_stock_purchase_rules_and_storefront_payload(): void
    {
        $fixture = $this->fixture();
        $resolver = app(ProductAvailabilityResolver::class);

        $inStock = $this->variant($this->product($fixture, ProductAvailabilityStatus::CODE_OUT_OF_STOCK), ['stock_quantity' => 3]);
        $this->assertTrue($resolver->resolve($inStock)['can_purchase']);

        $cases = [
            ProductAvailabilityStatus::CODE_OUT_OF_STOCK => false,
            ProductAvailabilityStatus::CODE_PREORDER => true,
            ProductAvailabilityStatus::CODE_BACKORDER => true,
            ProductAvailabilityStatus::CODE_COMING_SOON => false,
        ];

        foreach ($cases as $code => $expected) {
            $variant = $this->variant($this->product($fixture, $code), ['stock_quantity' => 0]);
            $payload = $resolver->resolve($variant);

            $this->assertSame($expected, $payload['can_purchase']);
            $this->assertSame(0, $payload['stock_quantity']);
            $this->assertFalse($payload['is_in_stock']);
            $this->assertSame($code, $payload['availability']['code']);
            $this->assertArrayHasKey('label', $payload['availability']);
            $this->assertArrayHasKey('badge_type', $payload['availability']);
        }
    }

    public function test_customer_guard_rejects_blocked_zero_stock_and_allows_preorder_checkout(): void
    {
        $fixture = $this->fixture();
        $blocked = $this->variant($this->product($fixture, ProductAvailabilityStatus::CODE_COMING_SOON), ['stock_quantity' => 0]);
        $allowed = $this->variant($this->product($fixture, ProductAvailabilityStatus::CODE_BACKORDER), ['stock_quantity' => 0]);
        $guard = app(CustomerPurchaseAvailabilityGuard::class);

        try {
            $guard->assertVariantCanBePurchased($blocked);
            $this->fail('Blocked zero-stock status should reject customer purchase.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $guard->assertVariantCanBePurchased($allowed);
        $guard->assertCheckoutCanProceed([$allowed]);
        $this->assertTrue(true);
    }

    public function test_used_status_cannot_be_deleted_and_unused_status_can_be_restored(): void
    {
        $fixture = $this->fixture();
        $used = $this->availabilityStatus($fixture['merchant'], ProductAvailabilityStatus::CODE_IN_STOCK);
        $this->product($fixture, ProductAvailabilityStatus::CODE_IN_STOCK);

        $this->actingAs($fixture['merchantUser'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->delete(route('merchant.availability-statuses.destroy', $used))
            ->assertSessionHasErrors('availability_status');

        $this->actingAs($fixture['merchantUser'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.availability-statuses.store'), [
                'name' => 'Limited Drop',
                'customer_description' => 'Available only while the limited batch lasts.',
                'purchase_allowed' => '1',
                'badge_type' => 'warning',
                'sort_order' => 70,
                'status' => 'active',
            ])
            ->assertRedirect();

        $custom = ProductAvailabilityStatus::query()->where('merchant_id', $fixture['merchant']->getKey())->where('code', 'LIMITED_DROP')->firstOrFail();

        $this->actingAs($fixture['merchantUser'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->delete(route('merchant.availability-statuses.destroy', $custom))
            ->assertRedirect();

        $this->assertSoftDeleted('product_availability_statuses', ['id' => $custom->getKey()]);

        $this->actingAs($fixture['merchantUser'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.availability-statuses.restore', $custom->getRouteKey()))
            ->assertRedirect();

        $this->assertFalse($custom->fresh()->trashed());
    }

    /**
     * @return array{merchantUser: User, merchant: MerchantProfile, shop: Shop, category: ProductCategory}
     */
    private function fixture(string $email = 'merchant-availability@example.test'): array
    {
        $merchantUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Availability Merchant',
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
            'user_id' => $merchantUser->getKey(),
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $merchant = MerchantProfile::query()->create([
            'user_id' => $merchantUser->getKey(),
            'business_name' => 'Availability Merchant '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        $root = ProductCategory::query()->create([
            'name' => 'Availability Root '.Str::random(4),
            'slug' => 'availability-root-'.Str::random(8),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Availability Leaf '.Str::random(4),
            'slug' => 'availability-leaf-'.Str::random(8),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Availability Shop '.Str::random(4),
            'slug' => 'availability-shop-'.Str::random(8),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return compact('merchantUser', 'merchant', 'shop', 'category');
    }

    private function availabilityStatus(MerchantProfile $merchant, string $code): ProductAvailabilityStatus
    {
        return ProductAvailabilityStatus::query()
            ->where('merchant_id', $merchant->getKey())
            ->where('code', $code)
            ->firstOrFail();
    }

    private function product(array $fixture, string $statusCode = ProductAvailabilityStatus::CODE_IN_STOCK): Product
    {
        return Product::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'root_product_category_id' => $fixture['shop']->root_product_category_id,
            'product_category_id' => $fixture['category']->getKey(),
            'availability_status_id' => $this->availabilityStatus($fixture['merchant'], $statusCode)->getKey(),
            'product_name' => 'Availability Product '.Str::random(4),
            'slug' => 'availability-product-'.Str::random(8),
            'status' => 'draft',
            'tax_mode' => 'inherit',
        ]);
    }

    private function variant(Product $product, array $overrides = []): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'name' => $product->product_name,
            'mrp' => '100.00',
            'selling_price' => '90.00',
            'stock_quantity' => 0,
            'low_stock_threshold' => 0,
            'is_default' => true,
            'sort_order' => 0,
            'status' => 'active',
            ...$overrides,
        ]);
    }
}
