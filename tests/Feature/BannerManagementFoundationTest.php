<?php

namespace Tests\Feature;

use App\Enums\BannerLinkType;
use App\Enums\BannerPosition;
use App\Models\Banner;
use App\Models\MerchantProfile;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use App\Services\Banner\BannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class BannerManagementFoundationTest extends TestCase
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

    public function test_admin_can_create_marketplace_banner_with_enum_position(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.banners.store'), [
                'owner_type' => 'marketplace',
                'position' => BannerPosition::HOMEPAGE_HERO->value,
                'title' => 'Homepage Sale',
                'desktop_image' => UploadedFile::fake()->image('desktop.jpg', 1200, 400),
                'link_type' => BannerLinkType::NONE->value,
                'sort_order' => 1,
                'status' => Banner::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('banners', [
            'position' => BannerPosition::HOMEPAGE_HERO->value,
            'merchant_id' => null,
            'shop_id' => null,
            'title' => 'Homepage Sale',
            'status' => Banner::STATUS_ACTIVE,
        ]);
    }

    public function test_admin_banner_form_exposes_guided_sections_and_target_controls(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.banners.create'))
            ->assertOk()
            ->assertSee('Banner Details')
            ->assertSee('Images')
            ->assertSee('Navigation')
            ->assertSee('Display Settings And Schedule')
            ->assertSee('Search Product')
            ->assertSee('Desktop Preview')
            ->assertSee('Quick Suggestions')
            ->assertSee('Main slider on marketplace homepage');
    }

    public function test_custom_url_can_be_marked_to_open_in_new_tab(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.banners.store'), [
                'owner_type' => 'marketplace',
                'position' => BannerPosition::HOMEPAGE_HERO->value,
                'title' => 'External Offer',
                'desktop_image' => UploadedFile::fake()->image('desktop.jpg', 1200, 400),
                'link_type' => BannerLinkType::CUSTOM_URL->value,
                'link_value' => 'https://example.com/deal',
                'open_in_new_tab' => '1',
                'status' => Banner::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('banners', [
            'title' => 'External Offer',
            'link_type' => BannerLinkType::CUSTOM_URL->value,
            'link_value' => 'https://example.com/deal',
            'open_in_new_tab' => true,
        ]);
    }

    public function test_marketplace_banner_rejects_merchant_only_position(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.banners.store'), [
                'owner_type' => 'marketplace',
                'position' => BannerPosition::STORE_HERO->value,
                'title' => 'Wrong Scope',
                'desktop_image' => UploadedFile::fake()->image('desktop.jpg', 1200, 400),
                'link_type' => BannerLinkType::NONE->value,
                'status' => Banner::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('position');
    }

    public function test_merchant_banner_uses_active_shop_and_rejects_admin_position(): void
    {
        Storage::fake('public');
        $fixture = $this->merchantFixture('merchant-banner@example.test');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.banners.store'), [
                'position' => BannerPosition::HOMEPAGE_HERO->value,
                'title' => 'Admin Position',
                'desktop_image' => UploadedFile::fake()->image('desktop.jpg', 1200, 400),
                'link_type' => BannerLinkType::NONE->value,
                'status' => Banner::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('position');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.banners.store'), [
                'position' => BannerPosition::STORE_HERO->value,
                'title' => 'Storefront Hero',
                'desktop_image' => UploadedFile::fake()->image('desktop.jpg', 1200, 400),
                'link_type' => BannerLinkType::NONE->value,
                'status' => Banner::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('banners', [
            'position' => BannerPosition::STORE_HERO->value,
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'title' => 'Storefront Hero',
        ]);
    }

    public function test_merchant_cannot_edit_another_shop_banner(): void
    {
        $first = $this->merchantFixture('first-banner@example.test');
        $second = $this->merchantFixture('second-banner@example.test');
        $banner = $this->banner(BannerPosition::STORE_HERO, $second['merchant']->getKey(), $second['shop']->getKey());

        $this->actingAs($first['user'])
            ->withSession(['active_shop_id' => $first['shop']->getKey()])
            ->get(route('merchant.banners.edit', $banner))
            ->assertNotFound();
    }

    public function test_max_active_or_scheduled_banners_is_enforced_from_enum(): void
    {
        Storage::fake('public');
        $fixture = $this->merchantFixture('max-banner@example.test');

        for ($i = 0; $i < BannerPosition::STORE_MIDDLE->maxBanners(); $i++) {
            $this->banner(BannerPosition::STORE_MIDDLE, $fixture['merchant']->getKey(), $fixture['shop']->getKey(), ['title' => 'Banner '.$i]);
        }

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.banners.store'), [
                'position' => BannerPosition::STORE_MIDDLE->value,
                'title' => 'Too Many',
                'desktop_image' => UploadedFile::fake()->image('desktop.jpg', 1200, 400),
                'link_type' => BannerLinkType::NONE->value,
                'status' => Banner::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('position');
    }

    public function test_storefront_service_filters_visibility_and_owner_scope(): void
    {
        $fixture = $this->merchantFixture('service-banner@example.test');
        $visible = $this->banner(BannerPosition::STORE_HERO, $fixture['merchant']->getKey(), $fixture['shop']->getKey(), ['title' => 'Visible', 'sort_order' => 2]);
        $first = $this->banner(BannerPosition::STORE_HERO, $fixture['merchant']->getKey(), $fixture['shop']->getKey(), ['title' => 'First', 'sort_order' => 1]);
        $this->banner(BannerPosition::STORE_HERO, $fixture['merchant']->getKey(), $fixture['shop']->getKey(), ['title' => 'Future', 'starts_at' => now()->addDay()]);
        $this->banner(BannerPosition::STORE_HERO, $fixture['merchant']->getKey(), $fixture['shop']->getKey(), ['title' => 'Expired', 'ends_at' => now()->subDay()]);
        $this->banner(BannerPosition::STORE_HERO, null, null, ['title' => 'Marketplace Leak']);

        $banners = app(BannerService::class)->getStoreBanners($fixture['shop']->getKey(), BannerPosition::STORE_HERO);

        $this->assertSame([$first->getKey(), $visible->getKey()], $banners->pluck('id')->all());
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::query()->create([
            'name' => Str::headline($roleSlug).' User',
            'email' => $roleSlug.'-'.Str::random(8).'@example.test',
            'password' => Hash::make('password'),
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

    private function merchantFixture(string $email): array
    {
        $user = $this->userWithRole('merchant');
        $user->forceFill(['email' => $email])->save();

        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Merchant '.$user->getKey(),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        $root = ProductCategory::query()->create([
            'name' => 'Fashion '.$user->getKey(),
            'slug' => 'fashion-'.$user->getKey(),
            'status' => 'active',
        ]);

        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Shop '.$user->getKey(),
            'slug' => 'shop-'.$user->getKey(),
            'address_line_1' => 'Address',
            'status' => 'active',
        ]);

        return compact('user', 'merchant', 'shop');
    }

    private function banner(BannerPosition $position, ?int $merchantId, ?int $shopId, array $overrides = []): Banner
    {
        return Banner::query()->create([
            'merchant_id' => $merchantId,
            'shop_id' => $shopId,
            'position' => $position->value,
            'title' => $overrides['title'] ?? 'Banner '.Str::random(5),
            'desktop_image_path' => 'banners/test/desktop.jpg',
            'link_type' => BannerLinkType::NONE->value,
            'sort_order' => $overrides['sort_order'] ?? 0,
            'starts_at' => $overrides['starts_at'] ?? null,
            'ends_at' => $overrides['ends_at'] ?? null,
            'status' => $overrides['status'] ?? Banner::STATUS_ACTIVE,
        ]);
    }
}
