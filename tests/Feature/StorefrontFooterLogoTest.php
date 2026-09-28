<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\SystemSettingGroup;
use App\Models\User;
use App\Services\Marketplace\MarketplaceLogoService;
use Database\Seeders\MasterData\MarketplaceFooterLogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontFooterLogoTest extends TestCase
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

    public function test_storefront_footer_uses_configured_footer_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('marketplace/logo/footer-white.png', 'logo');
        $this->logoSetting(MarketplaceLogoService::SETTING_KEY, MarketplaceLogoService::DEFAULT_LOGO_PATH);
        $this->logoSetting(MarketplaceLogoService::FOOTER_SETTING_KEY, 'marketplace/logo/footer-white.png');

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('/storage/marketplace/logo/footer-white.png', false);
    }

    public function test_storefront_footer_falls_back_to_marketplace_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('marketplace/logo/header-logo.png', 'logo');
        $this->logoSetting(MarketplaceLogoService::SETTING_KEY, 'marketplace/logo/header-logo.png');
        $this->logoSetting(MarketplaceLogoService::FOOTER_SETTING_KEY, null);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('/storage/marketplace/logo/header-logo.png', false);
    }

    public function test_storefront_footer_falls_back_to_default_logo_when_nothing_configured(): void
    {
        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('assets/admin/images/logov2.png', false);
    }

    public function test_storefront_header_remains_on_marketplace_logo_when_footer_logo_configured(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('marketplace/logo/header-logo.png', 'logo');
        Storage::disk('public')->put('marketplace/logo/footer-white.png', 'logo');
        $this->logoSetting(MarketplaceLogoService::SETTING_KEY, 'marketplace/logo/header-logo.png');
        $this->logoSetting(MarketplaceLogoService::FOOTER_SETTING_KEY, 'marketplace/logo/footer-white.png');

        $response = $this->get(route('storefront.home'))->assertOk();

        // Header partial keeps using the header logo URL.
        $response->assertSee('/storage/marketplace/logo/header-logo.png', false);
        // Footer partial uses the dedicated footer logo URL.
        $response->assertSee('/storage/marketplace/logo/footer-white.png', false);
    }

    public function test_admin_settings_page_shows_footer_logo_management(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Footer Logo')
            ->assertSee('Optional logo used in the storefront footer. A light/white logo is recommended for dark footer backgrounds.')
            ->assertSee('Upload / Change Footer Logo');
    }

    public function test_footer_logo_setting_is_listed_under_marketplace_system_settings(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seed(MarketplaceFooterLogoSeeder::class);

        $this->actingAs($admin)
            ->get(route('admin.system-settings.index', ['search' => 'marketplace.footer_logo']))
            ->assertOk()
            ->assertSee('marketplace.footer_logo')
            ->assertSee('Footer Logo');
    }

    public function test_admin_can_upload_footer_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'footer_logo' => UploadedFile::fake()->image('footer.png', 400, 120),
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Admin settings updated successfully.');

        $path = SystemSetting::query()->where('key', MarketplaceLogoService::FOOTER_SETTING_KEY)->value('value');

        $this->assertIsString($path);
        $this->assertStringStartsWith('marketplace/logo/', $path);
        $this->assertStringStartsWith('marketplace/logo/marketplace-footer-logo-', $path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_admin_can_replace_footer_logo_and_old_managed_file_is_deleted(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'footer_logo' => UploadedFile::fake()->image('old.png', 400, 120),
            ]));

        $oldPath = SystemSetting::query()->where('key', MarketplaceLogoService::FOOTER_SETTING_KEY)->value('value');

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'footer_logo' => UploadedFile::fake()->image('new.webp', 400, 120),
            ]))
            ->assertRedirect();

        $newPath = SystemSetting::query()->where('key', MarketplaceLogoService::FOOTER_SETTING_KEY)->value('value');

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_admin_can_remove_footer_logo_and_fallback_returns_to_marketplace_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('marketplace/logo/header-logo.png', 'logo');
        $this->logoSetting(MarketplaceLogoService::SETTING_KEY, 'marketplace/logo/header-logo.png');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'footer_logo' => UploadedFile::fake()->image('footer.png', 400, 120),
            ]));

        $uploadedPath = SystemSetting::query()->where('key', MarketplaceLogoService::FOOTER_SETTING_KEY)->value('value');

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'remove_footer_logo' => '1',
            ]))
            ->assertRedirect();

        $this->assertNull(
            SystemSetting::query()->where('key', MarketplaceLogoService::FOOTER_SETTING_KEY)->value('value'),
        );
        Storage::disk('public')->assertMissing($uploadedPath);

        $this->assertSame(
            asset('storage/marketplace/logo/header-logo.png'),
            app(MarketplaceLogoService::class)->footerUrl(),
        );
    }

    public function test_footer_logo_rejects_invalid_type_and_oversized_file(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->from(route('admin.settings.edit'))
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'footer_logo' => UploadedFile::fake()->create('footer.svg', 10, 'image/svg+xml'),
            ]))
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasErrors('footer_logo');

        $this->actingAs($admin)
            ->from(route('admin.settings.edit'))
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'footer_logo' => UploadedFile::fake()->image('huge.png', 400, 120)->size(2049),
            ]))
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasErrors('footer_logo');
    }

    public function test_footer_logo_seeder_preserves_configured_value(): void
    {
        $this->seed(MarketplaceFooterLogoSeeder::class);
        $setting = SystemSetting::query()->where('key', MarketplaceLogoService::FOOTER_SETTING_KEY)->firstOrFail();
        $setting->update(['value' => 'marketplace/logo/keep-me.png']);

        $this->seed(MarketplaceFooterLogoSeeder::class);

        $this->assertSame('marketplace/logo/keep-me.png', $setting->fresh()->value);
        $this->assertSame('Footer Logo', $setting->fresh()->label);
        $this->assertSame('marketplace', $setting->fresh()->group->slug);
    }

    private function logoSetting(string $key, ?string $value): void
    {
        $group = SystemSettingGroup::query()->firstOrCreate(
            ['slug' => 'marketplace'],
            ['name' => 'Marketplace', 'sort_order' => 15, 'status' => 'active'],
        );

        SystemSetting::query()->create([
            'group_id' => $group->getKey(),
            'key' => $key,
            'label' => $key,
            'value' => $value,
            'value_type' => SystemSetting::TYPE_STRING,
            'is_public' => false,
            'is_encrypted' => false,
            'sort_order' => 10,
            'status' => SystemSetting::STATUS_ACTIVE,
        ]);
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            '_method' => 'PUT',
            'settings' => [
                'regional' => [
                    'timezone' => 'Asia/Kolkata',
                    'date_format' => 'd-m-Y',
                    'time_format' => 'h:i A',
                    'financial_year_start_month' => '4',
                ],
                'currency' => [
                    'base_currency' => 'INR',
                    'symbol' => 'Rs',
                    'decimal_places' => '2',
                    'thousands_separator' => ',',
                    'decimal_separator' => '.',
                    'symbol_position' => 'before',
                ],
                'storefront_banner' => [
                    'max_per_shop' => '3',
                ],
            ],
        ], $overrides);
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::query()->create([
            'name' => Str::headline($roleSlug).' User',
            'email' => $roleSlug.'-'.Str::random(8).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $roleId = DB::table('auth_roles')->insertGetId([
            'name' => Str::headline($roleSlug),
            'slug' => $roleSlug,
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
}
