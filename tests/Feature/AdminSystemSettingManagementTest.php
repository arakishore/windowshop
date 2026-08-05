<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\MasterData\StorefrontBannerSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminSystemSettingManagementTest extends TestCase
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

    public function test_admin_can_view_storefront_banner_system_setting(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seed(StorefrontBannerSettingSeeder::class);

        $this->actingAs($admin)
            ->get(route('admin.system-settings.index', ['search' => 'storefront_banner.max_per_shop']))
            ->assertOk()
            ->assertSee('System Setting List')
            ->assertSee('Storefront Banner')
            ->assertSee('storefront_banner.max_per_shop')
            ->assertSee('Maximum Banners Per Shop')
            ->assertSee('3');
    }

    public function test_system_setting_identity_fields_are_read_only_on_edit_form(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seed(StorefrontBannerSettingSeeder::class);
        $setting = SystemSetting::query()->where('key', 'storefront_banner.max_per_shop')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.system-settings.edit', $setting))
            ->assertOk()
            ->assertSee('storefront_banner.max_per_shop')
            ->assertSee('Storefront Banner')
            ->assertSee('Maximum Banners Per Shop')
            ->assertSee('Integer')
            ->assertDontSee('id="group_id"', false)
            ->assertDontSee('id="label"', false)
            ->assertDontSee('id="value_type"', false);
    }

    public function test_admin_can_update_system_setting_value(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seed(StorefrontBannerSettingSeeder::class);
        $setting = SystemSetting::query()->where('key', 'storefront_banner.max_per_shop')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.system-settings.update', $setting), [
                'group_id' => $setting->group_id,
                'label' => 'Maximum Banners Per Shop',
                'value' => '4',
                'value_type' => 'integer',
                'description' => 'Maximum number of banner slots allowed for each merchant shop.',
                'sort_order' => 10,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.system-settings.edit', $setting));

        $this->assertDatabaseHas('system_settings', [
            'id' => $setting->getKey(),
            'value' => '4',
            'value_type' => 'integer',
            'updated_by' => $admin->getKey(),
        ]);
    }

    public function test_integer_system_setting_rejects_non_integer_value(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seed(StorefrontBannerSettingSeeder::class);
        $setting = SystemSetting::query()->where('key', 'storefront_banner.max_per_shop')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.system-settings.edit', $setting))
            ->put(route('admin.system-settings.update', $setting), [
                'group_id' => $setting->group_id,
                'label' => 'Maximum Banners Per Shop',
                'value' => 'not-a-number',
                'value_type' => 'integer',
                'description' => 'Maximum number of banner slots allowed for each merchant shop.',
                'sort_order' => 10,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.system-settings.edit', $setting))
            ->assertSessionHasErrors('value');
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::query()->create([
            'name' => Str::headline($roleSlug).' User',
            'email' => $roleSlug.'-'.Str::random(8).'@example.test',
            'password' => Hash::make('password'),
        ]);

        $roleId = DB::table('auth_roles')->where('slug', $roleSlug)->value('id')
            ?? DB::table('auth_roles')->insertGetId([
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
