<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Notifications\NotificationChannelName;
use App\Services\Notification\AdminNotificationRecipientResolver;
use App\Services\Notification\EmailConfigurationService;
use App\Services\System\SystemSettingKeys;
use App\Services\System\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class EmailConfigurationSystemSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_canonical_non_secret_values_preserve_false_and_blank(): void
    {
        $settings = app(SystemSettingService::class);
        $settings->setEmailSetting('enabled', false, SystemSetting::TYPE_BOOLEAN);
        $settings->setEmailSetting('smtp.host', '');

        $values = app(EmailConfigurationService::class)->values();

        $this->assertFalse($values['enabled']);
        $this->assertSame('', $values['host']);
    }

    public function test_non_secret_application_defaults_work_without_legacy_table(): void
    {
        SystemSetting::withTrashed()->where('key', SystemSettingKeys::email('smtp.host'))->forceDelete();
        Schema::dropIfExists('admin_settings');

        $values = app(EmailConfigurationService::class)->values();

        $this->assertSame('', $values['host']);
        $this->assertSame(587, $values['port']);
        $this->assertSame('tls', $values['encryption']);
        $this->assertFalse($values['enabled']);
        $this->assertDatabaseMissing('system_settings', ['key' => SystemSettingKeys::email('smtp.host')]);
    }

    public function test_soft_deleted_canonical_email_setting_is_a_tombstone(): void
    {
        app(SystemSettingService::class)->setEmailSetting('smtp.host', 'canonical.smtp.test')->delete();

        $this->assertSame('', app(EmailConfigurationService::class)->values()['host']);
    }

    public function test_admin_notification_recipient_uses_canonical_value_and_normal_empty_fallback(): void
    {
        app(SystemSettingService::class)->setEmailSetting('admin_notification_email', 'canonical@example.test');

        $this->assertSame(
            [['id' => null, 'destination' => 'canonical@example.test']],
            app(AdminNotificationRecipientResolver::class)->forChannel(NotificationChannelName::EMAIL),
        );

        SystemSetting::withTrashed()
            ->where('key', SystemSettingKeys::email('admin_notification_email'))
            ->forceDelete();

        $this->assertSame([], app(AdminNotificationRecipientResolver::class)->forChannel(NotificationChannelName::EMAIL));
    }

    public function test_email_social_links_remain_independent_from_storefront_social_links(): void
    {
        $settings = app(SystemSettingService::class);
        $settings->set('social.facebook', 'https://storefront.example.test/windowshop');
        $settings->setEmailSetting('footer.social.facebook', 'https://email.example.test/windowshop');

        $presentation = app(EmailConfigurationService::class)->presentation();

        $this->assertSame(
            'https://email.example.test/windowshop',
            $presentation['social_links']['Facebook'],
        );
        $this->assertNotSame(
            $settings->socialLinks()['Facebook'],
            $presentation['social_links']['Facebook'],
        );
    }

    public function test_invalid_canonical_password_ciphertext_is_not_configured_or_exposed(): void
    {
        $settings = app(SystemSettingService::class);
        $secret = $settings->setEmailPassword('temporary-secret');
        DB::table('system_settings')->where('id', $secret->getKey())->update(['value' => 'copied-unusable-ciphertext']);

        $email = app(EmailConfigurationService::class);

        $this->assertFalse($email->passwordConfigured());
        $this->assertNull($email->decryptedPassword());
        $this->assertArrayNotHasKey('smtp.password', $email->values());
        $this->assertNull($settings->get(SystemSettingKeys::EMAIL_SMTP_PASSWORD));
    }

    public function test_unmarked_migrated_password_ciphertext_is_not_used_by_runtime(): void
    {
        $settings = app(SystemSettingService::class);
        $settings->setEncrypted(SystemSettingKeys::EMAIL_SMTP_PASSWORD, 'legacy-compatible-secret');

        $email = app(EmailConfigurationService::class);

        $this->assertFalse($email->passwordConfigured());
        $this->assertNull($email->decryptedPassword());
    }

    public function test_description_changes_do_not_affect_versioned_password_validity(): void
    {
        $settings = app(SystemSettingService::class);
        $secret = $settings->setEmailPassword('description-independent-secret');
        $secret->update(['description' => 'Documentation text changed independently.']);

        $email = app(EmailConfigurationService::class);

        $this->assertTrue($email->passwordConfigured());
        $this->assertTrue(hash_equals('description-independent-secret', $email->decryptedPassword() ?? ''));
    }

    public function test_empty_versioned_password_is_not_configured(): void
    {
        app(SystemSettingService::class)->setEncrypted(
            SystemSettingKeys::EMAIL_SMTP_PASSWORD,
            'canonical-smtp:v1:',
        );

        $email = app(EmailConfigurationService::class);

        $this->assertFalse($email->passwordConfigured());
        $this->assertNull($email->decryptedPassword());
    }
}
