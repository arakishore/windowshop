<?php

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Enums\BannerTemplateAvailability;
use App\Enums\BannerTemplateCategory;
use App\Models\Banner;
use App\Models\BannerTemplate;
use App\Models\MerchantProfile;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class BannerTemplateActivationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_admin_can_create_marketplace_banner_from_template(): void
    {
        $admin = $this->userWithRole('admin');
        $template = $this->template([
            'availability' => BannerTemplateAvailability::ADMIN->value,
            'default_position' => BannerPosition::HOMEPAGE_HERO->value,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.banners.store'), [
                'source_type' => 'template',
                'banner_template_uuid' => $template->uuid,
                'owner_type' => 'marketplace',
                'position' => BannerPosition::HOMEPAGE_HERO->value,
                'title' => '',
                'link_type' => 'none',
                'status' => Banner::STATUS_INACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('banners', [
            'source_type' => 'template',
            'banner_template_id' => $template->getKey(),
            'merchant_id' => null,
            'shop_id' => null,
            'title' => $template->default_title,
            'desktop_image_path' => $template->desktop_image_path,
        ]);
    }

    public function test_merchant_library_filters_templates_and_activation_enforces_limit(): void
    {
        $fixture = $this->merchantFixture();
        $merchantTemplate = $this->template(['name' => 'Merchant Hero']);
        $this->template([
            'name' => 'Admin Only',
            'availability' => BannerTemplateAvailability::ADMIN->value,
            'default_position' => BannerPosition::HOMEPAGE_HERO->value,
        ]);
        $inactive = $this->template(['name' => 'Inactive Template', 'status' => BannerTemplate::STATUS_INACTIVE]);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->get(route('merchant.banner-library.index'))
            ->assertOk()
            ->assertSee('Merchant Hero')
            ->assertDontSee('Admin Only')
            ->assertDontSee('Inactive Template');

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.banners.store'), [
                'source_type' => 'template',
                'banner_template_uuid' => $merchantTemplate->uuid,
                'position' => BannerPosition::STORE_HERO->value,
                'title' => '',
                'link_type' => 'none',
                'status' => Banner::STATUS_INACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('banners', [
            'source_type' => 'template',
            'banner_template_id' => $merchantTemplate->getKey(),
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'title' => $merchantTemplate->default_title,
        ]);

        DB::table('system_settings')->insert([
            'uuid' => (string) Str::uuid(),
            'key' => 'storefront_banner.max_per_shop',
            'value' => '1',
            'value_type' => 'integer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.banners.store'), [
                'source_type' => 'template',
                'banner_template_uuid' => $merchantTemplate->uuid,
                'position' => BannerPosition::STORE_HERO->value,
                'title' => 'Too Many',
                'link_type' => 'none',
                'status' => Banner::STATUS_INACTIVE,
            ])
            ->assertSessionHasErrors('banner_template_uuid');

        $inactive->delete();
        $this->actingAs($fixture['user'])
            ->withSession(['active_shop_id' => $fixture['shop']->getKey()])
            ->post(route('merchant.banners.store'), [
                'source_type' => 'template',
                'banner_template_uuid' => $inactive->uuid,
                'position' => BannerPosition::STORE_HERO->value,
                'title' => 'Deleted',
                'link_type' => 'none',
                'status' => Banner::STATUS_INACTIVE,
            ])
            ->assertSessionHasErrors('banner_template_uuid');
    }

    public function test_replace_template_reuses_banner_and_preserves_fields_by_default(): void
    {
        $admin = $this->userWithRole('admin');
        $first = $this->template(['default_title' => 'Original', 'desktop_image_path' => 'banner-templates/first/desktop.jpg']);
        $second = $this->template(['default_title' => 'Replacement', 'desktop_image_path' => 'banner-templates/second/desktop.jpg']);
        $banner = Banner::query()->create([
            'source_type' => 'template',
            'banner_template_id' => $first->getKey(),
            'position' => BannerPosition::STORE_HERO->value,
            'title' => 'Keep This',
            'desktop_image_path' => $first->desktop_image_path,
            'link_type' => 'none',
            'sort_order' => 7,
            'status' => Banner::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.banners.replace-template', $banner), [
                'banner_template_uuid' => $second->uuid,
                'apply_template_defaults' => 'images_only',
            ])
            ->assertRedirect(route('admin.banners.edit', $banner));

        $banner->refresh();

        $this->assertSame($second->getKey(), $banner->banner_template_id);
        $this->assertSame('template', $banner->source_type->value);
        $this->assertSame('Keep This', $banner->title);
        $this->assertSame(7, $banner->sort_order);
        $this->assertSame($second->desktop_image_path, $banner->desktop_image_path);
        $this->assertSame(1, Banner::query()->whereKey($banner->getKey())->count());
    }

    private function template(array $overrides = []): BannerTemplate
    {
        return BannerTemplate::query()->create([
            'code' => $overrides['code'] ?? 'template_'.strtolower(Str::random(8)),
            'category' => $overrides['category'] ?? BannerTemplateCategory::GENERAL->value,
            'name' => $overrides['name'] ?? 'Template',
            'description' => 'Reusable banner template.',
            'default_title' => $overrides['default_title'] ?? 'New Arrivals',
            'default_subtitle' => 'Fresh arrivals added every week',
            'default_button_text' => 'Shop Now',
            'desktop_image_path' => $overrides['desktop_image_path'] ?? 'banner-templates/general/desktop.jpg',
            'mobile_image_path' => $overrides['mobile_image_path'] ?? 'banner-templates/general/mobile.jpg',
            'default_position' => $overrides['default_position'] ?? BannerPosition::STORE_HERO->value,
            'availability' => $overrides['availability'] ?? BannerTemplateAvailability::BOTH->value,
            'sort_order' => $overrides['sort_order'] ?? 0,
            'status' => $overrides['status'] ?? BannerTemplate::STATUS_ACTIVE,
        ]);
    }

    private function merchantFixture(): array
    {
        $user = $this->userWithRole('merchant');
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
}
