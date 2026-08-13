<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\SystemSettingGroup;
use App\Services\Marketplace\MarketplaceLogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class StorefrontMarketplaceLogoTest extends TestCase
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

    public function test_storefront_header_uses_default_marketplace_logo_when_no_custom_logo_exists(): void
    {
        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('assets/admin/images/logov2.png', false)
            ->assertDontSee('assets/storefront/images/logo/logo.svg', false);
    }

    public function test_storefront_header_displays_uploaded_marketplace_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('marketplace/logo/custom-logo.png', 'logo');
        $this->systemSetting('marketplace/logo/custom-logo.png');

        $this->get(route('storefront.products'))
            ->assertOk()
            ->assertSee('/storage/marketplace/logo/custom-logo.png', false)
            ->assertDontSee('assets/storefront/images/logo/logo.svg', false);
    }

    public function test_storefront_header_falls_back_when_uploaded_logo_file_is_missing(): void
    {
        Storage::fake('public');
        $this->systemSetting('marketplace/logo/missing-logo.png');

        $this->get(route('storefront.cart'))
            ->assertOk()
            ->assertSee('assets/admin/images/logov2.png', false)
            ->assertDontSee('/storage/marketplace/logo/missing-logo.png', false);
    }

    public function test_storefront_routes_do_not_allow_customer_logo_updates(): void
    {
        $this->systemSetting(MarketplaceLogoService::DEFAULT_LOGO_PATH);

        $this->post(route('storefront.login'), [
            'marketplace_logo' => 'marketplace/logo/customer.png',
        ])->assertMethodNotAllowed();

        $this->assertSame(
            MarketplaceLogoService::DEFAULT_LOGO_PATH,
            SystemSetting::query()->where('key', MarketplaceLogoService::SETTING_KEY)->value('value'),
        );
    }

    private function systemSetting(string $value): void
    {
        $group = SystemSettingGroup::query()->create([
            'name' => 'Marketplace',
            'slug' => 'marketplace',
            'sort_order' => 15,
            'status' => 'active',
        ]);

        SystemSetting::query()->create([
            'group_id' => $group->getKey(),
            'key' => MarketplaceLogoService::SETTING_KEY,
            'label' => 'Marketplace Logo',
            'value' => $value,
            'value_type' => SystemSetting::TYPE_STRING,
            'is_public' => false,
            'is_encrypted' => false,
            'sort_order' => 10,
            'status' => SystemSetting::STATUS_ACTIVE,
        ]);
    }
}
