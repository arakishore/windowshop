<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\MerchantProfile;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Banner\BannerLimitService;
use App\Services\System\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class BannerLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_banner_limit_reads_system_settings_and_falls_back_safely(): void
    {
        $settings = app(SystemSettingService::class);

        $this->assertSame(3, $settings->merchantBannerLimitPerShop());

        $this->systemSetting('storefront_banner.max_per_shop', '5', SystemSetting::TYPE_INTEGER);
        $this->assertSame(5, $settings->merchantBannerLimitPerShop());

        SystemSetting::query()->where('key', 'storefront_banner.max_per_shop')->update(['value' => '99']);
        $this->assertSame(3, $settings->merchantBannerLimitPerShop());

        SystemSetting::query()->where('key', 'storefront_banner.max_per_shop')->update(['value' => 'abc']);
        $this->assertSame(3, $settings->merchantBannerLimitPerShop());

        SystemSetting::query()->where('key', 'storefront_banner.max_per_shop')->update(['value' => '4', 'status' => SystemSetting::STATUS_INACTIVE]);
        $this->assertSame(3, $settings->merchantBannerLimitPerShop());
    }

    public function test_slot_limit_counts_all_non_soft_deleted_shop_banners(): void
    {
        $fixture = $this->merchantFixture();
        $service = app(BannerLimitService::class);

        $this->systemSetting('storefront_banner.max_per_shop', '3', SystemSetting::TYPE_INTEGER);
        $this->banner($fixture['merchant'], $fixture['shop'], ['status' => Banner::STATUS_ACTIVE]);
        $this->banner($fixture['merchant'], $fixture['shop'], ['status' => Banner::STATUS_INACTIVE]);
        $deleted = $this->banner($fixture['merchant'], $fixture['shop'], ['status' => Banner::STATUS_ACTIVE]);
        $deleted->delete();

        $this->assertSame(2, $service->usedSlots($fixture['merchant'], $fixture['shop']));
        $this->assertSame(1, $service->remainingSlots($fixture['merchant'], $fixture['shop']));
        $this->assertTrue($service->canCreate($fixture['merchant'], $fixture['shop']));

        $this->banner($fixture['merchant'], $fixture['shop'], ['starts_at' => now()->addDay()]);

        $this->assertFalse($service->canCreate($fixture['merchant'], $fixture['shop']));
    }

    private function systemSetting(string $key, string $value, string $type): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            [
                'uuid' => (string) Str::uuid(),
                'value' => $value,
                'value_type' => $type,
                'status' => SystemSetting::STATUS_ACTIVE,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function merchantFixture(): array
    {
        $user = User::query()->create([
            'name' => 'Merchant User',
            'email' => 'merchant-'.Str::random(8).'@example.test',
            'password' => Hash::make('password'),
        ]);

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

    private function banner(MerchantProfile $merchant, Shop $shop, array $overrides = []): Banner
    {
        return Banner::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'position' => 'store_hero',
            'title' => 'Banner '.Str::random(5),
            'desktop_image_path' => 'banners/test/desktop.jpg',
            'link_type' => 'none',
            'sort_order' => 0,
            'starts_at' => $overrides['starts_at'] ?? null,
            'ends_at' => $overrides['ends_at'] ?? null,
            'status' => $overrides['status'] ?? Banner::STATUS_ACTIVE,
        ]);
    }
}
