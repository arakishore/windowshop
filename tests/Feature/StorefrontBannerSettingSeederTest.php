<?php

namespace Tests\Feature;

use Database\Seeders\MasterData\StorefrontBannerSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class StorefrontBannerSettingSeederTest extends TestCase
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

    public function test_storefront_banner_setting_seeder_creates_group_and_setting(): void
    {
        $this->seed(StorefrontBannerSettingSeeder::class);

        $group = DB::table('system_setting_groups')->where('slug', 'storefront-banner')->first();
        $setting = DB::table('system_settings')->where('key', 'storefront_banner.max_per_shop')->first();

        $this->assertNotNull($group);
        $this->assertNotNull($group->uuid);
        $this->assertSame('Storefront Banner', $group->name);
        $this->assertSame('Global configuration for storefront banner behaviour.', $group->description);
        $this->assertSame(100, (int) $group->sort_order);
        $this->assertSame('active', $group->status);
        $this->assertNull($group->deleted_at);

        $this->assertNotNull($setting);
        $this->assertNotNull($setting->uuid);
        $this->assertSame((int) $group->id, (int) $setting->group_id);
        $this->assertSame('Maximum Banners Per Shop', $setting->label);
        $this->assertSame('3', $setting->value);
        $this->assertSame('integer', $setting->value_type);
        $this->assertSame('Maximum number of banner slots allowed for each merchant shop.', $setting->description);
        $this->assertSame(10, (int) $setting->sort_order);
        $this->assertSame('active', $setting->status);
        $this->assertSame(0, (int) $setting->is_public);
        $this->assertSame(0, (int) $setting->is_encrypted);
        $this->assertNull($setting->deleted_at);
    }

    public function test_storefront_banner_setting_seeder_is_idempotent_and_recovers_existing_records(): void
    {
        $this->seed(StorefrontBannerSettingSeeder::class);

        $group = DB::table('system_setting_groups')->where('slug', 'storefront-banner')->first();
        $setting = DB::table('system_settings')->where('key', 'storefront_banner.max_per_shop')->first();
        $groupUuid = $group->uuid;
        $settingUuid = $setting->uuid;

        DB::table('system_setting_groups')->where('id', $group->id)->update([
            'name' => 'Old Name',
            'status' => 'inactive',
            'deleted_at' => now(),
        ]);
        DB::table('system_settings')->where('id', $setting->id)->update([
            'value' => '9',
            'status' => 'inactive',
            'deleted_at' => now(),
        ]);

        $this->seed(StorefrontBannerSettingSeeder::class);
        $this->seed(StorefrontBannerSettingSeeder::class);

        $group = DB::table('system_setting_groups')->where('slug', 'storefront-banner')->first();
        $setting = DB::table('system_settings')->where('key', 'storefront_banner.max_per_shop')->first();

        $this->assertSame(1, DB::table('system_setting_groups')->where('slug', 'storefront-banner')->count());
        $this->assertSame(1, DB::table('system_settings')->where('key', 'storefront_banner.max_per_shop')->count());
        $this->assertSame($groupUuid, $group->uuid);
        $this->assertSame($settingUuid, $setting->uuid);
        $this->assertSame('Storefront Banner', $group->name);
        $this->assertSame('3', $setting->value);
        $this->assertSame('active', $group->status);
        $this->assertSame('active', $setting->status);
        $this->assertNull($group->deleted_at);
        $this->assertNull($setting->deleted_at);
    }
}
