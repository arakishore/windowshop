<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use App\Services\Product\ProductDuplicationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class ProductMerchandisingTest extends TestCase
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

    public function test_featured_product_scopes_and_boundaries(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        $fixture = $this->fixture();

        $default = $this->product($fixture, ['product_name' => 'Default Product']);
        $current = $this->product($fixture, ['product_name' => 'Current', 'is_featured' => true, 'sort_order' => 2]);
        $scheduled = $this->product($fixture, ['product_name' => 'Scheduled', 'is_featured' => true, 'featured_from' => '2026-08-02 10:00:00']);
        $expired = $this->product($fixture, ['product_name' => 'Expired', 'is_featured' => true, 'featured_until' => '2026-07-31 10:00:00']);
        $fromBoundary = $this->product($fixture, ['product_name' => 'From Boundary', 'is_featured' => true, 'featured_from' => '2026-08-01 10:00:00', 'sort_order' => 1]);
        $untilBoundary = $this->product($fixture, ['product_name' => 'Until Boundary', 'is_featured' => true, 'featured_until' => '2026-08-01 10:00:00', 'sort_order' => 3]);
        $disabledWithDates = $this->product($fixture, ['product_name' => 'Disabled With Dates', 'is_featured' => false, 'featured_from' => '2026-07-01 10:00:00', 'featured_until' => '2026-09-01 10:00:00']);

        $this->assertFalse($default->fresh()->is_featured);
        $this->assertTrue($current->fresh()->isCurrentlyFeatured());
        $this->assertFalse($disabledWithDates->fresh()->isCurrentlyFeatured());

        $this->assertSame(
            [$fromBoundary->id, $current->id, $untilBoundary->id],
            Product::query()->currentlyFeatured()->featuredOrder()->pluck('id')->all(),
        );
        $this->assertSame([$scheduled->id], Product::query()->scheduledFeatured()->pluck('id')->all());
        $this->assertSame([$expired->id], Product::query()->expiredFeatured()->pluck('id')->all());
        $this->assertFalse(Schema::hasColumn('products', 'featured_sort_order'));

        Carbon::setTestNow();
    }

    public function test_admin_and_merchant_can_save_featured_fields_and_invalid_dates_are_rejected(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture);

        $this->actingAs($fixture['admin'])
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $this->payload($fixture, [
                'featured_from' => '2026-08-10 10:00',
                'featured_until' => '2026-08-09 10:00',
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('featured_until');

        $this->actingAs($fixture['admin'])
            ->put(route('admin.products.update', $product), $this->payload($fixture, [
                'sort_order' => 7,
                'is_featured' => '1',
                'featured_from' => '2026-08-01 09:00',
                'featured_until' => '2026-08-31 21:00',
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(7, $product->sort_order);
        $this->assertTrue($product->is_featured);
        $this->assertSame('2026-08-01 09:00:00', $product->featured_from->format('Y-m-d H:i:s'));

        $this->actingAs($fixture['merchantUser'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->put(route('merchant.products.update', $product), $this->payload($fixture, [
                'is_featured' => null,
                'featured_from' => '2026-08-01 09:00',
                'featured_until' => '2026-08-31 21:00',
            ]))
            ->assertRedirect(route('merchant.products.edit', $product))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertFalse($product->is_featured);
        $this->assertSame('2026-08-01 09:00:00', $product->featured_from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-31 21:00:00', $product->featured_until->format('Y-m-d H:i:s'));
    }

    public function test_featured_filters_and_bulk_actions_preserve_dates(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        $fixture = $this->fixture();
        $current = $this->product($fixture, ['product_name' => 'Featured Current', 'is_featured' => true]);
        $scheduled = $this->product($fixture, ['product_name' => 'Featured Scheduled', 'is_featured' => true, 'featured_from' => '2026-09-01 10:00:00']);
        $plain = $this->product($fixture, ['product_name' => 'Plain Product', 'featured_from' => '2026-09-15 10:00:00']);
        $disabledScheduled = $this->product($fixture, ['product_name' => 'Disabled Saved Schedule', 'featured_from' => '2026-09-20 10:00:00']);

        $this->actingAs($fixture['admin'])
            ->get(route('admin.products.index', ['featured' => 'current']))
            ->assertOk()
            ->assertSee('Featured Current')
            ->assertDontSee('Featured Scheduled');

        $this->actingAs($fixture['merchantUser'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->get(route('merchant.products.index', ['featured' => 'scheduled']))
            ->assertOk()
            ->assertSee('Featured Scheduled')
            ->assertSee('From 01 Sep 2026 10:00')
            ->assertDontSee('Featured Current');

        $this->actingAs($fixture['admin'])
            ->get(route('admin.products.index', ['featured' => 'scheduled']))
            ->assertOk()
            ->assertSee('Featured Scheduled')
            ->assertSee('From 01 Sep 2026 10:00')
            ->assertDontSee('Featured Current');

        $this->actingAs($fixture['admin'])
            ->post(route('admin.products.bulk-action'), [
                'action' => 'mark_featured',
                'product_ids' => [$plain->getKey()],
            ])
            ->assertRedirect();

        $plain->refresh();
        $this->assertTrue($plain->is_featured);
        $this->assertSame('2026-09-15 10:00:00', $plain->featured_from->format('Y-m-d H:i:s'));

        $this->actingAs($fixture['merchantUser'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->get(route('merchant.products.index', ['featured' => 'not_featured']))
            ->assertOk()
            ->assertSee('Disabled Saved Schedule')
            ->assertSee('Saved schedule')
            ->assertSee('From 20 Sep 2026 10:00');

        $this->actingAs($fixture['merchantUser'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.products.bulk-action'), [
                'action' => 'remove_featured',
                'product_ids' => [$current->getKey()],
            ])
            ->assertRedirect();

        $this->assertFalse($current->fresh()->is_featured);

        Carbon::setTestNow();
    }

    public function test_product_duplication_preserves_merchandising_fields(): void
    {
        $fixture = $this->fixture();
        $product = $this->product($fixture, [
            'sort_order' => 42,
            'is_featured' => true,
            'featured_from' => '2026-08-01 09:00:00',
            'featured_until' => '2026-08-31 21:00:00',
        ]);

        $duplicate = app(ProductDuplicationService::class)->duplicate($product, $fixture['admin']);

        $this->assertSame(42, $duplicate->sort_order);
        $this->assertTrue($duplicate->is_featured);
        $this->assertSame('2026-08-01 09:00:00', $duplicate->featured_from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-31 21:00:00', $duplicate->featured_until->format('Y-m-d H:i:s'));
    }

    /**
     * @return array{admin: User, merchantUser: User, merchant: MerchantProfile, shop: Shop, category: ProductCategory}
     */
    private function fixture(): array
    {
        $admin = $this->user('admin@example.test', 'admin');
        $merchantUser = $this->user('merchant-featured@example.test', 'merchant');
        $merchant = MerchantProfile::query()->create([
            'user_id' => $merchantUser->getKey(),
            'business_name' => 'Featured Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $root = ProductCategory::query()->create([
            'name' => 'Apparel',
            'slug' => 'apparel-'.Str::random(6),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Shirts',
            'slug' => 'shirts-'.Str::random(6),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Featured Shop',
            'slug' => 'featured-shop-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return compact('admin', 'merchantUser', 'merchant', 'shop', 'category');
    }

    private function user(string $email, string $roleSlug): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($roleSlug).' User',
            'email' => $email,
            'mobile' => '90000'.random_int(10000, 99999),
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

    /**
     * @param array<string, mixed> $overrides
     */
    private function product(array $fixture, array $overrides = []): Product
    {
        return Product::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'root_product_category_id' => $fixture['shop']->root_product_category_id,
            'product_category_id' => $fixture['category']->getKey(),
            'product_name' => $overrides['product_name'] ?? 'Featured Product '.Str::random(4),
            'slug' => 'featured-product-'.Str::random(8),
            'status' => 'draft',
            ...$overrides,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $fixture, array $overrides = []): array
    {
        return [
            'shop_id' => $fixture['shop']->getKey(),
            'product_category_id' => $fixture['category']->getKey(),
            'brand_id' => null,
            'product_name' => 'Updated Featured Product',
            'short_description' => null,
            'sort_order' => 0,
            'status' => 'draft',
            'tax_mode' => 'inherit',
            'tax_class_id' => null,
            ...$overrides,
        ];
    }
}
