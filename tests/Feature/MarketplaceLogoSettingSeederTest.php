<?php

namespace Tests\Feature;

use Database\Seeders\MasterData\SystemFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class MarketplaceLogoSettingSeederTest extends TestCase
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

    public function test_system_foundation_seeder_creates_default_marketplace_logo_setting(): void
    {
        $this->seed(SystemFoundationSeeder::class);

        $group = DB::table('system_setting_groups')->where('slug', 'marketplace')->first();
        $setting = DB::table('system_settings')->where('key', 'marketplace.logo')->first();

        $this->assertNotNull($group);
        $this->assertSame('Marketplace', $group->name);
        $this->assertSame(15, (int) $group->sort_order);
        $this->assertSame('active', $group->status);

        $this->assertNotNull($setting);
        $this->assertSame((int) $group->id, (int) $setting->group_id);
        $this->assertSame('Marketplace Logo', $setting->label);
        $this->assertSame('assets/admin/images/logov2.png', $setting->value);
        $this->assertSame('string', $setting->value_type);
        $this->assertSame('active', $setting->status);
    }
}
