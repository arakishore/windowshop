<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\System\SystemSettingService;
use Database\Seeders\MasterData\PublicContactSettingSeeder;
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

    public function test_admin_can_update_public_contact_settings_with_key_specific_validation(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seed(PublicContactSettingSeeder::class);
        $email = SystemSetting::query()->where('key', 'contact.support_email')->firstOrFail();
        $facebook = SystemSetting::query()->where('key', 'social.facebook')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.system-settings.index', ['search' => 'contact.']))
            ->assertOk()->assertSee('contact.support_email')->assertSee('contact.phone');

        $this->actingAs($admin)->put(route('admin.system-settings.update', $email), $this->payload($email, 'not-an-email'))
            ->assertSessionHasErrors('value');
        $this->actingAs($admin)->put(route('admin.system-settings.update', $facebook), $this->payload($facebook, 'javascript:alert(1)'))
            ->assertSessionHasErrors('value');

        $this->actingAs($admin)->put(route('admin.system-settings.update', $email), $this->payload($email, 'support@windowshop.test'))
            ->assertRedirect(route('admin.system-settings.edit', $email));
        $this->actingAs($admin)->put(route('admin.system-settings.update', $facebook), $this->payload($facebook, 'https://facebook.com/windowshop'))
            ->assertRedirect(route('admin.system-settings.edit', $facebook));

        $this->assertSame('support@windowshop.test', $email->fresh()->value);
        $this->assertSame('https://facebook.com/windowshop', $facebook->fresh()->value);
    }

    public function test_non_admin_cannot_manage_public_contact_system_settings(): void
    {
        $merchant = $this->userWithRole('merchant');
        $this->seed(PublicContactSettingSeeder::class);
        $setting = SystemSetting::query()->where('key', 'contact.phone')->firstOrFail();

        $this->actingAs($merchant)->get(route('admin.system-settings.edit', $setting))->assertForbidden();
        $this->actingAs($merchant)->put(route('admin.system-settings.update', $setting), $this->payload($setting, '+91 98765 43210'))->assertForbidden();
    }

    public function test_encrypted_setting_is_masked_and_blank_admin_update_preserves_secret(): void
    {
        $admin = $this->userWithRole('admin');
        $setting = app(SystemSettingService::class)->setEncrypted('test.smtp.password', 'never-render-this-secret', [
            'label' => 'Test SMTP Password',
        ]);
        $ciphertext = $setting->getRawOriginal('value');

        $this->actingAs($admin)
            ->get(route('admin.system-settings.index', ['search' => 'test.smtp.password']))
            ->assertOk()
            ->assertSee('Configured')
            ->assertDontSee('never-render-this-secret');

        $this->actingAs($admin)
            ->get(route('admin.system-settings.index', ['search' => 'never-render-this-secret']))
            ->assertOk()
            ->assertDontSee('test.smtp.password');

        $this->actingAs($admin)
            ->get(route('admin.system-settings.edit', $setting))
            ->assertOk()
            ->assertSee('Configured — leave blank to keep the current secret.')
            ->assertDontSee('never-render-this-secret')
            ->assertDontSee($ciphertext);

        $this->actingAs($admin)
            ->put(route('admin.system-settings.update', $setting), [
                'group_id' => $setting->group_id,
                'label' => $setting->label,
                'value' => '',
                'value_type' => SystemSetting::TYPE_ENCRYPTED,
                'description' => null,
                'sort_order' => 0,
                'status' => SystemSetting::STATUS_ACTIVE,
                'is_public' => 1,
                'is_encrypted' => 0,
            ])
            ->assertRedirect(route('admin.system-settings.edit', $setting));

        $fresh = $setting->fresh();
        $this->assertSame($ciphertext, $fresh->getRawOriginal('value'));
        $this->assertTrue($fresh->is_encrypted);
        $this->assertFalse($fresh->is_public);
        $this->assertSame(SystemSetting::TYPE_ENCRYPTED, $fresh->value_type);
    }

    private function payload(SystemSetting $setting, ?string $value): array
    {
        return [
            'group_id' => $setting->group_id,
            'label' => $setting->label,
            'value' => $value,
            'value_type' => $setting->value_type,
            'description' => $setting->description,
            'sort_order' => $setting->sort_order,
            'status' => $setting->status,
            'is_public' => 1,
        ];
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
