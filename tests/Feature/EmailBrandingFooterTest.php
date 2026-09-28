<?php

namespace Tests\Feature;

use App\Mail\TransactionalNotificationMail;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notification\EmailConfigurationService;
use App\Services\System\SystemSettingService;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class EmailBrandingFooterTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', static fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTemplateSeeder::class);
    }

    public function test_default_common_footer_uses_dynamic_marketplace_year_and_canonical_links(): void
    {
        DB::table('system_settings')->insert(['key' => 'marketplace_name', 'value' => 'Local Bazaar', 'value_type' => 'string', 'status' => 'active']);
        $this->app->forgetInstance(SystemSettingService::class);
        $this->app->forgetInstance(EmailConfigurationService::class);
        $html = $this->mail()->render();

        $this->assertStringContainsString('Powered by Local Bazaar', $html);
        $this->assertStringContainsString('© '.now()->year.' Local Bazaar. All rights reserved.', $html);
        $this->assertStringContainsString(route('storefront.privacy'), $html);
        $this->assertStringContainsString(route('storefront.terms'), $html);
        $this->assertStringContainsString(route('storefront.contact'), $html);
        $this->assertStringNotContainsString('Google Play', $html);
        $this->assertStringNotContainsString('Facebook', $html);
    }

    public function test_configured_optional_footer_content_renders_safely_and_blanks_do_not_render(): void
    {
        $this->savePresentation([
            'footer.benefit_1' => 'Shop <Local>',
            'footer.benefit_2' => '',
            'footer.benefit_3' => 'Easy Exchange',
            'footer.social.facebook' => 'https://facebook.example/windowshop',
            'footer.apps.google_play' => 'https://play.example/windowshop',
            'footer.powered_by_text' => '<strong>Built for {{ marketplace_name }}</strong>',
        ]);

        $html = $this->mail()->render();

        $this->assertStringContainsString('Shop &lt;Local&gt;', $html);
        $this->assertStringContainsString('Easy Exchange', $html);
        $this->assertStringContainsString('Facebook', $html);
        $this->assertStringContainsString('Get it on Google Play', $html);
        $this->assertStringNotContainsString('App Store', $html);
        $this->assertStringContainsString('&lt;strong&gt;Built for WindowShop&lt;/strong&gt;', $html);
        $this->assertStringNotContainsString('<strong>Built for WindowShop</strong>', $html);
    }

    public function test_footer_and_powered_by_can_be_hidden_independently(): void
    {
        $this->savePresentation(['footer.show_powered_by' => false]);
        $html = $this->mail()->render();
        $this->assertStringNotContainsString('Powered by WindowShop', $html);
        $this->assertStringContainsString('All rights reserved.', $html);

        $this->savePresentation(['footer.show' => false, 'footer.show_powered_by' => true]);
        $html = $this->mail()->render();
        $this->assertStringNotContainsString('All rights reserved.', $html);
        $this->assertStringNotContainsString('Privacy Policy', $html);
        $this->assertStringNotContainsString('Powered by WindowShop', $html);
    }

    public function test_admin_rejects_invalid_urls_and_unknown_powered_by_variables(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.email-settings.update'), $this->settingsPayload([
            'footer' => [
                'show' => 1,
                'show_powered_by' => 1,
                'powered_by_text' => 'Powered by {{ unsafe }}',
                'social' => ['facebook' => 'javascript:alert(1)'],
            ],
        ]))->assertSessionHasErrors(['footer.social.facebook', 'footer.powered_by_text']);
    }

    public function test_admin_can_upload_replace_and_remove_dedicated_email_logo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.email-settings.edit'))
            ->assertOk()
            ->assertSee('Current Email Logo Preview')
            ->assertSee('id="email_logo_preview"', false)
            ->assertSee('js-clear-email-logo-preview');

        $this->actingAs($admin)->put(route('admin.email-settings.update'), $this->settingsPayload([
            'email_logo' => UploadedFile::fake()->image('email-logo.png', 400, 120),
        ]))->assertSessionHas('success');

        $service = app(EmailConfigurationService::class);
        $path = $service->values()['branding.logo_path'];
        Storage::disk('public')->assertExists($path);

        $this->actingAs($admin)->put(route('admin.email-settings.update'), $this->settingsPayload([
            'remove_email_logo' => 1,
        ]))->assertSessionHas('success');
        Storage::disk('public')->assertMissing($path);
        $this->assertSame('', $service->values()['branding.logo_path']);
    }

    public function test_notification_preview_uses_the_same_configured_common_footer(): void
    {
        $this->savePresentation(['footer.benefit_1' => 'Preview Benefit', 'footer.show_powered_by' => false]);
        $template = NotificationTemplate::query()->where('event_key', 'customer.registered')->where('channel', 'email')->firstOrFail();

        $this->actingAs($this->admin())->put(route('admin.notification-templates.preview', $template), [
            'subject' => $template->subject,
            'body' => $template->body,
        ])->assertOk()->assertSee('Preview Benefit')->assertDontSee('Powered by WindowShop');
    }

    private function mail(): TransactionalNotificationMail
    {
        $service = app(EmailConfigurationService::class);
        $marketplaceName = app(SystemSettingService::class)->marketplaceName();

        return new TransactionalNotificationMail(
            'Test notification',
            'Safe body',
            $marketplaceName,
            $service->emailLogoUrl(),
            $marketplaceName,
            'sender@example.test',
            $marketplaceName,
            emailPresentation: $service->presentation(),
        );
    }

    /** @param array<string, mixed> $overrides */
    private function savePresentation(array $overrides): void
    {
        app(EmailConfigurationService::class)->save(array_merge([
            'branding.show_name' => true,
            'footer.show' => true,
            'footer.benefit_1' => '',
            'footer.benefit_2' => '',
            'footer.benefit_3' => '',
            'footer.social.facebook' => '',
            'footer.social.instagram' => '',
            'footer.social.youtube' => '',
            'footer.social.twitter' => '',
            'footer.social.linkedin' => '',
            'footer.apps.google_play' => '',
            'footer.apps.app_store' => '',
            'footer.show_powered_by' => true,
            'footer.powered_by_text' => 'Powered by {{ marketplace_name }}',
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function settingsPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'enabled' => 0,
            'smtp' => ['encryption' => 'tls'],
            'branding' => ['show_name' => 1],
            'footer' => ['show' => 1, 'show_powered_by' => 1, 'powered_by_text' => 'Powered by {{ marketplace_name }}'],
        ], $overrides);
    }

    private function admin(): User
    {
        $user = User::query()->create(['name' => 'Email Admin', 'email' => uniqid('email-admin-').'@example.test', 'password' => Hash::make('password'), 'status' => 'active']);
        $roleId = DB::table('auth_roles')->insertGetId(['name' => 'Super Admin', 'slug' => 'super_admin', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('auth_user_roles')->insert(['user_id' => $user->getKey(), 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }
}
