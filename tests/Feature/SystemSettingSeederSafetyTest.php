<?php

namespace Tests\Feature;

use Database\Seeders\MasterData\SystemFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class SystemSettingSeederSafetyTest extends TestCase
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

    public function test_system_foundation_seeder_preserves_existing_configured_values(): void
    {
        $this->seed(SystemFoundationSeeder::class);
        DB::table('system_settings')->where('key', 'default_currency')->update(['value' => 'USD']);
        DB::table('system_settings')->where('key', 'marketplace_name')->update(['value' => 'Configured Market']);

        $this->seed(SystemFoundationSeeder::class);

        $this->assertSame('USD', DB::table('system_settings')->where('key', 'default_currency')->value('value'));
        $this->assertSame('Configured Market', DB::table('system_settings')->where('key', 'marketplace_name')->value('value'));
        $this->assertSame(1, DB::table('system_settings')->where('key', 'default_currency')->count());
    }
}
