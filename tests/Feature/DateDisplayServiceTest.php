<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Services\Admin\AdminSettingsService;
use App\Services\DateTime\DateDisplayService;
use Carbon\CarbonImmutable;
use Database\Seeders\AdminSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class DateDisplayServiceTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSettingsSeeder::class);
        $this->app->forgetInstance(DateDisplayService::class);
    }

    public function test_utc_timestamps_are_displayed_in_configured_timezone(): void
    {
        $timestamp = CarbonImmutable::parse('2026-08-01 13:05:00', 'UTC');

        $this->assertSame('01-08-2026 06:35 PM', app_datetime($timestamp));
        $this->assertSame('06:35 PM', app_time($timestamp));
    }

    public function test_custom_date_and_time_formats_are_used(): void
    {
        $this->settings()->set('regional', 'date_format', 'd/m/Y');
        $this->settings()->set('regional', 'time_format', 'H:i');
        $this->app->forgetInstance(DateDisplayService::class);

        $timestamp = CarbonImmutable::parse('2026-01-31 08:35:00', 'UTC');

        $this->assertSame('31/01/2026 14:05', app_datetime($timestamp));
    }

    public function test_null_invalid_and_date_only_values_are_handled_safely(): void
    {
        $this->settings()->set('regional', 'timezone', 'Pacific/Midway');
        $this->app->forgetInstance(DateDisplayService::class);

        $this->assertSame('N/A', app_datetime(null, 'N/A'));
        $this->assertSame('N/A', app_datetime('not-a-date', 'N/A'));
        $this->assertSame('01-08-2026', app_date('2026-08-01'));
        $this->assertSame('01-08-2026', app_date(CarbonImmutable::parse('2026-08-01 00:00:00', 'UTC')));
        $this->assertSame('N/A', app_time('2026-08-01', 'N/A'));
    }

    public function test_invalid_saved_timezone_falls_back_without_breaking_views(): void
    {
        AdminSetting::query()
            ->where('group', 'regional')
            ->where('setting_key', 'timezone')
            ->update(['setting_value' => 'Bad/Timezone']);
        $this->app->forgetInstance(DateDisplayService::class);

        $timestamp = CarbonImmutable::parse('2026-08-01 13:05:00', 'UTC');

        $this->assertSame('01-08-2026 01:05 PM', app_datetime($timestamp));
    }

    public function test_settings_are_cached_for_multiple_display_calls(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        app_datetime(CarbonImmutable::parse('2026-08-01 13:05:00', 'UTC'));
        app_datetime(CarbonImmutable::parse('2026-08-02 13:05:00', 'UTC'));
        app_time(CarbonImmutable::parse('2026-08-03 13:05:00', 'UTC'));
        app_date('2026-08-04');

        $settingsQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'admin_settings'))
            ->count();

        DB::disableQueryLog();

        $this->assertSame(1, $settingsQueries);
    }

    public function test_receipt_and_order_views_use_the_global_formatter(): void
    {
        $this->assertStringContainsString(
            'app_datetime($order->created_at)',
            file_get_contents(resource_path('views/merchant/pos/receipt.blade.php')),
        );
        $this->assertStringContainsString(
            'app_datetime($order->created_at)',
            file_get_contents(resource_path('views/merchant/sales/show.blade.php')),
        );
    }

    private function settings(): AdminSettingsService
    {
        return app(AdminSettingsService::class);
    }
}
