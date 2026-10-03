<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Notifications\NotificationMessage;
use App\Services\Notification\NotificationPreferenceResolver;
use App\Services\System\SystemSettingKeys;
use App\Services\System\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function test_canonical_false_and_true_remain_authoritative(): void
    {
        $settings = app(SystemSettingService::class);
        $resolver = app(NotificationPreferenceResolver::class);

        $settings->setNotificationPreference('order.new.admin', 'email', false);
        $this->assertFalse($resolver->enabled($this->globalMessage()));

        $settings->setNotificationPreference('order.new.admin', 'email', true);
        $this->assertTrue($resolver->enabled($this->globalMessage()));
    }

    public function test_catalogue_default_is_used_without_legacy_table(): void
    {
        Schema::dropIfExists('admin_settings');

        $this->assertFalse(app(NotificationPreferenceResolver::class)->enabled($this->globalMessage()));
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

    public function test_soft_deleted_canonical_preference_uses_catalogue_default(): void
    {
        $settings = app(SystemSettingService::class);
        $settings->setNotificationPreference('order.new.admin', 'email', true)->delete();

        $this->assertFalse(app(NotificationPreferenceResolver::class)->enabled($this->globalMessage()));
        $this->assertNotNull(SystemSetting::withTrashed()
            ->where('key', SystemSettingKeys::notificationPreference('order.new.admin', 'email'))
            ->first()?->deleted_at);
    }

    private function globalMessage(): NotificationMessage
    {
        return new NotificationMessage('order.new.admin', 'admin', 'email');
    }
}
