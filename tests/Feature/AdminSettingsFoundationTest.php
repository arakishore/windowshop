<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\System\SystemSettingService;
use App\Support\CurrencyCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminSettingsFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_visiting_admin_settings_does_not_initialize_legacy_defaults(): void
    {
        $admin = $this->adminUser();

        $this->assertDatabaseCount('admin_settings', 0);
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk();
        $this->assertDatabaseCount('admin_settings', 0);
    }

    public function test_currency_catalog_loads_reference_currencies(): void
    {
        $currencies = app(CurrencyCatalog::class)->all();

        $this->assertCount(49, $currencies);
        $this->assertSame('Indian Rupee', app(CurrencyCatalog::class)->find('INR')['name']);
        $this->assertSame(0, app(CurrencyCatalog::class)->find('JPY')['decimals']);
        $this->assertSame(3, app(CurrencyCatalog::class)->find('KWD')['decimals']);
    }

    public function test_admin_can_view_and_update_canonical_global_settings_without_legacy_writes(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Admin Settings')
            ->assertSee('Regional')
            ->assertSee('Currency')
            ->assertSee('Storefront Banner')
            ->assertSee('Maximum Banners Per Shop')
            ->assertSee('Asia/Kolkata')
            ->assertSee('Pacific/Midway')
            ->assertSee('INR - Indian Rupee')
            ->assertSee('USD - United States Dollar')
            ->assertSee('JPY - Japanese Yen')
            ->assertSee('₹1,234,567.50')
            ->assertDontSee('regional.timezone')
            ->assertDontSee('currency.base_currency');

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success', 'Admin settings updated successfully.');

        $regional = $this->settings()->regionalConfig();
        $currency = $this->settings()->currencyConfig();
        $this->assertSame('d/m/Y', $regional['date_format']);
        $this->assertSame('H:i', $regional['time_format']);
        $this->assertSame(1, $regional['financial_year_start_month']);
        $this->assertSame('USD', $currency['currency']);
        $this->assertSame('$', $currency['symbol']);
        $this->assertSame(2, $currency['decimal_places']);
        $this->assertSame('before', $currency['symbol_position']);
        $this->assertSame('USD', SystemSetting::query()->where('key', 'default_currency')->value('value'));
        $this->assertSame('Asia/Kolkata', SystemSetting::query()->where('key', 'default_timezone')->value('value'));
        $this->assertSame('4', SystemSetting::query()->where('key', 'storefront_banner.max_per_shop')->value('value'));
        $this->assertDatabaseCount('admin_settings', 0);
    }

    public function test_admin_settings_rejects_invalid_storefront_banner_limit(): void
    {
        $payload = $this->payload();
        $payload['settings']['storefront_banner']['max_per_shop'] = '0';

        $this->actingAs($this->adminUser())
            ->put(route('admin.settings.update'), $payload)
            ->assertSessionHasErrors('settings.storefront_banner.max_per_shop');
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['settings' => [
            'regional' => [
                'timezone' => 'Asia/Kolkata',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'financial_year_start_month' => '1',
            ],
            'currency' => [
                'base_currency' => 'USD',
                'symbol' => '$',
                'decimal_places' => '2',
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'symbol_position' => 'before',
            ],
            'storefront_banner' => ['max_per_shop' => '4'],
        ]];
    }

    private function adminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Settings User',
            'email' => 'admin-settings-'.Str::random(6).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $roleId = DB::table('auth_roles')->insertGetId([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
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

    private function settings(): SystemSettingService
    {
        return app(SystemSettingService::class);
    }
}
