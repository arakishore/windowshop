<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\User;
use App\Services\Admin\AdminSettingsInitializer;
use App\Services\Admin\AdminSettingsService;
use App\Support\CurrencyCatalog;
use Database\Seeders\AdminSettingsSeeder;
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
            $pdo->sqliteCreateCollation(
                'utf8mb4_unicode_ci',
                fn (string $left, string $right): int => strcmp($left, $right),
            );
        }
    }

    public function test_admin_settings_seeder_creates_global_defaults(): void
    {
        $this->seed(AdminSettingsSeeder::class);

        $expectedCount = collect($this->initializer()->defaults())->sum(fn (array $settings): int => count($settings));

        $this->assertSame($expectedCount, AdminSetting::query()->count());
        $this->assertSame('Asia/Kolkata', $this->settings()->get('regional', 'timezone'));
        $this->assertSame('d-m-Y', $this->settings()->get('regional', 'date_format'));
        $this->assertSame('h:i A', $this->settings()->get('regional', 'time_format'));
        $this->assertSame(4, $this->settings()->get('regional', 'financial_year_start_month'));
        $this->assertSame('INR', $this->settings()->get('currency', 'base_currency'));
        $this->assertSame('₹', $this->settings()->get('currency', 'symbol'));
        $this->assertSame(2, $this->settings()->get('currency', 'decimal_places'));
        $this->assertSame(',', $this->settings()->get('currency', 'thousands_separator'));
        $this->assertSame('.', $this->settings()->get('currency', 'decimal_separator'));
        $this->assertSame('before', $this->settings()->get('currency', 'symbol_position'));
    }

    public function test_currency_catalog_loads_reference_currencies(): void
    {
        $currencies = app(CurrencyCatalog::class)->all();

        $this->assertCount(49, $currencies);
        $this->assertSame('Indian Rupee', app(CurrencyCatalog::class)->find('INR')['name']);
        $this->assertSame(0, app(CurrencyCatalog::class)->find('JPY')['decimals']);
        $this->assertSame(3, app(CurrencyCatalog::class)->find('KWD')['decimals']);
    }

    public function test_admin_can_view_and_update_global_settings(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Admin Settings')
            ->assertSee('Regional')
            ->assertSee('Currency')
            ->assertSee('Asia/Kolkata')
            ->assertSee('Pacific/Midway')
            ->assertSee('INR - Indian Rupee')
            ->assertSee('USD - United States Dollar')
            ->assertSee('JPY - Japanese Yen')
            ->assertSee('₹1,234,567.50')
            ->assertDontSee('regional.timezone')
            ->assertDontSee('currency.base_currency');

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'settings' => [
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
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Admin settings updated successfully.');

        $this->assertSame('d/m/Y', $this->settings()->get('regional', 'date_format'));
        $this->assertSame('H:i', $this->settings()->get('regional', 'time_format'));
        $this->assertSame(1, $this->settings()->get('regional', 'financial_year_start_month'));
        $this->assertSame('USD', $this->settings()->get('currency', 'base_currency'));
        $this->assertSame('$', $this->settings()->get('currency', 'symbol'));
        $this->assertSame(2, $this->settings()->get('currency', 'decimal_places'));
        $this->assertSame('before', $this->settings()->get('currency', 'symbol_position'));
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

    private function settings(): AdminSettingsService
    {
        return app(AdminSettingsService::class);
    }

    private function initializer(): AdminSettingsInitializer
    {
        return app(AdminSettingsInitializer::class);
    }
}
