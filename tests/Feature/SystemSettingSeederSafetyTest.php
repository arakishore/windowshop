<?php

namespace Tests\Feature;

use Database\Seeders\MasterData\SystemFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function test_fresh_install_has_only_canonical_global_settings_foundation(): void
    {
        $this->assertFalse(Schema::hasTable('admin_settings'));
        $this->assertTrue(Schema::hasTable('system_settings'));

        $this->seed(SystemFoundationSeeder::class);

        $expected = [
            'default_currency' => 'INR',
            'default_timezone' => 'Asia/Kolkata',
            'regional.date_format' => 'd-m-Y',
            'regional.time_format' => 'h:i A',
            'regional.financial_year_start_month' => '4',
            'currency.symbol' => '₹',
            'currency.decimal_places' => '2',
            'currency.thousands_separator' => ',',
            'currency.decimal_separator' => '.',
            'currency.symbol_position' => 'before',
        ];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, DB::table('system_settings')->where('key', $key)->value('value'));
            $this->assertSame(1, DB::table('system_settings')->where('key', $key)->count());
        }
    }

    public function test_system_foundation_seeder_preserves_all_configured_regional_and_currency_values(): void
    {
        $this->seed(SystemFoundationSeeder::class);

        $configured = [
            'default_currency' => 'USD',
            'default_timezone' => 'UTC',
            'regional.date_format' => 'Y-m-d',
            'regional.time_format' => 'H:i',
            'regional.financial_year_start_month' => '1',
            'currency.symbol' => '$',
            'currency.decimal_places' => '0',
            'currency.thousands_separator' => ' ',
            'currency.decimal_separator' => ',',
            'currency.symbol_position' => 'after',
        ];

        foreach ($configured as $key => $value) {
            DB::table('system_settings')->where('key', $key)->update(['value' => $value]);
        }

        $this->seed(SystemFoundationSeeder::class);

        foreach ($configured as $key => $value) {
            $this->assertSame($value, DB::table('system_settings')->where('key', $key)->value('value'));
            $this->assertSame(1, DB::table('system_settings')->where('key', $key)->count());
        }
    }
}
