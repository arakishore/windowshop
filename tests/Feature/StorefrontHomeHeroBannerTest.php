<?php

namespace Tests\Feature;

use App\Enums\BannerLinkType;
use App\Enums\BannerPosition;
use App\Models\Banner;
use App\Models\MerchantProfile;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontHomeHeroBannerTest extends TestCase
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

    public function test_active_hero_banner_appears_on_homepage(): void
    {
        $this->banner(['title' => 'Dynamic Hero Banner']);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Dynamic Hero Banner')
            ->assertSee('/storage/banners/test/desktop.jpg', false)
            ->assertDontSee('Elevate Your Everyday Style');
    }

    public function test_inactive_hero_banner_does_not_appear(): void
    {
        $this->banner([
            'title' => 'Inactive Hero Banner',
            'status' => Banner::STATUS_INACTIVE,
        ]);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertDontSee('Inactive Hero Banner')
            ->assertSee('Elevate Your Everyday Style');
    }

    public function test_future_hero_banner_does_not_appear_before_start_time(): void
    {
        $this->banner([
            'title' => 'Future Hero Banner',
            'starts_at' => now()->addDay(),
        ]);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertDontSee('Future Hero Banner')
            ->assertSee('Elevate Your Everyday Style');
    }

    public function test_expired_hero_banner_does_not_appear_after_end_time(): void
    {
        $this->banner([
            'title' => 'Expired Hero Banner',
            'ends_at' => now()->subDay(),
        ]);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertDontSee('Expired Hero Banner')
            ->assertSee('Elevate Your Everyday Style');
    }

    public function test_active_hero_banner_with_no_schedule_appears(): void
    {
        $this->banner([
            'title' => 'Always On Hero Banner',
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Always On Hero Banner');
    }

    public function test_hero_banners_follow_sort_order(): void
    {
        $this->banner(['title' => 'Second Hero Banner', 'sort_order' => 2]);
        $this->banner(['title' => 'First Hero Banner', 'sort_order' => 1]);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSeeInOrder(['First Hero Banner', 'Second Hero Banner']);
    }

    public function test_only_homepage_hero_position_banners_are_shown(): void
    {
        $this->banner(['title' => 'Homepage Middle Banner', 'position' => BannerPosition::HOMEPAGE_MIDDLE]);
        $this->banner(['title' => 'Homepage Hero Banner']);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Homepage Hero Banner')
            ->assertDontSee('Homepage Middle Banner');
    }

    public function test_shop_specific_hero_banner_does_not_appear_on_global_homepage(): void
    {
        $shop = $this->shop();
        $this->banner([
            'title' => 'Shop Specific Homepage Hero',
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
        ]);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertDontSee('Shop Specific Homepage Hero')
            ->assertSee('Elevate Your Everyday Style');
    }

    public function test_mobile_image_falls_back_to_desktop_image_when_missing(): void
    {
        $this->banner([
            'title' => 'Desktop Fallback Hero',
            'desktop_image_path' => 'banners/test/desktop-only.jpg',
            'mobile_image_path' => null,
        ]);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('srcset="http://localhost/storage/banners/test/desktop-only.jpg"', false)
            ->assertSee('src="http://localhost/storage/banners/test/desktop-only.jpg"', false);
    }

    public function test_homepage_works_when_no_hero_banner_exists(): void
    {
        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Elevate Your Everyday Style')
            ->assertSee('assets/storefront/images/slider/slider-1.jpg', false);
    }

    public function test_homepage_hero_banner_limit_is_respected(): void
    {
        for ($i = 1; $i <= BannerPosition::HOMEPAGE_HERO->maxBanners() + 1; $i++) {
            $this->banner([
                'title' => 'Limited Hero Banner '.$i,
                'sort_order' => $i,
            ]);
        }

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Limited Hero Banner 1')
            ->assertSee('Limited Hero Banner 5')
            ->assertDontSee('Limited Hero Banner 6');
    }

    private function banner(array $overrides = []): Banner
    {
        $position = $overrides['position'] ?? BannerPosition::HOMEPAGE_HERO;

        return Banner::query()->create([
            'merchant_id' => $overrides['merchant_id'] ?? null,
            'shop_id' => $overrides['shop_id'] ?? null,
            'position' => $position instanceof BannerPosition ? $position->value : $position,
            'title' => $overrides['title'] ?? 'Hero Banner '.Str::random(5),
            'subtitle' => $overrides['subtitle'] ?? 'Seasonal picks',
            'description' => $overrides['description'] ?? 'Fresh local offers for the homepage.',
            'desktop_image_path' => $overrides['desktop_image_path'] ?? 'banners/test/desktop.jpg',
            'mobile_image_path' => array_key_exists('mobile_image_path', $overrides) ? $overrides['mobile_image_path'] : 'banners/test/mobile.jpg',
            'link_type' => $overrides['link_type'] ?? BannerLinkType::NONE->value,
            'link_value' => $overrides['link_value'] ?? null,
            'open_in_new_tab' => $overrides['open_in_new_tab'] ?? false,
            'button_text' => $overrides['button_text'] ?? null,
            'sort_order' => $overrides['sort_order'] ?? 0,
            'starts_at' => $overrides['starts_at'] ?? null,
            'ends_at' => $overrides['ends_at'] ?? null,
            'status' => $overrides['status'] ?? Banner::STATUS_ACTIVE,
        ]);
    }

    private function shop(): Shop
    {
        $user = User::query()->create([
            'name' => 'Merchant User',
            'email' => 'merchant-'.Str::random(8).'@example.test',
            'password' => 'password',
        ]);

        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Merchant '.$user->getKey(),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        $category = ProductCategory::query()->create([
            'name' => 'Fashion '.$user->getKey(),
            'slug' => 'fashion-'.$user->getKey(),
            'status' => 'active',
        ]);

        return Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $category->getKey(),
            'name' => 'Shop '.$user->getKey(),
            'slug' => 'shop-'.$user->getKey(),
            'address_line_1' => 'Address',
            'status' => 'active',
        ]);
    }
}
