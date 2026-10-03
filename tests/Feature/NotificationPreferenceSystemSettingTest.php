<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\SystemSetting;
use App\Notifications\NotificationMessage;
use App\Services\Notification\NotificationPreferenceResolver;
use App\Services\System\SystemSettingKeys;
use App\Services\System\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class NotificationPreferenceSystemSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_dynamic_preference_keys_are_canonical_and_canonical_true_and_false_are_read(): void
    {
        $settings = app(SystemSettingService::class);
        $resolver = app(NotificationPreferenceResolver::class);

        $this->assertSame(
            'notifications.events.order.new.admin.email.enabled',
            SystemSettingKeys::notificationPreference('order.new.admin', 'email'),
        );

        $settings->setNotificationPreference('order.new.admin', 'email', true);
        $this->assertTrue($resolver->enabled($this->globalMessage()));

        $settings->setNotificationPreference('order.new.admin', 'email', false);
        $this->assertFalse($resolver->enabled($this->globalMessage()));
    }

    public function test_canonical_false_wins_over_legacy_true_and_canonical_true_wins_over_legacy_false(): void
    {
        $settings = app(SystemSettingService::class);
        $resolver = app(NotificationPreferenceResolver::class);

        $this->legacy('order.new.admin', true);
        $settings->setNotificationPreference('order.new.admin', 'email', false);
        $this->assertFalse($resolver->enabled($this->globalMessage()));

        AdminSetting::query()->where('group', 'notifications')->delete();
        $this->legacy('order.new.admin', false);
        $settings->setNotificationPreference('order.new.admin', 'email', true);
        $this->assertTrue($resolver->enabled($this->globalMessage()));
    }

    public function test_legacy_is_used_only_when_canonical_key_is_absent(): void
    {
        $this->legacy('order.new.admin', true);

        $this->assertTrue(app(NotificationPreferenceResolver::class)->enabled($this->globalMessage()));
        $this->assertDatabaseMissing('system_settings', [
            'key' => SystemSettingKeys::notificationPreference('order.new.admin', 'email'),
        ]);
    }

    public function test_catalogue_default_is_used_when_canonical_and_legacy_are_absent(): void
    {
        $resolver = app(NotificationPreferenceResolver::class);

        $this->assertFalse($resolver->enabled($this->globalMessage()));
        $this->assertTrue($resolver->defaultEnabled('order.confirmed.customer', 'email'));
    }

    public function test_soft_deleted_canonical_preference_is_a_tombstone_and_does_not_revive_legacy(): void
    {
        $settings = app(SystemSettingService::class);
        $settings->setNotificationPreference('order.new.admin', 'email', false)->delete();
        $this->legacy('order.new.admin', true);

        $this->assertFalse(app(NotificationPreferenceResolver::class)->enabled($this->globalMessage()));
        $this->assertNotNull(SystemSetting::withTrashed()
            ->where('key', SystemSettingKeys::notificationPreference('order.new.admin', 'email'))
            ->first()?->deleted_at);
    }

    private function globalMessage(): NotificationMessage
    {
        return new NotificationMessage('order.new.admin', 'admin', 'email');
    }

    private function legacy(string $eventKey, bool $enabled): void
    {
        AdminSetting::query()->create([
            'group' => 'notifications',
            'setting_key' => "events.{$eventKey}.email.enabled",
            'setting_value' => $enabled ? '1' : '0',
            'setting_type' => AdminSetting::TYPE_BOOLEAN,
        ]);
    }
}
