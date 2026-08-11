<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Marketplace\MarketplaceLogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminMarketplaceLogoSettingsTest extends TestCase
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

    public function test_admin_settings_page_shows_marketplace_logo_management(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Marketplace')
            ->assertSee('Marketplace Logo')
            ->assertSee('Upload / Change Logo')
            ->assertSee('assets/admin/images/logov2.png');
    }

    public function test_admin_can_upload_marketplace_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'marketplace_logo' => UploadedFile::fake()->image('logo.png', 400, 120),
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Admin settings updated successfully.');

        $path = SystemSetting::query()->where('key', MarketplaceLogoService::SETTING_KEY)->value('value');

        $this->assertIsString($path);
        $this->assertStringStartsWith('marketplace/logo/', $path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_admin_can_replace_marketplace_logo_and_old_managed_file_is_deleted(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'marketplace_logo' => UploadedFile::fake()->image('old.jpg', 400, 120),
            ]));

        $oldPath = SystemSetting::query()->where('key', MarketplaceLogoService::SETTING_KEY)->value('value');

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'marketplace_logo' => UploadedFile::fake()->image('new.webp', 400, 120),
            ]))
            ->assertRedirect();

        $newPath = SystemSetting::query()->where('key', MarketplaceLogoService::SETTING_KEY)->value('value');

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_admin_can_remove_marketplace_logo_and_restore_default(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'marketplace_logo' => UploadedFile::fake()->image('logo.jpeg', 400, 120),
            ]));

        $uploadedPath = SystemSetting::query()->where('key', MarketplaceLogoService::SETTING_KEY)->value('value');

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'remove_marketplace_logo' => '1',
            ]))
            ->assertRedirect();

        $this->assertSame(
            MarketplaceLogoService::DEFAULT_LOGO_PATH,
            SystemSetting::query()->where('key', MarketplaceLogoService::SETTING_KEY)->value('value'),
        );
        Storage::disk('public')->assertMissing($uploadedPath);
    }

    public function test_marketplace_logo_rejects_invalid_type_and_oversized_file(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->from(route('admin.settings.edit'))
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'marketplace_logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            ]))
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasErrors('marketplace_logo');

        $this->actingAs($admin)
            ->from(route('admin.settings.edit'))
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'marketplace_logo' => UploadedFile::fake()->image('huge.png', 400, 120)->size(2049),
            ]))
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasErrors('marketplace_logo');
    }

    public function test_non_admin_cannot_update_marketplace_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole('merchant'))
            ->post(route('admin.settings.update'), $this->settingsPayload([
                'marketplace_logo' => UploadedFile::fake()->image('logo.png', 400, 120),
            ]))
            ->assertForbidden();
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
